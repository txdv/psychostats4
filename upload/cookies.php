<?php
define("VALID_PAGE", 1);
define("NOTHEME", 1);
include(dirname(__FILE__) . "/includes/common.php");

$sess = session_sid();
$pre = session_sidprefix();
foreach ($_COOKIE as $k => $v) {
	if (substr($k,0,strlen($pre)) == $pre) {
		$suffix = substr($k, strlen($pre));
		session_cookie("", time()-100000, $suffix);
	}
}
session_delete($sess);
gotopage(ps_url_wrapper(array('_base' => 'index.php')));

include(PS_ROOTDIR . '/includes/footer.php');
?>
