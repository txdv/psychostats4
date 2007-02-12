<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if ($register_admin_controls) {
	return 1;
}

$data['PS_ADMIN_PAGE'] = 'clantags';

if ($cancel) previouspage(ps_url_wrapper(array('_amp' => '&', 'c' => 'clantags')));

$validfields = array('srvimport','upload');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

// form fields ...
$formfields = array(
	'csvfile'	=> array('label' => $ps_lang->trans("Upload CSV File"). ':', 	'val' => '', 'statustext' => $ps_lang->trans("Upload a CSV file with updates")),
	'srvimport'	=> array('label' => $ps_lang->trans("Clantags Updated"). ':',	'val' => '', 'statustext' => $ps_lang->trans("Shows the last time the clantags on psychostats.com were updated")),
);

list($lastmod,$srvmsg) = ps_import_update_time('clantags');

$form = array();
$errors = array();
$success = '';

if ($submit) {
	$validkeys = $ps_db->table_columns($ps->t_config_clantags);
	$form = packform($formfields);
	$keys = array();
	$csv = array();
	$fh = 0;

	if ($srvimport) {
		if ($lastmod) {
			$fh = ps_import_open('clantags');
		}
		if (!$fh or !$lastmod) {
			$formfields['srvimport']['error'] = $ps_lang->trans("Unable to download updates");
		}

	} elseif ($upload) {
		$file = $_FILES['csvfile'];
		if ($file['size'] and is_uploaded_file($file['tmp_name'])) {
			$fh = @fopen($file['tmp_name'], 'r');
			if (!$fh) {
				$formfields['csvfile']['error'] = $ps_lang->trans("Error processing uploaded file");
			}
		} else {
			$formfields['csvfile']['error'] = $ps_lang->trans("Uploaded file is invalid");
		}
	}

	if ($fh) {
		$csvkeys = fgetcsv($fh, 1024, ',', '"');
		foreach ($csvkeys as $k) {
			if (in_array($k, $validkeys)) $keys[] = $k;
		}
		unset($keys['weaponid']);

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
			if ($ps_db->exists($ps->t_config_clantags, 'clantag', $set['clantag'])) {
				$res = $ps_db->update($ps->t_config_clantags, $set, 'clantag', $set['clantag']);
			} else {
				$set['id'] = $ps_db->next_id($ps->t_config_clantags, 'id');
				$res = $ps_db->insert($ps->t_config_clantags, $set);
			}
			if (!$res) {
				$errors[] = $ps->errstr;
			}
		}
		if (!count($errors)) $success .= count($csv) . " " . $ps_lang->trans("clantags imported successfully");
		if ($success) gotopage("$PHP_SELF?c=clantags&msg=" . urlencode($success));
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
