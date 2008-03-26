<?php 
/**
 *	This file is part of PsychoStats.
 *
 *	Written by Jason Morriss <stormtrooper@psychostats.com>
 *	Copyright 2008 Jason Morriss
 *
 *	PsychoStats is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	PsychoStats is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with PsychoStats.  If not, see <http://www.gnu.org/licenses/>.
 *
 *	Version: $Id$
 */
define("PSYCHOSTATS_PAGE", true);
include(dirname(__FILE__) . "/includes/common.php");
$cms->init_theme($ps->conf['main']['theme'], $ps->conf['theme']);
$ps->theme_setup($cms->theme);
$cms->theme->page_title('PsychoStats - Please Login');

$validfields = array('submit','cancel','ref');
$cms->theme->assign_request_vars($validfields, true);

if ($cancel or $cms->user->logged_in()) previouspage('index.php');

$bad_pw_error = $cms->trans('Invalid username or password');

$form = $cms->new_form();
$form->default_modifier('trim');
$form->default_validator('blank', $cms->trans("This field can not be blank"));
$form->field('username', 'user_exists');
$form->field('password');
//$form->field('autologin');	// use $cms->input['autologin'] instead

if ($submit) {
	$form->validate();
	$input = $form->values();
	$valid = !$form->has_errors();
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	$u = null;
	if ($valid) {
		// attempt to authenticate
		$id = $cms->user->auth($input['username'], $input['password']);
		if ($id) {
			// now load the user if possible
			$u = $cms->new_user();
			if (!$u->load($id)) {
				$form->error('fatal', $cms->trans("Error retreiving user from database") . ":" . $u->loaderr);
				$valid = false;
			}
		} else { // auth failed
			$form->error('fatal', $bad_pw_error);
			$ps->errlog(sprintf("Failed login attempt for user '%s' (bad password) from IP [%s]", $input['username'], remote_addr()));
			$valid = false;
		}
	}

	// verify the user's confirmation flag is enabled
	if ($valid and !$u->confirmed()) {
		$form->error('fatal', $cms->trans("This user has not been confirmed yet and can not login at this time."));
		$ps->errlog(sprintf("Failed login attempt for user '%s' (not confirmed) from IP [%s]", $input['username'], remote_addr()));
		$valid = false;
	}

	// verify the user has permissions to login
	if ($valid and !$u->has_access()) {
		$form->error('fatal', $cms->trans("User does not have permission to login"));
		$ps->errlog(sprintf("Failed login attempt for user '%s' (access denied) from IP [%s]", $input['username'], remote_addr()));
		$valid = false;
	}

	// If authenetication was valid then we'll set the users online flag and redirect to their previous page
	if (!$form->has_errors()) {
//		header("Cache-Control: no-cache, must-revalidate");
		$cms->session->online_status(1, $u->userid());
		if ($cms->input['autologin']) $cms->session->save_login($u->userid(), $u->password());
		if (!empty($_REQUEST['ref']) and strpos($_REQUEST['ref'], 'loggedin') === false) {
			$_REQUEST['ref'] .= strpos($_REQUEST['ref'], '?') === false ? '?' : '&';
			$_REQUEST['ref'] .= 'loggedin=1';
		}
		previouspage(ps_url_wrapper('index.php'));
	}
}

if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());

// assign variables to the theme
$cms->theme->assign(array(
	'errors'	=> $form->errors(),
	'form'		=> $form->values(),
	'form_key'	=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
));

// display the output
$basename = basename(__FILE__, '.php');
$cms->theme->add_js('js/forms.js');
$cms->theme->add_css('css/forms.css');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');

// validator functions --------------------------------------------------------------------------

function user_exists($var, $value, &$form) {
	global $cms, $ps, $bad_pw_error;
	if (!$cms->user->username_exists($value)) {
		$ps->errlog(sprintf("Failed login attempt for unknown user '%s' from IP [%s]", $value, remote_addr()));
		$form->error('fatal', $bad_pw_error);
		return false;
	}
	return true;
}

function password_match($var, $value, &$form) {
	global $valid, $cms, $ps;
	if (!empty($value)) {
		if ($value != $form->input['password2']) {
			$valid = false;
			$form->error($var, $cms->trans("Passwords do not match"));
		}
	}
	return $valid;
}

?>
