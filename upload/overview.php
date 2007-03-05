<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . "/includes/common.php");

$validfields = array('themefile','s');
globalize($validfields);

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'overview';

list($totalconn,$totaldays,$totalkills) = 
	$ps_db->fetch_list("SELECT SUM(connections), COUNT(distinct statdate), SUM(kills) FROM $ps->t_map_data");
list($totalplrs) = $ps_db->fetch_list("SELECT COUNT(*) FROM $ps->t_plr");
list($totalrankedplrs) = $ps_db->fetch_list("SELECT COUNT(*) FROM $ps->t_plr WHERE allowrank=1");

$data['totalconn'] = $totalconn;
$data['totaldays'] = $totaldays;
$data['connperday'] = $totaldays ? ($totaldays ? floor($totalconn / $totaldays) : 0) : '0.00';
$data['totalkills'] = $totalkills;
$data['totalplrs'] = $totalplrs;
$data['totalrankedplrs'] = $totalrankedplrs;
$data['rankedpct'] = $totalplrs ? sprintf("%0.0f", $totalrankedplrs / $totalplrs * 100) : '0.00';

$data['PAGE'] = 'overview';
$smarty->assign($data);
$smarty->parse($themefile);
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . "/includes/footer.php");
?>
