<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if ($register_admin_controls) {
	return 1;
}

$data['PS_ADMIN_PAGE'] = 'clans';

if ($cancel) previouspage(ps_url_wrapper(array('_amp' => '&', 'c' => 'clans')));

$validfields = array('srvimport','upload');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

// form fields ...
$formfields = array(
	'csvfile'	=> array('label' => $ps_lang->trans("Upload CSV File"). ':', 	'val' => '', 'statustext' => $ps_lang->trans("Upload a CSV file with updates")),
);

$srvimport = 0;	// remove this line and uncomment the following block if you want server imports to work
//list($lastmod,$srvmsg) = ps_import_update_time('clans');

$form = array();
$errors = array();
$success = '';

if ($submit) {
	$validkeys = $ps_db->table_columns($ps->t_clan_profile);
	$form = packform($formfields);
	$keys = array();
	$csv = array();
	$fh = 0;

	if ($srvimport) {
		if ($downloadok) {
			$fh = ps_import_open('clans');
		}
		if (!$fh or !$downloadok) {
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
		unset($keys['clanid'],$keys['allowrank'],$keys['locked']);

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
			if ($ps_db->exists($ps->t_clan_profile, 'clantag', $set['clantag'])) {
				$res = $ps_db->update($ps->t_clan_profile, $set, 'clantag', $set['clantag']);
			} else {
				$clanid = $ps_db->next_id($ps->t_clan, "clanid");
				$res = $ps_db->insert($ps->t_clan, array('clanid' => $clanid, 'clantag' => $set['clantag'], 'allowrank' => '0', 'locked' => '0'));
				$res = $ps_db->insert($ps->t_clan_profile, $set);
			}
			if (!$res) {
				$errors[] = $ps_db->errstr;
			}
		}
		if (!count($errors)) $success .= count($csv) . " " . $ps_lang->trans("clan profiles imported successfully");
		if ($success) gotopage("admin.php?c=clans&msg=" . urlencode($success));
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
