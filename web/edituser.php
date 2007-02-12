<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . "/includes/common.php");
include(PS_ROOTDIR . '/includes/forms.php');

$minpwlen = 5;

$validfields = array('themefile','submit','cancel','ref','id','new');
globalize($validfields);

foreach ($validfields as $var) {
	$data[$var] = $$var;
}

if ($cancel) previouspage('index.php');
if (!user_logged_on()) gotopage("login.php?ref=" . urlencode($PHP_SELF . "?id=$id"));

// form fields ...
$formfields = array(
	'username'	=> array('label' => $ps_lang->trans("Username"). ':', 		'val' => 'B', 'statustext' => $ps_lang->trans("Changing your username is optional")),
	'accesslevel'	=> array('label' => $ps_lang->trans("Accesslevel"). ':', 		'val' => '',  'statustext' => $ps_lang->trans("Assign an accesslevel for this user")),
	'password'	=> array('label' => $ps_lang->trans("New Password") .':', 		'val' => '',  'statustext' => $ps_lang->trans("Changing your password is optional")),
	'password2'	=> array('label' => $ps_lang->trans("Repeat New Password") .':', 	'val' => '',  'statustext' => $ps_lang->trans("Retype your new password")),
	'oldpassword'	=> array('label' => $ps_lang->trans("Current Password") .':', 	'val' => '',  'statustext' => $ps_lang->trans("You must enter your current password to change it")),
);

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'edituser';
$data['PAGE'] = 'edituser';

$form = array();
$errors = array();

if ($new) $id = 0;

// no user id? default to current user
if (!$new and empty($id)) $id = $ps_user['userid'];
$edit_allowed = ($id == $ps_user['userid'] || user_is_admin());
$require_oldpassword = ($id == $ps_user['userid']);			// only require password if we are editing ourself
$allow_username_change = (user_is_admin() || $ps->conf['main']['allow_username_change']);

if (!$allow_username_change) {
	unset($formfields['username']);
}

// double check, still no userid? -- bail (this shouldn't happen)
if (!$new and empty($id)) {
	abort('nomatch', $ps_lang->trans("No user ID"), $ps_lang->trans("You must specify a user ID"));
}

// a non-numeric ID was given
if (!is_numeric($id)) {
	abort('nomatch', $ps_lang->trans("Invalid user ID"), $ps_lang->trans("An invalid user ID was specified"));
}

// Current user is not an admin and is trying to edit someone else
if (!$edit_allowed) {
	abort('nomatch', $ps_lang->trans("Access Denied!"), $ps_lang->trans("You do not have privilege to edit other users"));
}

//if (empty($id) or !is_numeric($id)) $id = $ps_user['userid'];
$theuser = array();
if ($id == $ps_user['userid']) {
	$theuser = $ps_user;
} elseif ($id) {
	$theuser = load_user($id);
}

if (!$new and !$theuser) {
	abort('nomatch', $ps_lang->trans("No User Found!"), $ps_lang->trans("The user ID does not exist"));
}

// process submitted form
if ($submit and $_SERVER['REQUEST_METHOD'] == 'POST') {
	$form = packform($formfields);
	trim_all($form);
//	if (get_magic_quotes_gpc()) stripslashes_all($form);

	// do not allow accesslevel to change if we're not an admin or if we are editing ourself.
	if (!$new) {
		if (!user_is_admin() or $theuser['userid'] == $ps_user['userid']) {
			unset($form['accesslevel']);
		}

		// is username being changed (and is it allowed)?
		if ($allow_username_change) {
			if ($form['username'] != $theuser['username']) {
				if (empty($form['username'])) {
					$formfields['username']['error'] = $ps_lang->trans("Username can not be blank");
				} elseif (username_exists($form['username'])) {
					$formfields['username']['error'] = $ps_lang->trans("Username already exists");
				}
			}
		} else {
//			unset($form['username']);
		}
	}

	// is password being changed?
	if (!$new) {
		if (!empty($form['password'])) {
			if ($ps_user['userid'] == $theuser['userid']) {	// user is changing their own password
				if (md5($form['oldpassword']) != $theuser['password']) {
					$formfields['oldpassword']['error'] = $ps_lang->trans("Invalid password");
//					unset($form['oldpassword'],$form['password'], $form['password2']);
				} else {
					if ($form['password'] != $form['password2']) {
						$formfields['password']['error'] = $ps_lang->trans("Passwords do not match");
						$formfields['password2']['error'] = $ps_lang->trans("Please try again");
//						unset($form['oldpassword'],$form['password'], $form['password2']);
					}
				}
			} else {				// user (admin) is changing another user
				if ($form['password'] != $form['password2']) {
					$formfields['password']['error'] = $ps_lang->trans("Passwords do not match");
					$formfields['password2']['error'] = $ps_lang->trans("Please try again");
//					unset($form['oldpassword'],$form['password'], $form['password2']);
				}
			}

			if (!$formfields['password']['error'] and strlen($form['password']) < $minpwlen) {
				$formfields['password']['error'] = sprintf($ps_lang->trans("Password must be at least %d characters long"), $minpwlen);
				unset($form['oldpassword'],$form['password'], $form['password2']);
			}
		} else {
			// unset the password otherwise it'll be saved as an empty value in the database
			unset($form['password'], $form['oldpassword']);
		}
	} else {
		if (username_exists($form['username'])) {
			$formfields['username']['error'] = $ps_lang->trans("Username already exists");
		}		

		if ($form['password'] != $form['password2']) {
			$formfields['password']['error'] = $ps_lang->trans("Passwords do not match");
			$formfields['password2']['error'] = $ps_lang->trans("Please try again");
		}
		if (!$formfields['password']['error'] and strlen($form['password']) < $minpwlen) {
			$formfields['password']['error'] = sprintf($ps_lang->trans("Password must be at least %d characters long"), $minpwlen);
			unset($form['oldpassword'],$form['password'], $form['password2']);
		}
	}

	// automatically verify all fields
	foreach ($formfields as $key => $ignore) {
		form_checks($form[$key], $formfields[$key]);
	}

	$errors = all_form_errors($formfields);

	// If there are no errors act on the data given
	if (!count($errors)) {
		$set = $form;
		if (!empty($set['password'])) $set['password'] = md5($form['password']);
		unset($set['password2'], $set['oldpassword']);

		trimset($set, $theuser);

		if ($new) {
			$set['userid'] = next_user_id();
			$set['confirmed'] = 1;
		}

		$ok = 1;
		if (count($set)) {
			if ($new) {
				$ok = insert_user($set);
			} else {
				$ok = update_user($set, $theuser['userid']);
			}
		} 
		if ($ok) {
			previouspage('index.php');
		} else {
			$errors['fatal'] = $ps_db->errstr;
		}
	}

	$data += $form;	

} else {		// init defaults, if any
	// pack all the variables together and merge them with the data
	$data += $theuser;
}

$data['profile'] = $theuser;	// allow the form to reference the original profile information
$data['require_oldpassword'] = $require_oldpassword;
$data['allow_username_change'] = $allow_username_change;

$data['icons'] = load_icons(catfile($ps->conf['theme']['rootimagesdir'], 'icons'));
$data['countries'] = load_countries();
$data['form'] = $formfields;
$data['errors'] = $errors;

$smarty->assign($data);
$smarty->parse($themefile);
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . '/includes/footer.php');
?>
