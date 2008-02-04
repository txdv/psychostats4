<?php
/**
	opt.php
	$Id$

	AJAX session options update script.
	This script is called via AJAX requests in the theme to update various options on the current session.
	Currently, this is only used to update which content shade boxes on a page are closed.
*/
define("PSYCHOSTATS_PAGE", true);
include(dirname(__FILE__) . "/includes/common.php");
#$cms->init_theme($ps->conf['main']['theme'], $ps->conf['theme']);
#$ps->theme_setup($cms->theme);

// collect url parameters ...
$validfields = array('shade','closed');
$cms->globalize_request_vars($validfields);

$opt = $cms->session->load_session_options();
// update a content shade box. Adding a shade to the session options means it's CLOSED
if ($shade) {
	$current = $opt['shades'] ? $opt['shades'] : array();
	if (!is_array($current)) $current = array();
	if (!is_array($shade)) $shade = explode(',',$shade);

	if ($closed) {
		foreach ($shade as $s) {
			$key = str_replace('-','_',$s);
			$current[$key] = 1;
		}
	} else {
		foreach ($shade as $s) {
			$key = str_replace('-','_',$s);
			unset($current[$key]);
		}
	}
	$opt['shades'] = $current;
	$cms->session->save_session_options($opt);
	print_r($opt);
}

?>
