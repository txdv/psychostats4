<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if ($register_admin_controls) {
	$menu =& $PSAdminMenu->getSection( $ps_lang->trans("Configuration") );

	$opt =& $menu->newOption( $ps_lang->trans("Bonuses"), 'plrbonus' );
	$opt->link(ps_url_wrapper(array('c' => 'plrbonus')));

	return 1;
}

$data['PS_ADMIN_PAGE'] = "plrbonus";

if ($cancel) previouspage("admin.php?c=".urlencode($c));

$validfields = array('id','act','start','limit','order','sort','g','m','filter','new','del','export','import','msg');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

if ($import) gotopage("$PHP_SELF?c=plrbonus_import");

// form fields ...
$formfields = array(
	// act = 'edit'
	'gametype'	=> array('label' => $ps_lang->trans("Gametype").':',	'val' => '',  'statustext' => $ps_lang->trans("The gametype for the bonus (or blank for all)")),
	'modtype'	=> array('label' => $ps_lang->trans("Modtype").':',	'val' => '',  'statustext' => $ps_lang->trans("The modtype for the bonus (or blank for all mods of the gametype)")),
	'event'		=> array('label' => $ps_lang->trans("Event Name").':',	'val' => 'B', 'statustext' => $ps_lang->trans("Name of the event to trigger the bonus on")),
	'enactor'	=> array('label' => $ps_lang->trans("Enactor").':',	'val' => 'N', 'statustext' => $ps_lang->trans("Bonus for player who triggered the event")),
	'enactor_team'	=> array('label' => $ps_lang->trans("Enactor Team").':',	'val' => 'N', 'statustext' => $ps_lang->trans("Bonus for team mates of the enactor")),
	'victim'	=> array('label' => $ps_lang->trans("Victim").':',		'val' => 'N', 'statustext' => $ps_lang->trans("Bonus for player that is on the receiving end of the event")),
	'victim_team'	=> array('label' => $ps_lang->trans("Victim Team").':',	'val' => 'N', 'statustext' => $ps_lang->trans("Bonus for team mates of the victim")),
	'desc'		=> array('label' => $ps_lang->trans("Description").':',	'val' => '',  'statustext' => $ps_lang->trans("Short description of the event purpose")),
	
);

// load all current gametypes and modtypes
$gametypes = $ps_db->fetch_list("SELECT DISTINCT gametype FROM $ps->t_config_plrbonuses ORDER BY gametype");
$modtypes = $ps_db->fetch_list("SELECT DISTINCT modtype FROM $ps->t_config_plrbonuses ORDER BY modtype");

$act = strtolower($act);
if (!is_numeric($id)) $id = 0;
if (!is_numeric($start) or $start < 0) $start = 0;
if (!is_numeric($limit) or $limit == 0) $limit = 25;
$limit = 1000000;
//if (empty($order)) $order = 'event';
$order = 'event';
if (!in_array($sort, array('asc', 'desc'))) $sort = 'asc';
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
	$where .= "event LIKE '%" . $ps_db->escape($filter) . "%'";
}
if ($where) $where = "WHERE $where";

$cmd = "SELECT * FROM $ps->t_config_plrbonuses $where ORDER BY $order $sort";
if (!$export) $cmd .= " LIMIT $start,$limit";

$list = array();
$list = $ps_db->fetch_rows(1, $cmd);

# export the data and exit
if ($export) {
	// get the first item in the list so we can determine what keys are available
	$i = $list[0];
	unset($i['event'], $i['id']);			// remove unwanted keys
	$keys = array_keys($i);				// get a list of the keys (no values)
	array_unshift($keys, 'event');			// make sure event is always the first key

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
	header("Content-Disposition: attachment; filename=\"ps-bonuses.csv\"");
	print $csv;
	exit();
}

$bonus = array();
if ($id) {
	$bonus = $ps_db->fetch_row(1, "SELECT * FROM $ps->t_config_plrbonuses WHERE id='" . $ps_db->escape($id) . "'");
}

$form = array();
$errors = array();

if ($submit and $del and $bonus['id']) {
	$ps_db->delete($ps->t_config_plrbonuses, 'id', $bonus['id']);
	previouspage("admin.php?c=".urlencode($c));

} elseif ($submit and $act == 'edit' and $_SERVER['REQUEST_METHOD'] == 'POST') {
	$form = packform($formfields);
	trim_all($form);

	$form['gametype'] = strtolower($form['gametype']);
	$form['modtype'] = strtolower($form['modtype']);

	// do not allow duplicate events
	if (!$id and $ps_db->exists($ps->t_config_plrbonuses, 'event', $form['event'])) {
		$formfields['event']['error'] = $ps_lang->trans("Duplicate event name");
	}

	// automatically verify all fields
	foreach ($formfields as $key => $ignore) {
		form_checks($form[$key], $formfields[$key]);
	}

	$errors = all_form_errors($formfields);

	if (!count($errors)) {
		$set = $form;
		if (!$id) {
			$set['id'] = $ps_db->next_id($ps->t_config_plrbonuses);
		}

		$ok = 0;
		$ps_db->begin();
		if ($id) {
			$ok = $ps_db->update($ps->t_config_plrbonuses, $set, 'id', $id);
		} else {
			$ok = $ps_db->insert($ps->t_config_plrbonuses, $set);
		}

		if ($ok) {
			$ps_db->commit();
			previouspage("admin.php?c=".urlencode($c));
		} else {
			$ps_db->rollback();
		}
	}
	$data += $form;	
	$data['adminpage'] = 'plrbonus_edit';
} elseif ($act == 'edit') {
	$data += $bonus;
	$data['adminpage'] = 'plrbonus_edit';
} elseif ($submit and $new) {
	$data['adminpage'] = 'plrbonus_edit';
}

$data['form'] = $formfields;
$data['errors'] = $errors;
$data['bonuslist'] = $list;
$data['bonus'] = $bonus;
$data['id'] = $id;
$data['act'] = $act;
$data['gametypes'] = $gametypes;
$data['modtypes'] = $modtypes;


?>
