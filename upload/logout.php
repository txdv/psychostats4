<?php
define("PSYCHOSTATS_PAGE", true);
include(dirname(__FILE__) . "/includes/common.php");
$cms->init_theme($ps->conf['main']['theme'], $ps->conf['theme']);
$ps->theme_setup($cms->theme);

$validfields = array('ref');
$cms->theme->assign_request_vars($validfields, true);

if (!$cms->user->logged_in()) previouspage('index.php');

$cms->session->online_status(0);

// just redirect back to previous page
//previouspage('index.php');

// assign variables to the theme
$cms->theme->assign(array(
	// ...
));

// display the output
$basename = basename(__FILE__, '.php');
$cms->theme->add_css('css/forms.css');
$cms->theme->add_refresh($ref ? $ref : 'index.php');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');



?>
