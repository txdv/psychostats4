<?php
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
define("PSYCHOSTATS_LOGOUT_PAGE", true);
include("../includes/common.php");
include("./common.php");
$cms->theme->assign('page', basename(__FILE__, '.php'));

$validfields = array('ref');
$cms->theme->assign_request_vars($validfields, true);

// we don't want to actually log the user out of their session, just disable their ADMIN flag.
if ($cms->user->admin_logged_in()) {
	$cms->session->is_admin(0);
}
previouspage(dirname(dirname($_SERVER['SCRIPT_NAME'])));

// A page is never displayed for logout. Just redirect somewhere else.

// display the output
$basename = basename(__FILE__, '.php');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');

?>
