<?php
/*
	Installation is DONE! Explain to the user what to do next.
	$Id$
	
*/
if (!defined("PSYCHOSTATS_INSTALL_PAGE")) die("Unauthorized access to " . basename(__FILE__));

$validfields = array('done');
$cms->theme->assign_request_vars($validfields, true);

if ($done) {
	$cms->session->delete_cookie('_opts');
	gotopage("../admin/logsources.php");
}

// make DB connection
load_db_opts();
$db->config(array(
	'dbtype' => $dbtype,
	'dbhost' => $dbhost,
	'dbport' => $dbport,
	'dbname' => $dbname,
	'dbuser' => $dbuser,
	'dbpass' => $dbpass,
	'dbtblprefix' => $dbtblprefix
));
$db->clear_errors();
$db->connect();

if (!$db->connected || !$db->dbexists($db->dbname)) {
	if ($ajax_request) {
		print "<script type='text/javascript'>window.location = 'go.php?s=db&re=1&install=" . urlencode($install) . "';</script>";
		exit;
	} else {
		gotopage("go.php?s=db&re=1&install=" . urlencode($install));
	}
}

// now that the DB connection should be valid, reinitialize, so we'll have full access to user and session objects
$cms->init();
$ps = PsychoStats::create(array( 'dbhandle' => &$db ));
$ps->theme_setup($cms->theme);


$cms->theme->assign(array(
));

if ($ajax_request) {
//	sleep(1);
	$pagename = 'go-done-results';
	$cms->tiny_page($pagename, $pagename);
}

?>
