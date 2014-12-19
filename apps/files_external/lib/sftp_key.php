<?php
/**
 * Copyright (c) 2014 University of Edinburgh <Ross.Nicoll@ed.ac.uk>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
namespace OC\Files\Storage;

require_once __DIR__ . '/../../../3rdparty/phpseclib/phpseclib/phpseclib/Crypt/RSA.php';


/**
* Uses phpseclib's Net_SFTP class and the Net_SFTP_Stream stream wrapper to
* provide access to SFTP servers.
*/
class SFTP_Key extends \OC\Files\Storage\SFTP {
	private $public_key;
	private $private_key;

	public function __construct($params) {
		parent::__construct($params);
		$this->public_key = $params['public_key'];
		$this->private_key = $params['private_key'];
	}

	/**
	 * Returns the connection.
	 *
	 * @return \Net_SFTP connected client instance
	 * @throws \Exception when the connection failed
	 */
	public function getConnection() {
		if (!is_null($this->client)) {
			return $this->client;
		}

		$hostKeys = $this->readHostKeys();
		$this->client = new \Net_SFTP($this->getHost());

		// The SSH Host Key MUST be verified before login().
		$currentHostKey = $this->client->getServerPublicHostKey();
		if (array_key_exists($this->getHost(), $hostKeys)) {
			if ($hostKeys[$this->getHost()] != $currentHostKey) {
				throw new \Exception('Host public key does not match known key');
			}
		} else {
			$hostKeys[$this->getHost()] = $currentHostKey;
			$this->writeHostKeys($hostKeys);
		}

		$key = $this->getPrivateKey();
		if ($key == null) {
			throw new \Exception('Secret key could not be loaded');
		}
		if (!$this->client->login($this->getUser(), $key)) {
			throw new \Exception('Login failed');
		}
		return $this->client;
	}

	private function getPrivateKey() {
		$key = new \Crypt_RSA();
		$key->setPassword(\OC_Config::getValue('secret', ''));
                if (!$key->loadKey($this->private_key)) {
                        return null;
                }
		return $key;
	}

	public function test() {
		if (
			$this->getHost() == ''
			|| $this->getUser() == ''
			|| !isset($this->private_key)
		) {
			return false;
		}

		// Sanity check the host
		$host_parts = explode(':', $this->getHost());
		switch(count($host_parts)) {
		case 1:
			$hostname = $host_parts[0];
			if (!preg_match('^/\d+\.\d+\.\d+\.\d+$/', $hostname)
				&& gethostbyname($hostname) === $hostname) {
				// Hostname is not an IPv4 address and cannot be resolved via DNS
				return false;
			}
			break;
		case 2:
			$hostname = $host_parts[0];
			if (!preg_match('/^\d+\.\d+\.\d+\.\d+$/', $hostname)
				&& gethostbyname($hostname) === $hostname) {
				// Hostname is not an IPv4 address and cannot be resolved via DNS
				return false;
			}
			$port = $host_parts[1];
			if (!preg_match('/^\d+$/', $port)) {
				return false;
			}
			break;
		default:
			// Host must be a name, or name and port
			return false;
		}

		// Validate the key
		$key = $this->getPrivateKey();
		if ($key == null) {
			error_log('Secret key could not be loaded');
			return false;
		}

		if ($this->getConnection()->nlist() === false) {
			return false;
		}

		// Save the key somewhere it can easily be extracted later
                $owncloud_user = \OC_User::getUser();
		$key_dir = \OC_User::getHome($owncloud_user).'/sftp_keys';
		if (!is_dir($key_dir)) {
			if (!mkdir($key_dir, 0777, true)) {
				error_log('Could not create secret key directory.');
				return false;
			}
			chmod($key_dir, 700);
		}
		$key_filename = $key_dir.'/'.preg_replace('/[^\d\w_]/', '_', $this->getUser()).'@'.preg_replace('/[^\d\w\._]/', '_', $hostname).'.pub';
		$key_file = fopen($key_filename, "w");
		if ($key_file) {
			// chmod($key_filename, 700);
			fwrite($key_file, $this->public_key);
			fclose($key_file);
		} else {
			error_log('Could not write secret key file.');
		}

		return true;
	}
}
