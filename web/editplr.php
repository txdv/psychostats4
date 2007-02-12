<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . "/includes/common.php");
include(PS_ROOTDIR . "/includes/forms.php");

$minpwlen = 5;

$validfields = array('themefile','submit','cancel','ref','id');
globalize($validfields);

foreach ($validfields as $var) {
	$data[$var] = $$var;
}

if ($cancel) previouspage('index.php');
if (!user_logged_on()) gotopage("login.php?ref=" . urlencode($PHP_SELF . "?id=$id"));

// form fields ...
$formfields = array(
	'username'	=> array('label' => $ps_lang->trans("Username"). ':', 		'val' => '', 'statustext' => $ps_lang->trans("Changing your username is optional")),
	'accesslevel'	=> array('label' => $ps_lang->trans("Accesslevel"). ':', 		'val' => '', 'statustext' => $ps_lang->trans("Assign an accesslevel for this user")),
	'password'	=> array('label' => $ps_lang->trans("New Password") .':', 		'val' => '', 'statustext' => $ps_lang->trans("Changing your password is optional")),
	'password2'	=> array('label' => $ps_lang->trans("Repeat New Password") .':', 	'val' => '', 'statustext' => $ps_lang->trans("Retype your new password")),
	'oldpassword'	=> array('label' => $ps_lang->trans("Current Password") .':', 	'val' => '', 'statustext' => $ps_lang->trans("You must enter your current password to change it")),
	// use 'plrname' instead of 'name' so PsychoNuke works
	'plrname'	=> array('label' => $ps_lang->trans("Player Name"). ':', 		'val' => 'B', 'statustext' => $ps_lang->trans("Player name may change automatically unless you check the 'lock name' box")),
	'name'		=> array(							'val' => ''),
	'namelocked'	=> array('label' => $ps_lang->trans("Lock Name?"), 		'val' => '', 'statustext' => $ps_lang->trans("Locking your name will insure it does not get changed by stats updates")),
	'icon'		=> array('label' => $ps_lang->trans("Icon"). ':',	 		'val' => '', 'statustext' => $ps_lang->trans("Choose an interesting icon that represents your player")),
	'cc'		=> array('label' => $ps_lang->trans("Country"). ':',		'val' => '', 'statustext' => $ps_lang->trans("Choose your country")),
	'email'		=> array('label' => $ps_lang->trans("Email Address") .':', 	'val' => 'E', 'statustext' => $ps_lang->trans("An email address that other players can use to contact you")),
	'aim'		=> array('label' => $ps_lang->trans("AIM Screen name"). ':',	'val' => '', 'statustext' => $ps_lang->trans("AOL Instant Messenger (AIM) screen name")),
	'icq'		=> array('label' => $ps_lang->trans("ICQ Number"). ':',		'val' => '', 'statustext' => $ps_lang->trans("ICQ Number")),
	'msn'		=> array('label' => $ps_lang->trans("MSN Email Address"). ':',	'val' => '', 'statustext' => $ps_lang->trans("Microsoft MSN email address")),
	'website'	=> array('label' => $ps_lang->trans("Website"). ':',		'val' => '', 'statustext' => $ps_lang->trans("Enter your website if you have one")),
	'logo'		=> array('label' => $ps_lang->trans("Logo HTML"). ':',		'val' => '', 'statustext' => $ps_lang->trans("Your logo is displayed exactly as entered (HTML included)")),
);

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'editplr';
$data['PAGE'] = 'editplr';

$form = array();
$errors = array();

// no player id? default to current user (user might not have a plrid either)
if (empty($id)) $id = $ps_user['plrid'];
$edit_allowed = ($id == $ps_user['plrid'] || user_is_admin());
$require_oldpassword = ($id == $ps_user['plrid']);			// only require password if we are editing ourself
$allow_username_change = (user_is_admin() || $ps->conf['main']['allow_username_change']);
$allow_icon_upload = ($ps->conf['theme']['allow_icon_upload'] or user_is_admin());
$allow_icon_overwrite = ($ps->conf['theme']['allow_icon_overwrite'] or user_is_admin());

if (!$allow_username_change) {
	unset($formfields['username']);
}

// if the ID is still empty then we're most likely an admin with no player associated
if (empty($id)) {
	abort('nomatch', $ps_lang->trans("No player ID"), $ps_lang->trans("You must specify a player ID"));
}

// a non-numeric ID was given
if (!is_numeric($id)) {
	abort('nomatch', $ps_lang->trans("Invalid player ID"), $ps_lang->trans("An invalid player ID was specified"));
}

// Current user is not an admin and is trying to edit someone else
if (!$edit_allowed) {
	abort('nomatch', $ps_lang->trans("Access Denied!"), $ps_lang->trans("You do not have permission to edit other players"));
}

if (empty($id) or !is_numeric($id)) $id = $ps_user['plrid'];
if ($id == $ps_user['plrid']) {
	$theuser = $ps_user;
} else {
	$theuser = load_player($id);
}
if (!$theuser) {
	abort('nomatch', $ps_lang->trans("No Player Found!"), $ps_lang->trans("The player ID does not exist"));
}

if ($allow_icon_upload) {
	$allow_icon_upload = is_writable(catfile($ps->conf['theme']['rootimagesdir'], 'icons'));
}

$data['profile'] = $theuser;	// allow the form to reference the original profile information
$data['require_oldpassword'] = $require_oldpassword;
$data['allow_username_change'] = $allow_username_change;
$data['allow_icon_upload'] = $allow_icon_upload;
$data['allow_icon_overwrite'] = $allow_icon_overwrite;

// process submitted form
if ($submit and $_SERVER['REQUEST_METHOD'] == 'POST') {
	$form = packform($formfields);
	trim_all($form);
//	if (get_magic_quotes_gpc()) stripslashes_all($form);

	$form['cc'] = strtoupper($form['cc']);

	// do not allow accesslevel to change if we're not an admin or if we are editing ourself.
	if (!user_is_admin() or $theuser['userid'] == $ps_user['userid']) {
		unset($form['accesslevel']);
	}

	// is username being changed (and is it allowed)?
	if ($allow_username_change and $form['username'] != $theuser['username']) {
		if (empty($form['username'])) {
			$formfields['username']['error'] = $ps_lang->trans("Username can not be blank");
		} elseif (username_exists($form['username'])) {
			$formfields['username']['error'] = $ps_lang->trans("Username already exists");
		}
	} else {
		unset($form['username']);
	}

	// is password being changed?
	if (!empty($form['password'])) {
		if ($ps_user['plrid'] == $theuser['plrid']) {	// user is changing their own password
			if (md5($form['oldpassword']) != $theuser['password']) {
				$formfields['oldpassword']['error'] = $ps_lang->trans("Invalid password");
//				unset($form['oldpassword'],$form['password'], $form['password2']);
			} else {
				if ($form['password'] != $form['password2']) {
					$formfields['password']['error'] = $ps_lang->trans("Passwords do not match");
					$formfields['password2']['error'] = $ps_lang->trans("Please try again");
//					unset($form['oldpassword'],$form['password'], $form['password2']);
				}
			}
		} else {				// user (admin) is changing another user
			if ($form['password'] != $form['password2']) {
				$formfields['password']['error'] = $ps_lang->trans("Passwords do not match");
				$formfields['password2']['error'] = $ps_lang->trans("Please try again");
//				unset($form['oldpassword'],$form['password'], $form['password2']);
			}
		}

		if (!$formfields['password']['error'] and strlen($form['password']) < $minpwlen) {
			$formfields['password']['error'] = sprintf($ps_lang->trans("Password must be at least %d characters long"), $minpwlen);
			unset($form['oldpassword'],$form['password'], $form['password2']);
		}
	}

	// if we have no registered user yet make sure we have a username and password (if either was specified)
	if (!$theuser['userid'] and ($form['username'] != '' or $form['password'] != '')) {
		if (!$formfields['username']['error'] and $form['username'] == '') {
			$formfields['username']['error'] = $ps_lang->trans("You must create a username to register");
		}
		if (!$formfields['password']['error'] and $form['password'] == '') {
			$formfields['password']['error'] = $ps_lang->trans("You must create a password for the new user");
		}
	}

	// make sure 'website' variable has a protocol prefix
	if (!empty($form['website'])) {
		if (!preg_match('|^\w+://|', $form['website'])) {
			$form['website'] = "http://" . $form['website'];
		}
	}

	if (!empty($form['logo']) and strlen($form['logo']) > $ps->conf['theme']['format']['max_logo_size']) {
		$form['logo'] = substr($form['logo'], 0, $ps->conf['theme']['format']['max_logo_size']);
	}

	// automatically verify all fields
	foreach ($formfields as $key => $ignore) {
		form_checks($form[$key], $formfields[$key]);
	}

	$errors = all_form_errors($formfields);

	// If there are no errors act on the data given
	if (!count($errors)) {
		$set = $form;
		$set['logo'] = ps_strip_tags($set['logo']);
		$userset = array();
		$set['name'] = $set['plrname'];		// work around for 'PsychoNuke'
		unset($set['plrname']);
		if ($set['password'] != '') $userset['password'] = md5($form['password']);
		if ($set['username'] != '') $userset['username'] = $set['username'];
		if ($set['accesslevel'] != '') $userset['accesslevel'] = $set['accesslevel'];
		unset($set['username'], $set['password'], $set['password2'], $set['oldpassword'], $set['accesslevel']);

		// do not save user info if we have no username and this is a new user
		// otherwise you end up partially 'registering' the user (with no username or password)
		if (!$theuser['userid'] and !array_key_exists('username', $userset)) {
			$userset = array();
		}

		trimset($userset, $theuser);
		trimset($set, $theuser);

		$ok = $ok1 = $ok2 = 1;
		if (count($userset)) {
			if ($theuser['userid']) {
				$ok1 = update_user($userset, $theuser['userid']);
			} else {
				$userset['userid'] = next_user_id();
				if (user_is_admin()) $userset['confirmed'] = 1;
				if ($userset['accesslevel'] == '') $userset['accesslevel'] = ACL_USER;
				$ok1 = insert_user($userset, $theuser['userid']);
				if ($ok1) $set['userid'] = $userset['userid'];
			}
		} 
		if (count($set)) {
			$ok2 = $ps_db->update($ps->t_plr_profile, $set, 'uniqueid', $theuser['uniqueid']);
		}
		$ok = ($ok1 && $ok2);
		if ($ok) previouspage('index.php');
	}

	$data += $form;	

} else {		// init defaults, if any
	// pack all the variables together and merge them with the data
	$data += $theuser;
	$data['plrname'] = $data['name'];
}

$data['icons'] = load_icons(catfile($ps->conf['theme']['rootimagesdir'], 'icons'));
$data['countries'] = load_countries();
$data['form'] = $formfields;
$data['errors'] = $errors;
$smarty->assign($data);
$smarty->parse($themefile);
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . "/includes/footer.php");
?>
