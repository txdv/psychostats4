<?php 
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
define("PSYCHOSTATS_LOGIN_PAGE", true);
include("../includes/common.php");
include("./common.php");
$cms->theme->assign('page', basename(__FILE__, '.php'));

$validfields = array('submit','cancel','ref');
$cms->theme->assign_request_vars($validfields, true);

if ($cancel) {
	gotopage("../index.php");
} elseif ($cms->user->admin_logged_in()) {
	previouspage('index.php');
}

$bad_pw_error = $cms->trans('Invalid username or password');

$form = $cms->new_form();
$form->default_modifier('trim');
$form->default_validator('blank', $cms->trans("This field can not be blank"));
$form->field('username', 'user_exists');
$form->field('password');

if ($submit) {
	$form->validate();
	$input = $form->values();
	$valid = !$form->has_errors();
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	if ($valid) {
		// attempt to re-authenticate
		$id = $cms->user->auth($input['username'], $input['password']);
		if ($id) {
			// load the authenticated user and override the preivous user for this session
			if ($id != $cms->user->userid()) {
				$_u =& $cms->new_user();
				if (!$_u->load($id)) {
					$form->error('fatal', $cms->trans("Error retreiving user from database") . ":" . $_u->loaderr);
					$valid = false;
				} else {
					$cms->user =& $_u;
				}
			}

			if (!$cms->user->is_admin()) {
				$form->error('fatal', "Insufficient Privileges");
				$ps->errlog(sprintf("Failed admin login attempt for user '%s' (bad privs) from IP [%s]", $input['username'], remote_addr()));
				$valid = false;
			}
		} else { // auth failed
			$form->error('fatal', $bad_pw_error);
			$ps->errlog(sprintf("Failed admin login attempt for user '%s' (bad password) from IP [%s]", $input['username'], remote_addr()));
			$valid = false;
		}
	}

	// If authenetication was valid then we'll set the users admin flag and redirect to the previous page
	if ($valid and !$form->has_errors()) {
//		header("Cache-Control: no-cache, must-revalidate");
		// assign the session a new SID
		$cms->session->delete_session();
		$cms->session->sid($cms->session->generate_sid());
		$cms->session->send_cookie($cms->session->sid());
		$cms->session->key('');
		// enable the session admin flag
		$cms->session->is_admin(1);
		// make sure the user is actually marked online as well
		$cms->session->online_status(1, $cms->user->userid());
		previouspage('index.php');
	}
}

// save a new form key in the users session cookie
// this will also be put into a 'hidden' field in the form
if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());

// assign variables to the theme
$cms->theme->assign(array(
	'errors'	=> $form->errors(),
	'form'		=> $form->values(),
	'form_key'	=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
));

// display the output
$basename = basename(__FILE__, '.php');
$cms->theme->add_css('css/forms.css');
$cms->theme->add_js('js/forms.js');
$cms->theme->add_js('js/login.js');
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
