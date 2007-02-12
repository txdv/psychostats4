<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if ($register_admin_controls) {
	$menu =& $PSAdminMenu->getSection( $ps_lang->trans("Configuration") );

	$opt =& $menu->newOption( $ps_lang->trans("Clantags"), 'clantags' );
	$opt->link(ps_url_wrapper(array('c' => 'clantags')));

	return 1;
}

$data['PS_ADMIN_PAGE'] = "clantags";

if ($cancel) previouspage("admin.php?c=".urlencode($c));

$validfields = array('s','id','move','act','new','del','export','import','msg');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

if ($import) gotopage("$PHP_SELF?c={$c}_import");

// form fields ...
$formfields = array(
	// act = 'edit'
	'idx'		=> array('label' => $ps_lang->trans("Order").':',		'val' => 'N', 'statustext' => $ps_lang->trans("Scanning order of clantag")),
	'clantag'	=> array('label' => $ps_lang->trans("Clantag").':',	'val' => 'B',  'statustext' => $ps_lang->trans("Clantag definition")),
	'overridetag'	=> array('label' => $ps_lang->trans("Override Tag").':',	'val' => '',   'statustext' => $ps_lang->trans("Override automatic clantag match with the tag specified")),
	'pos'		=> array('label' => $ps_lang->trans("Position").':',	'val' => 'B',  'statustext' => $ps_lang->trans("Position of plain clantags (not used for regex clantags)")),
	'type'		=> array('label' => $ps_lang->trans("Type").':',		'val' => 'B',  'statustext' => $ps_lang->trans("Type of clantag")),
	'example'	=> array('label' => $ps_lang->trans("Example").':',	'val' => 'B',  'statustext' => $ps_lang->trans("Example of what the clantag would match")),	
);

$sections = array(
	array(
		'label'	=> 'plain',
		'comment' => 'Plain clantag definitions',
	),
	array(
		'label'	=> 'regex',
		'comment' => 'Regular expression clantag definitions',
	)
);

$act = strtolower($act);
$move = strtolower($move);
if (!is_numeric($id)) $id = 0;
if (!is_numeric($move)) $move = 0;

$plainlist = array();
$regexlist = array();
$plainlist = $ps_db->fetch_rows(1, "SELECT * FROM $ps->t_config_clantags WHERE type='plain' ORDER BY idx");
$regexlist = $ps_db->fetch_rows(1, "SELECT * FROM $ps->t_config_clantags WHERE type='regex' ORDER BY idx");

if (empty($s)) {
	$s =  'plain';
	if (!count($plainlist) and count($regexlist)) {
		$s = 'regex';
	}
}

# export the data and exit
if ($export) {
	$list = array_merge($plainlist, $regexlist);
	// get the first item in the list so we can determine what keys are available
	$i = $list[0];
	unset($i['clantag'], $i['id']);			// remove unwanted keys
	$keys = array_keys($i);				// get a list of the keys (no values)
	array_unshift($keys, 'clantag');		// make sure event is always the first key

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
	header("Content-Disposition: attachment; filename=\"ps-clantags.csv\"");
	print $csv;
	exit();
}

$ct = array();
if ($id) {
	$ct = $ps_db->fetch_row(1, "SELECT * FROM $ps->t_config_clantags WHERE id='" . $ps_db->escape($id) . "'");
}

# re-order the clantag list
if ($move and $ct['id']) {
	$list = ($ct['type'] == 'plain') ? $plainlist : $regexlist;
	$move = ($move > 0) ? 15 : -15;	

	$idx = 0;
	foreach ($list as $i => $a) {
		$list[$i]['idx'] = ++$idx * 10;
		if ($list[$i]['id'] == $id) $list[$i]['idx'] += $move;
	}
	usort($list, 'sortidx');

	$idx = 0;
	$ps_db->begin();
	foreach ($list as $i) {
		$ps_db->update($ps->t_config_clantags, array('idx' => ++$idx), 'id', $i['id']);
	}
	$ps_db->commit();

	# update the array in memory so things sort correctly on the webpage
	if ($ct['type'] == 'plain') {
		$plainlist = $list;
	} else {
		$regexlist = $list;
	}
}



$form = array();
$errors = array();

if ($submit and $del and $ct['id']) {
	$ps_db->delete($ps->t_config_clantags, 'id', $ct['id']);
	previouspage(sprintf("$PHP_SELF?c=%s&s=%s", urlencode($c), urlencode($ct['type'])));

} elseif ($submit and $act == 'edit' and $_SERVER['REQUEST_METHOD'] == 'POST') {
	$form = packform($formfields);
	trim_all($form);

	// automatically verify all fields
	foreach ($formfields as $key => $ignore) {
		form_checks($form[$key], $formfields[$key]);
	}

	$errors = all_form_errors($formfields);

	if (!count($errors)) {
		$set = $form;
		if (!$id) {
			$set['id'] = $ps_db->next_id($ps->t_config_clantags);
		}

		$ok = 0;
		$ps_db->begin();
		if ($id) {
			$ok = $ps_db->update($ps->t_config_clantags, $set, 'id', $id);
		} else {
			$ok = $ps_db->insert($ps->t_config_clantags, $set);
		}

		if ($ok) {
			$ps_db->commit();
			previouspage(sprintf("$PHP_SELF?c=%s&s=%s", urlencode($c), urlencode($set['type'])));
		} else {
			$ps_db->rollback();
		}
	}
	$data += $form;	
	$data['adminpage'] = 'clantags_edit';
} elseif ($act == 'edit') {
	$data += $ct;
	$data['adminpage'] = 'clantags_edit';
} elseif ($submit and $new) {
	$data['adminpage'] = 'clantags_edit';
}

$data['form'] = $formfields;
$data['errors'] = $errors;
$data['plainlist'] = $plainlist;
$data['regexlist'] = $regexlist;
$data['tag'] = $ct;
$data['id'] = $id;
$data['act'] = $act;
$data['s'] = $s;
$data['sections'] = $sections;



?>

