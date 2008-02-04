<?php
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
include("../includes/common.php");
include("./common.php");

$validfields = array('ref','cancel','submit');
$cms->theme->assign_request_vars($validfields, true);

$message = '';
$cms->theme->assign_by_ref('message', $message);

if ($cancel) {
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'manage.php' )));
}

$form = $cms->new_form();
$form->default_modifier('trim');
$form->field('player_profiles');
$form->field('player_aliases');
$form->field('player_bans');
$form->field('clan_profiles');
$form->field('users');

// process the form if submitted
$valid = true;
$db_errors = array();
if ($submit) {
	$form->validate();
	$input = $form->values();
	$valid = !$form->has_errors();
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	if ($valid) {
		$ok = $ps->reset_stats($input);

		if ($ok !== true) {
			$db_errors = $ok;	// $ok is an array of errors
			$form->error('fatal', "Errors occured during database reset; Please see below");
		} else {
			$message = $cms->message('success', array(
				'message_title'	=> $cms->trans("Database was reset!"), 
				'message'	=> $cms->trans("The database has been reset. Stats will be empty until your next stats update."),
			));
//			previouspage(ps_url_wrapper('manage.php'));
		}
	}
} else {
	// default all options to keep
	$form->input(array(
		'player_profiles'	=> true,
		'player_aliases'	=> true,
		'player_bans'		=> true,
		'clan_profiles'		=> true,
		'users'			=> true
	));
}

$cms->crumb('Manage', ps_url_wrapper($_SERVER['REQUEST_URI']));
$cms->crumb('Reset Stats', ps_url_wrapper($PHP_SELF));

// save a new form key in the users session cookie
// this will also be put into a 'hidden' field in the form
if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());

// assign variables to the theme
$cms->theme->assign(array(
	'errors'	=> $form->errors(),
	'db_errors'	=> $db_errors,
	'form'		=> $form->values(),
	'form_key'	=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
	'page'		=> basename(__FILE__, '.php'), 
));

// display the output
$basename = basename(__FILE__, '.php');
$cms->theme->add_css('css/2column.css');
$cms->theme->add_css('css/forms.css');
//$cms->theme->add_js('js/jquery.interface.js');
$cms->theme->add_js('js/forms.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
