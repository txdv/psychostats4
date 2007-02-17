<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if ($register_admin_controls) {
	return 1;
}

$data['PS_ADMIN_PAGE'] = 'aliases';

if ($cancel) previouspage(ps_url_wrapper(array('_amp' => '&', 'c' => 'aliases')));

$validfields = array('srvimport','upload');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

// form fields ...
$formfields = array(
	'csvfile'	=> array('label' => $ps_lang->trans("Upload CSV File"). ':', 	'val' => '', 'statustext' => $ps_lang->trans("Upload a CSV file with updates")),
);

$srvimport = 0;	// remove this line and uncomment the following block if you want server imports to work
//list($lastmod,$srvmsg) = ps_import_update_time('aliases');

$form = array();
$errors = array();
$success = '';

if ($submit) {
	$validkeys = $ps_db->table_columns($ps->t_plr_aliases);
	$form = packform($formfields);
	$keys = array();
	$csv = array();
	$fh = 0;

	if ($srvimport) {
		if ($lastmod) {
			$fh = ps_import_open('aliases');
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

			// insert info; ignore entries that already exist
			$exists = $ps_db->fetch_item(sprintf("SELECT id FROM $ps->t_plr_aliases WHERE uniqueid='%s' AND alias='%s'", 
				$ps_db->escape($set['uniqueid']), 
				$ps_db->escape($set['alias'])
			));
			if (!$exists) {
				$set['id'] = $ps_db->next_id($ps->t_plr_aliases);
				$res = $ps_db->insert($ps->t_plr_aliases, $set);
			}
			if (!$res) {
				$errors[] = $ps_db->errstr;
			}
		}
		if (!count($errors)) $success .= count($csv) . " " . $ps_lang->trans("aliases imported successfully");
		if ($success) gotopage("admin.php?c=aliases&msg=" . urlencode($success));
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
