<?php
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_INSTALL_PAGE", true);
require_once("./common.php");

$opts = init_session_opts();

$validfields = array('a','s','s_back','next','back','conf','install','re');
$cms->theme->assign_request_vars($validfields, true);

$allowed_steps = array('analyze','update','db','dbinit','admin','theme','save','done');

if ($back and in_array($s_back, $allowed_steps)) {
	gotopage("go.php?s=$s_back&install=" . urlencode($install));
} else if (!in_array($s, $allowed_steps)) {
	gotopage('index.php');
}

$allow_next = true;
$ajax_request = (!empty($a)) ? true : false;

// verify our install key still matches this session
// if the install key from the form does not match what is in the option cookie
// then we know the user either opened a second install page, or went back 
// to the install index, which destroyed the previous cookie (and the DB settings)
if ($install != $opts['install']) {
	if ($ajax_request) {
		print "<script type='text/javascript'>window.location = 'index.php?re=1';</script>";
		exit;
	} else {
		gotopage("index.php?re=1");
	}
}

$pagename = basename(__FILE__, '.php');
$cms->theme->add_css('css/2column.css');
$cms->theme->add_js("js/go.js");
$cms->theme->add_js("js/go-$s.js");

$cms->theme->assign_by_ref('allow_next', $allow_next);
$cms->theme->assign_by_ref('dbhost', $dbhost);
$cms->theme->assign_by_ref('dbport', $dbport);
$cms->theme->assign_by_ref('dbname', $dbname);
$cms->theme->assign_by_ref('dbuser', $dbuser);
$cms->theme->assign_by_ref('dbpass', $dbpass);
$cms->theme->assign_by_ref('dbtblprefix', $dbtblprefix);
$cms->theme->assign(array(
	'step'		=> $s,
	'db_connected'	=> $db->connected,
	'is_windows'	=> (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN')
));

// allow custom code to handle our current progress/event
include(catfile(dirname(__FILE__), "go-$s.php")); 

// display the output
$cms->full_page($pagename, $pagename, $pagename.'_header', $pagename.'_footer');

?>
