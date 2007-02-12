<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if ($register_admin_controls) {
	$menu =& $PSAdminMenu->getSection( $ps_lang->trans("Manage Players") );

	$opt =& $menu->newOption( $ps_lang->trans("Aliases"), 'aliases' );
	$opt->link(ps_url_wrapper(array('c' => 'aliases')));

	return 1;
}

$data['PS_ADMIN_PAGE'] = "aliases";

if ($cancel) previouspage("admin.php?c=".urlencode($c));

$validfields = array('filter','export','import','new','del','edit','id','act','msg');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

if ($import) gotopage("$PHP_SELF?c=aliases_import");

// form fields ...
$formfields = array(
	// act = 'edit'
	'uniqueid'	=> array('label' => $ps_lang->trans("Unique ID").':',	'val' => 'B',  'statustext' => $ps_lang->trans("Unique ID of a player to set alias to")),
	'alias'		=> array('label' => $ps_lang->trans("Alias").':',		'val' => 'B',  'statustext' => $ps_lang->trans("Alias the unique ID to this string")),
	
);

if (!is_numeric($id)) $id = 0;


$where = "";
if ($filter != '') {
	if ($where) $where .= " AND ";
	$where .= "alias LIKE '%" . $ps_db->escape($filter) . "%' OR ";
	$where .= "uniqueid LIKE '%" . $ps_db->escape($filter) . "%' ";
}
if ($where) $where = "WHERE $where";

$cmd = "SELECT * FROM $ps->t_plr_aliases $where ORDER BY uniqueid";
//if (!$export) $cmd .= " LIMIT $start,$limit";

$list = array();
$list = $ps_db->fetch_rows(1, $cmd);

# export the data and exit
if ($export) {
	// get the first item in the list so we can determine what keys are available
	$i = $list[0];
	unset($i['uniqueid'], $i['id']);		// remove unwanted keys
	$keys = array_keys($i);				// get a list of the keys (no values)
	array_unshift($keys, 'uniqueid');		// make sure uniqueid is always the first key

	$csv = csv($keys);				// 1st row is always the key order
	foreach ($list as $i) {
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
	header("Content-Disposition: attachment; filename=\"ps-plraliases.csv\"");
	print $csv;
	exit();
}

$plralias = array();
if ($id) {
	$plralias = $ps_db->fetch_row(1, "SELECT * FROM $ps->t_plr_aliases WHERE id='" . $ps_db->escape($id) . "'");
}

$form = array();
$errors = array();

if ($del and $plralias['id']) {
	$ps_db->delete($ps->t_plr_aliases, 'id', $plralias['id']);
	previouspage("admin.php?c=".urlencode($c));

} elseif ($submit and $act == 'edit' and $_SERVER['REQUEST_METHOD'] == 'POST') {
	$form = packform($formfields);
	trim_all($form);

	// 
	if ($form['uniqueid'] == $form['alias']) {
		$formfields['alias']['error'] = $ps_lang->trans("Can not be the same as the uniqueid");
	}

	// do not allow duplicate uniqueids
#	if (!$id and $ps_db->exists($ps->t_plr_aliases, 'uniqueid', $form['uniqueid'])) {
#		$formfields['uniqueid']['error'] = $ps_lang->trans("Duplicate uniqueid");
#	}

	// automatically verify all fields
	foreach ($formfields as $key => $ignore) {
		form_checks($form[$key], $formfields[$key]);
	}

	$errors = all_form_errors($formfields);

	if (!count($errors)) {
		$set = $form;
		if (!$id) {
			$set['id'] = $ps_db->next_id($ps->t_plr_aliases);
		}

		$ok = 0;
		$ps_db->begin();
		if ($id) {
			$ok = $ps_db->update($ps->t_plr_aliases, $set, 'id', $id);
		} else {
			$ok = $ps_db->insert($ps->t_plr_aliases, $set);
		}

		if ($ok) {
			$ps_db->commit();
			previouspage("admin.php?c=".urlencode($c));
		} else {
			$ps_db->rollback();
		}
	}
	$data += $form;	
	$data['adminpage'] = 'aliases_edit';
} elseif ($act == 'edit') {
	$data += $plralias;
	$data['adminpage'] = 'aliases_edit';
} elseif ($submit and $new) {
	$data['adminpage'] = 'aliases_edit';
}

$data['form'] = $formfields;
$data['errors'] = $errors;
$data['aliaslist'] = $list;
$data['plralias'] = $plralias;
$data['id'] = $id;
$data['act'] = $act;

?>
