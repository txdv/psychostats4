<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

include_once("_awards.php");

if ($register_admin_controls) {
	$menu =& $PSAdminMenu->getSection( $ps_lang->trans("Configuration") );

	$opt =& $menu->newOption( $ps_lang->trans("Awards"), 'awards' );
	$opt->link(ps_url_wrapper(array('c' => 'awards')));

	return 1;
}

$data['PS_ADMIN_PAGE'] = "awards";

$ps->load_config('awards');

if ($cancel) previouspage("$PHP_SELF?c=".urlencode($c));

$validfields = array('id','act','g','m','filter','new','del','export','import','msg','s');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

if ($import) gotopage("$PHP_SELF?c=awards_import");

// form fields ...
$formfields = array(
	// act = 'edit'
	'enabled'	=> array('label' => $ps_lang->trans("Enabled?").':',	'val' => 'N', 'statustext' => $ps_lang->trans("Is the award enabled?")),
	'gametype'	=> array('label' => $ps_lang->trans("Gametype").':',	'val' => '',  'statustext' => $ps_lang->trans("The gametype for the bonus (or blank for all)")),
	'modtype'	=> array('label' => $ps_lang->trans("Modtype").':',	'val' => '',  'statustext' => $ps_lang->trans("The modtype for the bonus (or blank for all mods of the gametype)")),
	'class'		=> array('label' => $ps_lang->trans("Award Class").':',	'val' => '',  'statustext' => $ps_lang->trans("Alternate plugin class to calculate the award (optional)")),
	'type'		=> array('label' => $ps_lang->trans("Award Type").':',	'val' => 'B', 'statustext' => $ps_lang->trans("The type of the award")),
	'name'		=> array('label' => $ps_lang->trans("Award Title").':',	'val' => 'B', 'statustext' => $ps_lang->trans("Title of the award")),
	'groupname'	=> array('label' => $ps_lang->trans("Group Title").':',	'val' => '',  'statustext' => $ps_lang->trans("Group title for the award (only used for weapon awards)")),
	'expr'		=> array('label' => $ps_lang->trans("Expression").':',	'val' => 'B', 'statustext' => $ps_lang->trans("Expression that is used to calculate the award")),
	'order'		=> array('label' => $ps_lang->trans("Order").':',		'val' => 'B', 'statustext' => $ps_lang->trans("The order of the output for the expression")),
	'where'		=> array('label' => $ps_lang->trans("Where Clause").':',	'val' => '',  'statustext' => $ps_lang->trans("Simple where clause to limit who is counted in the award")),
	'limit'		=> array('label' => $ps_lang->trans("Limit").':',		'val' => 'BN','statustext' => $ps_lang->trans("Limit how many players are included in each award")),
	'format'	=> array('label' => $ps_lang->trans("Format").':',		'val' => 'B', 'statustext' => $ps_lang->trans("Format of the award value")),
	'desc'		=> array('label' => $ps_lang->trans("Description").':',	'val' => '',  'statustext' => $ps_lang->trans("Short description of the award")),
);

// load all current gametypes and modtypes
$gametypes = $ps_db->fetch_list("SELECT DISTINCT gametype FROM $ps->t_config_awards ORDER BY gametype");
$modtypes = $ps_db->fetch_list("SELECT DISTINCT modtype FROM $ps->t_config_awards ORDER BY modtype");

$act = strtolower($act);
if (!is_numeric($id)) $id = 0;
if (!in_array($g, $gametypes)) $g = '';
if (!in_array($m, $modtypes)) $m = '';

$where = "";
if ($g) {
	$where .= "gametype='" . $ps_db->escape($g) . "'";
}
if ($m) {
	if ($where) $where .= " AND ";
	$where .= "modtype='" . $ps_db->escape($m) . "'";
}
if ($filter) {
	if ($where) $where .= " AND ";
	$_filter = $ps_db->escape($filter);
	$where .= "(name LIKE '%$_filter%' OR type LIKE '%$_filter%')";
}
if ($where) $where = "WHERE $where";

$cmd = "SELECT * FROM $ps->t_config_awards $where ORDER BY type,name";

$awardlist = array();
$awardlist = $ps_db->fetch_rows(1, $cmd);

# export the data and exit
if ($export) {
//	$list = array_merge($playerlist, $weaponlist);
	// get the first item in the list so we can determine what keys are available
	$i = $awardlist[0];
	unset($i['name'], $i['id']);			// remove unwanted keys
	$keys = array_keys($i);				// get a list of the keys (no values)
	array_unshift($keys, 'name');			// make sure this is always the first key

	$csv = csv($keys);				// 1st row is always the key order
	foreach ($awardlist as $i) {
		$set = array();
		foreach ($keys as $k) {			// we want to make sure our key order is the same
			$set[] = $i[$k];		// and we only use keys from the original $keys list
		}
		$csv .= csv($set);
	}

	// remove all pending output buffers first 
	while (@ob_end_clean());
	header("Pragma: no-cache");
	header("Content-Type: text/csv");
	header("Content-Length: " . strlen($csv));
	header("Content-Disposition: attachment; filename=\"ps-awards.csv\"");
	print $csv;
	exit();
}

$award = array();
if ($id) {
	$award = $ps_db->fetch_row(1, "SELECT * FROM $ps->t_config_awards WHERE id='" . $ps_db->escape($id) . "'");
}

$form = array();
$errors = array();

if ($submit and $del and $award['id']) {
	$ps_db->delete($ps->t_config_awards, 'id', $award['id']);
	previouspage("$PHP_SELF?c=".urlencode($c));

} elseif ($submit and $act == 'edit' and $_SERVER['REQUEST_METHOD'] == 'POST') {
	$form = packform($formfields);
	trim_all($form);

	$form['gametype'] = strtolower($form['gametype']);
	$form['modtype'] = strtolower($form['modtype']);

	// if the class is empty default it to the type
/*
	if ($form['class'] == '') {
		$form['class'] = $form['type'];
	}
*/

	// do not allow duplicate award names
	if (!$id and $ps_db->exists($ps->t_config_awards, 'name', $form['name'])) {
		$formfields['name']['error'] = $ps_lang->trans("Duplicate award name");
	}

	// automatically verify all fields
	foreach ($formfields as $key => $ignore) {
		form_checks($form[$key], $formfields[$key]);
	}

	$errors = all_form_errors($formfields);

	if (!count($errors)) {
		$set = $form;
		if (!$id) {
			$set['id'] = $ps_db->next_id($ps->t_config_awards);
		}

		$ok = 0;
		$ps_db->begin();
		if ($id) {
			$ok = $ps_db->update($ps->t_config_awards, $set, 'id', $id);
		} else {
			$ok = $ps_db->insert($ps->t_config_awards, $set);
		}

		if ($ok) {
			$ps_db->commit();
			previouspage("$PHP_SELF?c=".urlencode($c));
		} else {
			$ps_db->rollback();
		}
	}
	$data += $form;	
	$data['adminpage'] = 'awards_edit';
} elseif ($act == 'edit') {
	if (!$award['id']) award_defaults();
	$data += $award;
	$data['adminpage'] = 'awards_edit';
} elseif ($submit and $new) {
	if (!$award['id']) award_defaults();
	$data['adminpage'] = 'awards_edit';
}

$data['form'] = $formfields;
$data['errors'] = $errors;
$data['awardlist'] = $awardlist;
$data['award'] = $award;
$data['id'] = $id;
$data['act'] = $act;
$data['gametypes'] = $gametypes;
$data['modtypes'] = $modtypes;

?>
