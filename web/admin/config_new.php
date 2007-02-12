<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if ($register_admin_controls) {
	return 1;
}

if ($cancel) previouspage("admin.php?c=config");

$validfields = array('id','act');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

$validtypes = array( 'boolean', 'checkbox', 'select', 'text', 'textarea' );

$act = strtolower($act);

// form fields ...
$formfields = array(
	// conf settings
	'conftype'	=> array('label' => $ps_lang->trans("Config Type").':',	'val' => 'B', 'statustext' => $ps_lang->trans("Config Type specifies the when this option is loaded")),
	'section'	=> array('label' => $ps_lang->trans("Section").':',	'val' => '',  'statustext' => $ps_lang->trans("Optional sub-section for the config variable")),
	'var'		=> array('label' => $ps_lang->trans("Variable").':',	'val' => 'B', 'statustext' => $ps_lang->trans("Name of the config variable")),
	'value'		=> array('label' => $ps_lang->trans("Value").':',		'val' => '',  'statustext' => $ps_lang->trans("Initial value for the configuration option")),

	// layout settings
	'type'		=> array('label' => $ps_lang->trans("Input Type").':',	'val' => 'B', 'statustext' => $ps_lang->trans("What type of input should be used for this option?")),
	'options'	=> array('label' => $ps_lang->trans("Input Options").':',	'val' => '', 'statustext' => $ps_lang->trans("Optional settings for the input type (separated by commas)")),
	'verifycodes'	=> array('label' => $ps_lang->trans("Verify Codes").':',	'val' => '', 'statustext' => $ps_lang->trans("Verification codes to verify entered data is valid.")),
//	'multiple'	=> array('label' => $ps_lang->trans("Multiple Allowed?"),	'val' => 'B', 'statustext' => $ps_lang->trans("Can the variable have multiple entries in the config?")),
	'comment'	=> array('label' => $ps_lang->trans("Help Comment").':',	'val' => '', 'statustext' => $ps_lang->trans("Help comment that is displayed when editing configs.")),
);

if (!is_numeric($id)) $id = 0;

// load config based on ID
$conf = array();
if ($id) {
	$conf = $ps_db->fetch_row(1, 
		"SELECT c.*,cl.type,cl.id id2,cl.options,cl.verifycodes,cl.multiple,cl.locked,cl.comment FROM $ps->t_config c " .
		"LEFT JOIN $ps->t_config_layout cl USING (conftype,section,var)" . 
		"WHERE c.id='" . $ps_db->escape($id) . "'"
	);
	if (!$conf['id']) $id = 0;
}

# delete the config option
if ($submit and $act == 'del' and $id) {
	$ps_db->delete($ps->t_config, 'id', $id);
	if ($conf['id2']) $ps_db->delete($ps->t_config_layout, 'id', $conf['id2']);
	previouspage("admin.php?c=config&t=" . urlencode($conf['conftype']) . "&s=" . urlencode($conf['section']));

} elseif ($act == 'del' and $id) {
	$data['adminpage'] = 'config_del';
	$parts = array();
	$parts[] = $conf['conftype'];
	if ($conf['section']) $parts[] = $conf['section'];
	$parts[] = $conf['var'];
	$data['confvar'] = implode('.', $parts);
	$data['confval'] = $conf['value'];

} elseif ($submit and $_SERVER['REQUEST_METHOD'] == 'POST') {
	$form = packform($formfields);
	trim_all($form);

	// automatically verify all fields
	foreach ($formfields as $key => $ignore) {
		form_checks($form[$key], $formfields[$key]);
	}

	$errors = all_form_errors($formfields);

	if (!count($errors)) {
		$set1 = array();
		$set2 = array();
		if (!$id) {
			$set1['id'] = $ps_db->next_id($ps->t_config);
			$set1['idx'] = 1 + $ps_db->max($ps->t_config, 'idx', sprintf("conftype='%s' AND section='%s' AND var='%s'", 
				$ps_db->escape($form['conftype']), $ps_db->escape($form['section']), $ps_db->escape($form['var'])
			));
		}
		if (!$conf['id2']) {
			$set2['id'] = $ps_db->next_id($ps->t_config_layout);
		}
		$set1 += array(
			'conftype'	=> $form['conftype'],
			'section'	=> $form['section'],
			'var'		=> $form['var'],
			'value'		=> $form['value'],
		);
		$set2 += array(
			'conftype'	=> $form['conftype'],
			'section'	=> $form['section'],
			'var'		=> $form['var'],
			'type'		=> $form['type'],
			'options'	=> $form['options'],
			'verifycodes'	=> $form['verifycodes'],
//			'multiple'	=> $form['multiple'],
			'comment'	=> $form['comment'],
		);

		$ok = 0;
		$ps_db->begin();
		if ($id) {
			$ok = $ps_db->update($ps->t_config, $set1, 'id', $id);
		} else {
			$ok = $ps_db->insert($ps->t_config, $set1);
		}
		if ($ok) {
			if ($conf['id2']) {
				$ok = $ps_db->update($ps->t_config_layout, $set2, 'id', $conf['id2']);
			} else {
				$ok = $ps_db->insert($ps->t_config_layout, $set2);
			}
		}

		if ($ok) {
			$ps_db->commit();
			previouspage(sprintf("admin.php?c=config&t=%s&s=%s",
				urlencode($form['conftype']),
				urlencode($form['section'])
			));
		} else {
			$ps_db->rollback();
		}
	}
	$data += $form;	
} else {
	if ($id) {
		$data += $conf;
	} else {
		$data['conftype'] = $t;
		$data['section'] = $s;
	}
}

$data['validtypes'] = $validtypes;
$data['form'] = $formfields;
$data['id'] = $id;


?>
