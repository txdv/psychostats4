<?php 
define("VALID_PAGE", 1);
include(dirname(__FILE__) . "/includes/common.php");
include(PS_ROOTDIR . '/includes/forms.php');

$validfields = array('themefile','submit','cancel','ref');
globalize($validfields);

foreach ($validfields as $var) {
	$data[$var] = $$var;
}

if ($cancel) previouspage('index.php');

// form fields ...
$formfields = array(
	'username'	=> array('label' => $ps_lang->trans("Username"). ':', 		'val' => 'B', 'statustext' => $ps_lang->trans("Enter the username for your player account")),
	'password'	=> array('label' => $ps_lang->trans("Password") .':', 		'val' => 'B', 'statustext' => $ps_lang->trans("Enter your account password")),
	'autologin'	=> array('label' => $ps_lang->trans("Remember my login"), 		'val' => '',  'statustext' => $ps_lang->trans("Check this if you want to have your login remembered")),
);

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'login';

$form = array();
$errors = array();

$data['invalidlogin'] = '';

if (user_logged_on() and $ref) previouspage();

if (!user_logged_on() and $_POST['submit']) {
	$form = packform($formfields);
	trim_all($form);
	$invalid = 0;

	// manually verify fields that need it
	if (!username_exists($form['username'])) {
		if (!$invalid) $ps->errlog(sprintf("Failed login attempt for unknown user '%s' from IP [%s]", $form['username'], remote_addr()));
		$data['invalidlogin'] = $ps_lang->trans("Invalid username or password");
		$invalid = 1;
	}

	$u = load_user_only($form['username'], TRUE);
	if (!$invalid) {
		if ($u['password'] != md5($form['password'])) {
			$ps->errlog(sprintf("Failed login attempt for user '%s' (bad password) from IP [%s]", $form['username'], remote_addr()));
			$data['invalidlogin'] = $ps_lang->trans("Invalid username or password");
			$invalid = 1;
		}
		if (!$u['confirmed'] and !$invalid) {
			$data['invalidlogin'] = $ps_lang->trans("Your user has not been confirmed yet and can not login at this time.");
			$ps->errlog(sprintf("Failed login attempt for user '%s' (not confirmed) from IP [%s]", $form['username'], remote_addr()));
		}

		if (!user_has_access($u['accesslevel'], ACL_USER) and !$invalid) {
			$formfields['username']['error'] = $ps_lang->trans("Your user is not allowed to login at this time");
			$ps->errlog(sprintf("Failed login attempt for user '%s' (access denied) from IP [%s]", $form['username'], remote_addr()));
		}

	}

	$errors = all_form_errors($formfields);
	if (!count($errors) and !$invalid) {
//		header("Cache-Control: no-cache, must-revalidate");
		session_online_status(1, $u['userid']);
		if ($form['autologin']) session_save_autologin($u['userid'], $u['password']);
		previouspage('index.php');
	}

	$data += $form;	
}


$data['form'] = $formfields;
$data['errors'] = $errors;

$data['PAGE'] = 'login';
$smarty->assign($data);
$smarty->parse($themefile);
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . "/includes/footer.php");
?>
