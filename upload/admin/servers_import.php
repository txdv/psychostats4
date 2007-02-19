<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if ($register_admin_controls) {
	return 1;
}

$data['PS_ADMIN_PAGE'] = 'servers';

if ($cancel) previouspage(ps_url_wrapper(array('_amp' => '&', 'c' => 'servers')));

$validfields = array('upload');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

// form fields ...
$formfields = array(
	'csvfile'	=> array('label' => $ps_lang->trans("Upload CSV File"). ':', 	'val' => '', 'statustext' => $ps_lang->trans("Upload a CSV file with updates")),
);

$form = array();
$errors = array();
$success = '';

if ($submit) {
	$validkeys = $ps_db->table_columns($ps->t_config_servers);
	$form = packform($formfields);
	$keys = array();
	$csv = array();
	$fh = 0;

	// if 'upload'
	$file = $_FILES['csvfile'];
	if ($file['size'] and is_uploaded_file($file['tmp_name'])) {
		$fh = @fopen($file['tmp_name'], 'r');
		if (!$fh) {
			$formfields['csvfile']['error'] = $ps_lang->trans("Error processing uploaded file");
		}
	} else {
		$formfields['csvfile']['error'] = $ps_lang->trans("Uploaded file is invalid");
	}

	if ($fh) {
		$csvkeys = fgetcsv($fh, 1024, ',', '"');
		foreach ($csvkeys as $k) {
			if (in_array($k, $validkeys)) $keys[] = $k;
		}
		unset($keys['id']);

		while ($line = fgetcsv($fh, 1024, ',', '"')) {
			if (count($line)==1 and $line[0] === NULL) continue;
			$csv[] = $line;
		}
		@fclose($fh);
	}

	// process changes
	if (count($keys) and count($csv)) {
		foreach ($csv as $idx => $list) {
			$set = array();
			// build a set of "var => val" pairs from csv data
			for ($i=0; $i < count($keys); $i++) {
				$set[ $keys[$i] ] = $list[$i];
			}

			// update or insert info
			if ($ps_db->exists($ps->t_config_servers, 'serverip', $set['serverip'])) {
				$res = $ps_db->update($ps->t_config_servers, $set, 'serverip', $set['serverip']);
			} else {
				$set['id'] = $ps_db->next_id($ps->t_config_servers, 'id');
				$res = $ps_db->insert($ps->t_config_servers, $set);
			}
			if (!$res) {
				$errors[] = $ps->errstr;
			}
		}
		if (!count($errors)) $success .= count($csv) . " " . $ps_lang->trans("Servers imported successfully");
		if ($success) gotopage("$PHP_SELF?c=servers&msg=" . urlencode($success));
	}

} else {
	# ...
}

$data['lastmod'] = $lastmod ? date("D M j G:i:s T Y", $lastmod) : 0;
$data['servermsg'] = $srvmsg;
$data['errors'] = $errors;
$data['success'] = $success;
$data['form'] = $formfields;

?>
