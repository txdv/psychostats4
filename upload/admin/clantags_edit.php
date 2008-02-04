<?php
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
include("../includes/common.php");
include("./common.php");
$cms->theme->assign('page', 'clantags');

$validfields = array('ref','id','del','submit','cancel');
$cms->theme->assign_request_vars($validfields, true);

$message = '';
$cms->theme->assign_by_ref('message', $message);

if ($cancel) {
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'clantags.php' )));
}

// load the matching clantag if an ID was given
$clantag = array();
if (is_numeric($id)) {
	$clantag = $ps->db->fetch_row(1, "SELECT * FROM $ps->t_config_clantags WHERE id=" . $ps->db->escape($id));
	if (!$clantag['id']) {
		$data = array(
			'message' => $cms->trans("Invalid clantag ID Specified"),
		);
		$cms->full_page_err(basename(__FILE__, '.php'), $data);
		exit();		
	}
} elseif (!empty($id)) {
	$data = array(
		'message' => $cms->trans("Invalid clantag ID Specified"),
	);
	$cms->full_page_err(basename(__FILE__, '.php'), $data);
	exit();		
}

// delete it, if asked to
if ($del and $clantag['id'] == $id) {
	$ps->db->delete($ps->t_config_clantags, 'id', $id);
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'clantags.php' )));
}

// create the form variables
$form = $cms->new_form();
$form->default_modifier('trim');
$form->field('clantag','blank,val_regex');
$form->field('overridetag');
$form->field('pos');
$form->field('type');
$form->field('example');
//$form->field('idx');

if ($test and $clantag['id'] == $id) { 	// test the log source, if asked to
	$test = $form->values();
	$result = 'success';
	$msg = '';

	// verify the regex is valid
	// ...

	$message = $cms->message($result, array(
		'message_title'	=> $cms->trans("Testing Results"), 
		'message'	=> $msg
	));
	// don't let the form be submitted
	unset($submit);
}

// process the form if submitted
$valid = true;
if ($submit) {
	if ($form->input['type'] == 'plain') {
		$form->field('pos', 'blank');
		$form->field('clantag','blank');	// not a regex
	} else {
		$form->input['pos'] = '';
	}

	$form->validate();
	$input = $form->values();
	$valid = !$form->has_errors();
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	if ($valid) {
		$ok = false;
		if ($id) {
			$ok = $ps->db->update($ps->t_config_clantags, $input, 'id', $id);
		} else {
			$input['id'] = $ps->db->next_id($ps->t_config_clantags);
			$input['idx'] = $ps->db->max($ps->t_config_clantags, 'idx') + 10;		// last source
//			$input['idx'] = 0;							// first source
			$ok = $ps->db->insert($ps->t_config_clantags, $input);
		}
		if (!$ok) {
			$form->error('fatal', "Error updating database: " . $ps->db->errstr);
		} else {
			previouspage(ps_url_wrapper('clantags.php'));
		}
/*
		$message = $cms->message('success', array(
			'message_title'	=> $cms->trans("Update Successfull"),
			'message'	=> $cms->trans("Log Source has been updated"))
		));
*/

	}

} else {
	// fill in defaults
	if (!$test) {
		if ($id) {
			$form->input($clantag);
		}
	}
}

$cms->crumb('Manage', ps_url_wrapper('manage.php'));
$cms->crumb('Clan Tags', ps_url_wrapper('clantags.php'));
$cms->crumb('Edit');

// save a new form key in the users session cookie
// this will also be put into a 'hidden' field in the form
if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());

$cms->theme->assign(array(
	'errors'	=> $form->errors(),
	'clantag'	=> $clantag,
	'form'		=> $form->values(),
	'form_key'	=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
));

// display the output
$basename = basename(__FILE__, '.php');
$cms->theme->add_css('css/forms.css');
//$cms->theme->add_js('js/jquery.interface.js');
$cms->theme->add_js('js/clantags.js');
$cms->theme->add_js('js/forms.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

function val_regex($var, $value, &$form) {
	global $valid, $cms;
	if (!empty($value)) {
		if (@preg_match("/$value/", "this is a test") === false) {
			$valid = false;
			$form->error($var, $cms->trans("Invalid regex syntax; See http://php.net/pcre for details"));
		}
	}
	return $valid;
}

?>
