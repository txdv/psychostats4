<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

// register the control so it will appear on the admin menu
if ($register_admin_controls) {
	return 1;
}

$validfields = array('t');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

$data['PS_ADMIN_PAGE'] = "config_$t";

if ($cancel) previouspage(ps_url_wrapper(array(_amp => '&', 'c' => 'config', 't' => $t)));

// form fields ...
$formfields = array(
	'conftype'	=> array('label' => $ps_lang->trans("Config Type").':',			'val' => '', 'statustext' => $ps_lang->trans("Type of config to import (leave blank if the uploaded file has a \$TYPE set already).")),
	'conffile'	=> array('label' => $ps_lang->trans("Upload File").':',			'val' => '', 'statustext' => $ps_lang->trans("Plain text configuration file for import.")),
	'replacemulti'	=> array('label' => $ps_lang->trans("Replace Multi Options").':',	'val' => '', 'statustext' => $ps_lang->trans("Should 'multi' options we replaced with the entries in the uploaded config?")),
	'ignorenew'	=> array('label' => $ps_lang->trans("Ignore NEW Options").':',		'val' => '', 'statustext' => $ps_lang->trans("Should new or unknown options be ignored?")),
);

$conftypes = $ps_db->fetch_list("SELECT DISTINCT conftype FROM $ps->t_config ORDER BY conftype");

$form = array();
$errors = array();
$import_errors = array();

if ($submit and $_SERVER['REQUEST_METHOD'] = 'POST') {
	$form = packform($formfields);
	trim_all($form);

	// automatically verify all fields
	foreach ($formfields as $key => $ignore) {
		form_checks($form[$key], $formfields[$key]);
	}

	$errors = all_form_errors($formfields);

	$lines = array();
	if (!$errors) {
		$file = $_FILES['conffile'];
		if ($file['size'] and is_uploaded_file($file['tmp_name'])) {
			$lines = file($file['tmp_name']);
			if (!$lines) {
				$formfields['conffile']['error'] = $ps_lang->trans("Error processing uploaded file");
			}
		} else {
			$formfields['conffile']['error'] = $ps_lang->trans("Uploaded file is invalid");
		}
	}

	if (!$errors) {
		$import_errors = $ps->import_config($lines, $form['conftype'] ? $form['conftype'] : false, $form);
		if (!$import_errors) {
			previouspage(ps_url_wrapper(array(_amp => '&', 'c' => 'config', 't' => $t)));
		}
	}

} else {
	$data['replacemulti'] = 1;
	$data['ignorenew'] = 1;
}

$data['conftypes'] = $conftypes;
$data['form'] = $formfields;
$data['errors'] = $errors;
$data['import_errors'] = $import_errors;

?>
