<?php
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
include("../includes/common.php");
include("./common.php");
$cms->theme->assign('page', 'users');

$validfields = array('ref','id','del','submit','cancel');
$cms->theme->assign_request_vars($validfields, true);

$message = '';
$cms->theme->assign_by_ref('message', $message);

if ($cancel) {
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'users.php' )));
}

// load the matching user if an ID was given
$u =& $cms->new_user();
if (is_numeric($id)) {
	if (!$u->load($id)) {
		$data = array( 'message' => $cms->trans("Invalid User ID Specified") );
		$cms->full_page_err(basename(__FILE__, '.php'), $data);
		exit();		
	}
} elseif (!empty($id)) {
	$data = array( 'message' => $cms->trans("Invalid User ID Specified") );
	$cms->full_page_err(basename(__FILE__, '.php'), $data);
	exit();		
}

// delete it, if asked to
if ($del and $id and $u->userid() == $id) {
	if (!$u->delete_user($id)) {
		$data = array( 'message' => $cms->trans("Error deleting user: " . $u->dberr()) );
		$cms->full_page_err(basename(__FILE__, '.php'), $data);
		exit();
	}
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'users.php' )));
}

// create the form variables
$form = $cms->new_form();
$form->default_modifier('trim');
$u->init_form($form);
$form->field('username','blank');
$form->field('password');
$form->field('password2');
$form->field('accesslevel');
$form->field('confirmed');

// process the form if submitted
$valid = true;
if ($submit) {
	$form->validate();
	$input = $form->values();
	$valid = !$form->has_errors();
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	// verify the passwords match if one was specified
	if (!$id and $input['password'] == '') {
		$form->error('password', $cms->trans("A password must be entered for new users"));
	} elseif ($input['password'] != '') {
		if ($input['password'] != $input['password2']) {
			$form->error('password', $cms->trans("Passwords do not match; please try again."));
			$form->error('password2', ' ');
		} else {
			$input['password'] = $u->hash($input['password']);
		}
	} else {
		unset($input['password']);
	}
	unset($input['password2']);

	if (!array_key_exists($input['accesslevel'], $u->accesslevels())) {
		$form->error('accesslevel', $cms->trans("Invalid access level specified"));
	}

	if (!$form->error('username')) {
		$_u = $u->load_user($input['username'], 'username');
		if ($_u and $_u['userid'] != $u->userid()) {
			$form->error('username', $cms->trans("Username already exists under a different user"));
		}
		unset($_u);
	}
	
	$valid = ($valid and !$form->has_errors());
	if ($valid) {
		$ok = false;
		if ($id) {
			$ok = $u->update_user($input, $id);
		} else {
			$input['userid'] = $u->next_userid();
			$ok = $u->insert_user($input);
		}
		if (!$ok) {
			$form->error('fatal', "Error updating database: " . $ps->db->errstr);
		} else {
			previouspage('users.php');
		}

	}

} else {
	// fill in defaults
	if ($id) {
		$form->input($u->to_form_input());
	} else {
		$form->set('accesslevel', $u->acl_user());
		$form->set('confirmed', 1);
	}
}

$cms->crumb('Manage', ps_url_wrapper('manage.php'));
$cms->crumb('Users', ps_url_wrapper('users.php'));
$cms->crumb('Edit');

// save a new form key in the users session cookie
// this will also be put into a 'hidden' field in the form
if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());

$cms->theme->assign(array(
	'errors'	=> $form->errors(),
	'u'		=> $u->to_form_input(),
	'accesslevels'	=> $u->accesslevels(),
	'form'		=> $form->values(),
	'form_key'	=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
));

// display the output
$basename = basename(__FILE__, '.php');
$cms->theme->add_css('css/forms.css');
//$cms->theme->add_js('js/jquery.interface.js');
$cms->theme->add_js('js/forms.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
