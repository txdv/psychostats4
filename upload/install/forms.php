<?php
	// Automated form validation functions ...
	// These functions are only useful for pages that need to validate form data from a user. 

if (defined("FILE_FORMS_PHP")) return 1;
define("FILE_FORMS_PHP", 1);

// build array using globals from $fields as the variable references
function packform($fields) {
//	print_r($fields);
	$form = array();
	if (is_array($fields)) {
		foreach ($fields as $f => $o) {
//			$form[$f] = $GLOBALS[$f];
			$form[$f] = $_REQUEST[$f];
		}
	}
	return $form;
}

// convert the form variables back into globals to be used throughout the page ...
// i don't generally use this anymore (and just keep form variables in an array instead of global
function unpackform($form) {
	if (!is_array($form)) return;
	foreach ($form as $f => $v) {
		$GLOBALS[$f] = $v;
	}
}

function all_form_errors($formfields) {
	$errors = array();
	foreach ($formfields as $key => $field) {
		if ($field['error']) $errors[$key] = $field['error'];
	}
	return $errors;
}

// checks for 'automatic' errors in form input
function form_checks($value, &$field) {
	if (strpos($field['val'], 'B') !== false) val_blank($value, $field);
	if (strpos($field['val'], 'D') !== false) val_date($value, $field);
	if (strpos($field['val'], 'E') !== false) val_email($value, $field);
	if (strpos($field['val'], 'N') !== false) val_number($value, $field);
	// several of my normal 'check' routines were removed since they are not needed in PsychoStats3 code
}

function val_email($value, &$field) {
  global $ps_lang;
  if ($field['error']) return 1;
  if ($value == '') return 1;			// do not error if the email is blank
  $pattern = '/^([a-zA-Z0-9])+([a-zA-Z0-9\._-])*@([a-zA-Z0-9_-])+([a-zA-Z0-9\._-]+)+$/';
  if (!preg_match($pattern, $value)) $field['error'] = "email address is not valid";
}

function val_number($value, &$field) {
  global $ps_lang;
  if ($field['error']) return 1;
  if ($value == '') return 1;
  if (!is_numeric($value)) $field['error'] = "must be a number";
}

// only works on dates in the format of: "YYYY-MM-DD"
function val_date($value, &$field) {
  global $ps_lang;
  if ($field['error']) return 1;
  $err = "invalid date specified";
//  list($mon,$day,$year) = explode('-',$value, 3);
  list($year,$mon,$day) = explode('-',$value, 3);
  if (!(preg_match("/^\d+$/", $mon) and $mon > 0 and $mon < 13)) $field['error'] = $err;
  if (!(preg_match("/^\d+$/", $day) and $day > 0 and $day < 32)) $field['error'] = $err;
  if (!(preg_match("/^\d+$/", $year) and $year > 1900 and $year < (date('Y')-10))) $field['error'] = $err;
}

function val_blank($value, &$field) {
  global $ps_lang;
  if ($field['error']) return 1;
  if ($value == '') $field['error'] = "can not be blank";
}

?>
