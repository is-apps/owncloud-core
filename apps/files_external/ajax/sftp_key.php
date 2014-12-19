<?php
set_include_path(get_include_path().PATH_SEPARATOR.
	\OC_App::getAppPath('files_external').'/3rdparty/phpseclib/phpseclib/phpseclib');
include('Crypt/RSA.php');

OCP\JSON::checkAppEnabled('files_external');
OCP\JSON::checkLoggedIn();
OCP\JSON::callCheck();
$l = \OC::$server->getL10N('files_external');

$rsa = new Crypt_RSA();
$rsa->setPublicKeyFormat(CRYPT_RSA_PUBLIC_FORMAT_OPENSSH);
$rsa->setPassword(\OC_Config::getValue('secret', ''));

extract($rsa->createKey());
// Replace the placeholder label with a more meaningful one
$publickey = str_replace('phpseclib-generated-key', gethostname(), $publickey);

OCP\JSON::success(array('data' => array(
	'private_key' => $privatekey,
	'public_key' => $publickey
)));
