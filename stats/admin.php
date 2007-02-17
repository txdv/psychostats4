<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . '/includes/common.php');
include(PS_ROOTDIR . '/includes/forms.php');
include(PS_ROOTDIR . '/admin/_admin_shared.php');

$validfields = array('submit','cancel','c','themefile');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

if (empty($c)) $c = 'home';


if (!user_logged_on()) {
	gotopage("login.php?ref=" . urlencode($_SERVER['REQUEST_URI']));
}

if (!user_is_admin()) {
	abort('nomatch', $ps_lang->trans("Access Denied!"), $ps_lang->trans("You do not have administrative privileges"));
}

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'admin';
$data['PAGE'] = 'admin';

//if ($cancel) previouspage('admin.php');

// load all admin control extensions from the admin directory
$PSAdminMenu = new PSAdminMenu();
$controls = array();
$valid = array();
$admindir = PS_ROOTDIR . "/admin";
$dh = opendir($admindir);
$register_admin_controls = true;
while (($file = readdir($dh)) !== false) {
	if ($file{0} == '.') continue;			// ignore .files
	if ($file{0} == '_') continue;			// ignore files starting with underscore "_"
	if (is_link("$admindir/$file")) continue;	// ignore symlinks
	if (is_dir("$admindir/$file")) continue;	// ignore directories
	$m = array();
	if (!preg_match('|^([\w\d_]+)\.php$|', $file, $m)) continue;	// verify filename is a simple word (no spaces)
//	print "including $admindir/$file ... ";
	$valid[$m[1]] = include("$admindir/$file");
//	print "... done<br>";
}
$register_admin_controls = false;
$PSAdminMenu->sort();
$data['controls'] = $PSAdminMenu->sections;

// transfer request over to control ...
if ($valid[$c]) {
	$data['adminpage'] = $c;
	include("$admindir/$c.php");
} else {
	abort('admin_invalid', $ps_lang->trans("Invalid Request"), $ps_lang->trans("Invalid admin control specified"), 
		"<a href='admin.php'>" . $ps_lang->trans("Return to admin index") . "</a>");
}

$smarty->assign($data);
$smarty->parse($themefile);
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . '/includes/footer.php');

?>
