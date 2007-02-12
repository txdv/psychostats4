<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . "/includes/common.php");

$validfields = array('themefile');
globalize($validfields);

foreach ($validfields as $var) {
	$data[$var] = $$var;
}

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'logout';


if (user_logged_on()) {
//	header("Cache-Control: no-cache, must-revalidate");
	session_online_status(0);
}


$data['PAGE'] = 'logout';
$smarty->assign($data);
$smarty->parse($themefile);
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . '/includes/footer.php');
?>
