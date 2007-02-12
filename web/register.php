<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . "/includes/common.php");
include(PS_ROOTDIR . '/includes/forms.php');

$minpwlen = 6;

//$ps->conf['main']['uniqueid'] = 'name';
//$ps->conf['main']['registration'] = 'autoconfirm';

$validfields = array('themefile','submit','cancel','ref');
globalize($validfields);

foreach ($validfields as $var) {
	$data[$var] = $$var;
}

if ($cancel) previouspage('index.php');

// form fields ...
$formfields = array(
	'uniqueid'	=> array('label' => 'WORLDID:', 				'val' => 'B', 'statustext' => "", 'errortext' => "You must provide your STEAMID"),
	'username'	=> array('label' => $ps_lang->trans("Username"). ':', 		'val' => 'B', 'statustext' => $ps_lang->trans("Create a username for your account")),
	'password'	=> array('label' => $ps_lang->trans("Password") .':', 		'val' => 'B', 'statustext' => $ps_lang->trans("Create a password")),
	'password2'	=> array('label' => $ps_lang->trans("Repeat Password") .':', 	'val' => 'B', 'statustext' => $ps_lang->trans("Retype the same password")),
	'email'		=> array('label' => $ps_lang->trans("Email Address") .':', 	'val' => 'E', 'statustext' => $ps_lang->trans("Enter your email address")),
);

// change the label of the uniqueid to whatever is configured
switch ($ps->conf['main']['uniqueid']) {
	case "ipaddr":	
		$formfields['uniqueid']['label'] = $ps_lang->trans("IP Address") .':'; 
		$formfields['uniqueid']['statustext'] = $ps_lang->trans("Enter the current IP of your player"); 
		break;
	case "name":
		$formfields['uniqueid']['label'] = $ps_lang->trans("Player Name") .':'; 
		$formfields['uniqueid']['statustext'] = $ps_lang->trans("Enter the current name of your player"); 
		break;
	case "worldid":	
	case "steamid":	
	default:
		$formfields['uniqueid']['label'] = "STEAM ID:";
		$formfields['uniqueid']['statustext'] = $ps_lang->trans("Enter the Steam ID of your player"); 
}

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'register';

$form = array();
$errors = array();

$data['registration'] = 'pending';

// process submitted form
if ($submit and $_SERVER['REQUEST_METHOD'] == 'POST' and strtolower($ps->conf['main']['registration']) != 'closed') {
	$form = packform($formfields);
	trim_all($form);
//	if (get_magic_quotes_gpc()) stripslashes_all($form);

	$_uniqueid = $form['uniqueid'];
	if ($ps->conf['main']['uniqueid'] == 'ipaddr') {
		$_uniqueid = sprintf("%u", ip2long($_uniqueid));
	}

	// manually verify fields that need it
	if (!$form['uniqueid']) {
		$formfields['uniqueid']['error'] = $ps_lang->trans("You must provide a uniqueid");
	}

	if (!$form['username']) {
		$formfields['username']['error'] = $ps_lang->trans("You must provide a username");
	}

	if (!$form['password']) {
		$formfields['password']['error'] = $ps_lang->trans("You must create a password");
	}

	if (!$formfields['password']['error'] and ($form['password'] != $form['password2'])) {
		$formfields['password']['error'] = $ps_lang->trans("Passwords do not match");
		$formfields['password2']['error'] = $ps_lang->trans("Please try again");
	}
	if (!$formfields['password']['error'] and strlen($form['password']) < $minpwlen) {
		$formfields['password']['error'] = sprintf($ps_lang->trans("Password must be at least %d characters long"), $minpwlen);
	}

	// verify the uniqueid given DOES exist
	$already_registered = 0;
	if (!$formfields['uniqueid']['error']) {
		if (!$ps_db->exists($ps->t_plr_profile, 'uniqueid', $_uniqueid)) {
			$formfields['uniqueid']['error'] = $ps_lang->trans("Unique ID does not exist");
		} else {
			// verify this uniqueid doesn't already have a userid registered
			list($_id) = $ps_db->select_row($ps->t_plr_profile, 'userid', 'uniqueid', $_uniqueid);
			if ($_id) {
				$already_registered = 1;
				$formfields['uniqueid']['error'] = $ps_lang->trans("A user is already registered with this unique ID");
			}
		}
	}

	// if we're already registered there is no reason to continue checking for errors 
	if (!$already_registered) {
		// verify the username given does not already exist
		if (!$formfields['username']['error']) {
			if (username_exists($form['username'])) {
				$formfields['username']['error'] = $ps_lang->trans("Username already exists. Choose another name");
			}
		}

		// automatically verify all fields
		foreach ($formfields as $key => $ignore) {
			form_checks($form[$key], $formfields[$key]);
		}
	}

	// clear the errors and values for the fields listed (no sense in reporting them since this user was registered already)
	if ($already_registered) {
		foreach (array('username','password','password2') as $key) {
			unset($formfields[$key]['error']);
			$form[$key] = '';
		}
	}

	$errors = all_form_errors($formfields);

	// If there are no errors act on the data given
	if (!count($errors)) {
		$email = $form['email'];	// email is saved to profile table, not user table
		$set = $form;
		unset($set['email'], $set['password2'], $set['uniqueid']);
		$set['password'] = md5($form['password']);
		$set['accesslevel'] = ($ps->conf['main']['registration'] == 'open') ? ACL_USER : -1;
		$set['userid'] = $userid = next_user_id();

		$ps_db->begin();
		$ok = 1;
		if ($ok = insert_user($set)) {
			$profile = array('userid' => $userid);
			if ($email) $profile['email'] = $email;
			$ok = $ps_db->update($ps->t_plr_profile, $profile, 'uniqueid', $_uniqueid);
		}
		if (!$ok) {
			$ps_db->rollback();
			$data['registration'] = 'error';
		} else {
			$ps_db->commit();
			session_online_status(1, $userid);
			$data['user'] = $ps_user = load_user($userid);		// reload user information
			$data['registration'] = 'ok';
		}
	}

	$data += $form;	

} else {		// init defaults, if any
	if ($ps->conf['main']['uniqueid'] == 'ipaddr') {
		$uniqueid = remote_addr();
	}

	// pack all the variables together and merge them with the data
	$data += packform($formfields);
}

$data['form'] = $formfields;
$data['errors'] = $errors;

$data['PAGE'] = 'register';
$smarty->assign($data);
$smarty->parse($themefile);
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . '/includes/footer.php');
?>
