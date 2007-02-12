<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if ($register_admin_controls) {
	$menu =& $PSAdminMenu->getSection( $ps_lang->trans("Configuration") );

	$opt =& $menu->newOption( $ps_lang->trans("Weapons"), 'weapons' );
	$opt->link(ps_url_wrapper(array('c' => 'weapons')));

	return 1;
}

$data['PS_ADMIN_PAGE'] = 'weapons';

if ($cancel) previouspage('admin.php?c=' . urlencode($c));

$validfields = array('filter','export','import','new','del','edit','id','act','msg');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

if ($import) gotopage("$PHP_SELF?c=weapons_import");

// form fields ...
$formfields = array(
	// act = 'edit'
	'uniqueid'	=> array('label' => $ps_lang->trans("Unique ID").':',	'val' => 'B',  'statustext' => $ps_lang->trans("Unique in-game ID of the weapon")),
	'name'		=> array('label' => $ps_lang->trans("Weapon Name").':',	'val' => '',   'statustext' => $ps_lang->trans("Real world name of the weapon")),
	'skillweight'	=> array('label' => $ps_lang->trans("Skill Weight").':',	'val' => 'N',  'statustext' => $ps_lang->trans("Skill modifier weight for making a kill with the weapon")),
	'class'		=> array('label' => $ps_lang->trans("Weapon Class").':',	'val' => '',   'statustext' => $ps_lang->trans("Weapon class (or type of weapon)")),
	
);

if (!is_numeric($id)) $id = 0;

$errors = array();
$success = "";

// load current weapons, including some stats (to help admins in assigning weights)
$list = array();
$list = $ps_db->fetch_rows(1,"SELECT * FROM $ps->t_weapon ORDER BY uniqueid");

# export the data and exit
if ($export) {
	// get the first item in the list so we can determine what keys are available
	$i = $list[0];
	unset($i['uniqueid'], $i['weaponid']);		// remove unwanted keys
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
	header("Content-Disposition: attachment; filename=\"ps-weapons.csv\"");
	print $csv;
	exit();
}

$weapon = array();
if ($id) {
	$weapon = $ps_db->fetch_row(1, "SELECT * FROM $ps->t_weapon WHERE weaponid='" . $ps_db->escape($id) . "'");
}

$form = array();
$errors = array();

if ($del and $weapon['weaponid']) {
	$ps_db->delete($ps->t_weapon, 'weaponid', $weapon['weaponid']);
	previouspage("admin.php?c=".urlencode($c));

} elseif ($submit and $act == 'edit' and $_SERVER['REQUEST_METHOD'] == 'POST') {
	$form = packform($formfields);
	trim_all($form);

	// do not allow duplicate uniqueids
	if (!$id and $ps_db->exists($ps->t_weapon, 'uniqueid', $form['uniqueid'])) {
		$formfields['uniqueid']['error'] = $ps_lang->trans("Duplicate uniqueid");
	}

	// automatically verify all fields
	foreach ($formfields as $key => $ignore) {
		form_checks($form[$key], $formfields[$key]);
	}

	$errors = all_form_errors($formfields);

	if (!count($errors)) {
		$set = $form;
		if (!$id) {
			$set['weaponid'] = $ps_db->next_id($ps->t_weapon,'weaponid');
		}

		$ok = 0;
		$ps_db->begin();
		if ($id) {
			$ok = $ps_db->update($ps->t_weapon, $set, 'weaponid', $id);
		} else {
			$ok = $ps_db->insert($ps->t_weapon, $set);
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
	$data['adminpage'] = 'weapons_edit';
} elseif ($act == 'edit') {
	$data += $weapon;
	$data['adminpage'] = 'weapons_edit';
} elseif ($submit and $new) {
	$data['adminpage'] = 'weapons_edit';
}

$data['form'] = $formfields;
$data['errors'] = $errors;
$data['id'] = $id;
$data['act'] = $act;
$data['weapon'] = $weapon;
$data['weaponlist'] = $list;
$data['errors'] = $errors;
$data['success'] = $success;
?>
