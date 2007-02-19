<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . "/includes/common.php");

$validfields = array('themefile','s');
globalize($validfields);

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'server';

$servers = array();
$servers = $ps_db->fetch_rows(1, 
	"SELECT *,INET_NTOA(serverip) serverip, CONCAT_WS(':', INET_NTOA(serverip),serverport) ipport " . 
	"FROM $ps->t_config_servers " . 
	"WHERE enabled=1 " . 
	"ORDER BY idx,serverip,serverport"
);
$data['servers'] = $servers;

$data['PAGE'] = 'server';
$smarty->assign($data);
$smarty->parse($themefile);
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . "/includes/footer.php");
?>
