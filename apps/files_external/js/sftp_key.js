$(document).ready(function() {

	$('#externalStorage tbody tr.\\\\OC\\\\Files\\\\Storage\\\\SFTP_Key').each(function() {
		var tr = $(this);
		var config = $(tr).find('.configuration');
		if ($(config).find('.sftp_key').length == 0) {
			setup_table_row(tr, config);
		}
	});

	// We can't catch the DOM elements being added, but we can pick up when
	// they receive focus
	$('#externalStorage').on('focus', 'tbody tr.\\\\OC\\\\Files\\\\Storage\\\\SFTP_Key', function() {
		var tr = $(this);
		var config = $(tr).find('.configuration');

		if ($(config).find('.sftp_key').length == 0) {
			setup_table_row(tr, config);
		}
	});

	$('#externalStorage').on('click', '.sftp_key', function(event) {
		event.preventDefault();
		var tr = $(this).parent().parent();
		generate_keys(tr);
	});

	function setup_table_row(tr, config) {
		$(config).find('[data-parameter="public_key"]').attr('readonly', 'readonly');
		$(config).append($(document.createElement('a')).addClass('button sftp_key')
			.text(t('files_external', 'Generate keys')));
		// If there's no private key, build one
		if ($(config).find('[data-parameter="private_key"]').val() == '') {
			generate_keys(tr);
		}
	}

	function generate_keys(tr) {
		var config = $(tr).find('.configuration');

		$.post(OC.filePath('files_external', 'ajax', 'sftp_key.php'), {}, function(result) {
			if (result && result.status == 'success') {
				$(config).find('[data-parameter="public_key"]').val(result.data.public_key);
				$(config).find('[data-parameter="private_key"]').val(result.data.private_key);
				OC.MountConfig.saveStorage(tr, function(status) {
					// Nothing to do
				});
			} else {
				OC.dialogs.alert(result.data.message, t('files_external', 'Error generating key pair') );
			}
		});
	}
});
