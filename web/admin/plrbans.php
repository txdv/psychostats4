<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if ($register_admin_controls) {
	$menu =& $PSAdminMenu->getSection( $ps_lang->trans("Manage Players") );

	$opt =& $menu->newOption( $ps_lang->trans("Bans"), 'plrbans' );
	$opt->link(ps_url_wrapper(array('c' => 'plrbans')));

	return 1;
}

$data['PS_ADMIN_PAGE'] = "plrbans";

if ($cancel) previouspage("admin.php?c=".urlencode($c));

$validfields = array('filter','export','import','new','actionlist','edit','id','act','delete','enable','disable','matchtype','msg');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

if ($import) gotopage("$PHP_SELF?c=plrbans_import");

// form fields ...
$formfields = array(
	'enabled'	=> array('label' => $ps_lang->trans("Enabled").':',	'val' => 'N', 'statustext' => $ps_lang->trans("Should this ban be enforced?")),
	'matchtype'	=> array('label' => $ps_lang->trans("Match Type").':',	'val' => 'B', 'statustext' => $ps_lang->trans("How is this BAN matched against players?")),
	'matchstr'	=> array('label' => $ps_lang->trans("Match String").':','val' => 'B', 'statustext' => $ps_lang->trans("Match string criteria. % can be used as a wildcard.")),
	'reason'	=> array('label' => $ps_lang->trans("Reason").':',	'val' => '',  'statustext' => $ps_lang->trans("Brief reason why the player was banned (optional).")),
);

if (!is_numeric($id)) $id = 0;
if (!is_numeric($start) || $start < 0) $start = 0;
if (!is_numeric($limit) || $limit < 0) $limit = 100;
if ($export) { $start = 0; $limit = 0; }
$sort = "matchstr";
$order = "asc";

// perform the action requested on the selected users ...
if (is_array($actionlist) and count($actionlist)) {
	for ($i=0; $i < count($actionlist); $i++) {
		// remove invalid elements
		if (!is_numeric($actionlist[$i])) unset($actionlist[$i]);
	}

	if (count($actionlist)) {
		if ($delete) {
			$count = 0;
			foreach ($actionlist as $bid) {
				$ok = $ps_db->delete($ps->t_config_plrbans, 'id', $bid);
				if ($ok) $count++;
			}
			$data['msg'] = "$count " . $ps_lang->trans("BANS deleted");
		} elseif ($enable or $disable) {
			$enabled = $enable ? 1 : 0;
			$ps_db->query("UPDATE $ps->t_config_plrbans SET enabled=$enabled WHERE id IN (" . join(',', $actionlist) . ")");
			$count = $ps_db->affected_rows();
			if ($count) {
				$data['msg'] = "$count " . $ps_lang->trans("BANS updated");
			}
		}
	}
}

$where = "";
if ($filter != '') {
	if ($where) $where .= " AND ";
	$where .= "matchstr LIKE '%" . $ps_db->escape($filter) . "%' OR ";
	$where .= "reason LIKE '%" . $ps_db->escape($filter) . "%' ";
}
if ($matchtype and in_array($matchtype, array('worldid','ipaddr','name'))) {
	if ($where) $where .= " AND ";
	$where .= "matchtype='$matchtype'";
}
if ($where) $where = "WHERE $where";

$cmd = "SELECT * FROM $ps->t_config_plrbans $where ";
$cmd .= $ps->_getsortorder(array('start' => $start, 'limit' => $limit, 'order' => $order, 'sort' => $sort));
$banlist = array();
$banlist = $ps_db->fetch_rows(1, $cmd);

$cmd = "SELECT count(*) FROM $ps->t_config_plrbans $where ";
list($filtertotal) = $ps_db->fetch_list($cmd);

# export the data and exit
if ($export) {
	// get the first item in the list so we can determine what keys are available
	$i = $banlist[0];
	unset($i['matchtype'],$i['matchstr'], $i['id']);// remove unwanted keys
	$keys = array_keys($i);				// get a list of the keys (no values)
	array_unshift($keys, 'matchtype', 'matchstr');	// make sure ... is always the first key

	$csv = csv($keys);				// 1st row is always the key order
	foreach ($banlist as $i) {
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
	header("Content-Disposition: attachment; filename=\"ps-plrbans.csv\"");
	print $csv;
	exit();
}

$plrban = array();
if ($id) {
	$plrban = $ps_db->fetch_row(1, "SELECT * FROM $ps->t_config_plrbans WHERE id='" . $ps_db->escape($id) . "'");
}

$form = array();
$errors = array();

if ($del and $plrban['id']) {
	$ps_db->delete($ps->t_config_plrbans, 'id', $plrban['id']);
	previouspage("admin.php?c=".urlencode($c));

} elseif ($submit and $act == 'edit' and $_SERVER['REQUEST_METHOD'] == 'POST') {
	$form = packform($formfields);
	trim_all($form);

	// automatically verify all fields
	foreach ($formfields as $key => $ignore) {
		form_checks($form[$key], $formfields[$key]);
	}

	$errors = all_form_errors($formfields);

	// check for duplicate
	if (!$plrban['id'] and !count($errors)) {
		list($exists) = $ps_db->fetch_item(sprintf("SELECT id FROM $ps->t_config_plrbans WHERE matchtype='%s' AND matchstr='%s' LIMIT 1", 
			$ps_db->escape($form['matchtype']), 
			$ps_db->escape($form['matchstr'])
		));
		if ($exists) {
			$errors['fatal'] = $ps_lang->trans("Duplicate BAN attempted");
			$formfields['matchstr']['error'] = $ps_lang->trans("Duplicate ban already exists");
		}
	}

	if (!count($errors)) {
		$set = $form;
		$set['bandate'] = time();
		if (!$id) {
			$set['id'] = $ps_db->next_id($ps->t_config_plrbans);
		}

		$ok = 0;
		$ps_db->begin();
		if ($id) {
			$ok = $ps_db->update($ps->t_config_plrbans, $set, 'id', $id);
		} else {
			$ok = $ps_db->insert($ps->t_config_plrbans, $set);
		}

		if ($ok) {
			$ps_db->commit();
			previouspage("admin.php?c=".urlencode($c));
		} else {
			$errors['fatal'] = $ps_db->errstr;
			$ps_db->rollback();
		}
	}
	$data += $form;	
	$data['adminpage'] = 'plrbans_edit';
} elseif ($act == 'edit') {
	$data += $plrban;
	$data['adminpage'] = 'plrbans_edit';
} elseif ($submit and $new) {
	$data['enabled'] = 1;
	$data['adminpage'] = 'plrbans_edit';
}

$data['pagerstr'] = pagination(array(
	'baseurl'	=> ps_url_wrapper(array('c' => $c, 'limit' => $limit, 'filter' => $filter)),
	'total'		=> $filtertotal,
	'start'		=> $start,
	'perpage'	=> $limit, 
	'pergroup'	=> 3,
	'prefix'	=> '',
	'next'		=> $ps_lang->trans("Next"),
	'prev'		=> $ps_lang->trans("Prev"),
));

$data['form'] = $formfields;
$data['errors'] = $errors;
$data['banlist'] = $banlist;
$data['plrban'] = $plrban;
$data['id'] = $id;
$data['act'] = $act;

?>
