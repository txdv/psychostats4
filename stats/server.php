<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . "/includes/common.php");
include(PS_ROOTDIR . "/includes/class_PQ.php");

$validfields = array('themefile','s');
globalize($validfields);

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'server';

$servers = array();
$ps->load_config('servers');
$servers = $ps->conf['servers'];

reset($servers);
while (list($id,$ary) = each($servers)) {
	if (!$ary['enabled']) unset($servers[$id]);
}

$data['servers'] = $servers;

$data['PAGE'] = 'server';
$smarty->assign($data);
$smarty->parse($themefile);
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . "/includes/footer.php");
?>
