<?php
/*
	Callback script for page options. All this page does is send back a cookie to the browser 
	with some options defined. This allows pages to perform instant changes using javascript
	and w/o pages being reloaded. Which makes things a lot more interactive for users.

	The reason javascript isn't used to create the cookies is due to the complexity of the 
	options cookie being set. It's a serialized/encoded string of options which I can not do
	from javascript directly.
*/

define("VALID_PAGE", 1);
define("NOTHEME", 1);
define("NO_CONTENT_TYPE", 1);
define("NOTIMER", 1);
include(dirname(__FILE__) . "/includes/common.php");

$validfields = array('t','o','c');
globalize($validfields);

// save boxes that are CLOSED.
// I only save closed boxes to help keep the cookie size to a minimum.
// (I assume most people will have all/most boxes open most of the time)
if ($t == 'box') {
	$ps_user_opts_old = $ps_user_opts;
	if ($o) { 	// opened box names
		foreach (split(',',$o) as $_o) unset($ps_user_opts[$_o]);
	}
	if ($c) {	// closed box names
		foreach (split(',',$c) as $_c) $ps_user_opts[$_c] = 0;
	}

	// set the new options cookie
	session_save_user_opts($ps_user_opts);
	session_close();
} elseif ($t == 'compare') {
	$ps_user_opts['compare'] = array();
	foreach (split(',',$c) as $_c) if (is_numeric($_c) and $_c != 0) $ps_user_opts['compare'][] = (int)$_c;
	session_save_user_opts($ps_user_opts);
	session_close();
}


done();

// return a spacer image
function done() {
	@header("Content-Type: image/gif");
	print pack("H*", "47494638396101000100f00000000000ffffff21f90401000001002c00000000010001000002024c01003b");
	exit();
}

?>
