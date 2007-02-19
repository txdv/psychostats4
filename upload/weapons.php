<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . "/includes/common.php");

$validfields = array('sort','order','start','limit','weaponview','themefile','xml');
globalize($validfields);

$weaponview = strtolower($weaponview);

$sort = strtolower($sort);
$order = strtolower($order);
if (!preg_match('/^\w+$/', $sort)) $sort = 'kills';
if (!in_array($order, array('asc','desc'))) $order = 'desc';
if (empty($weaponview) or $weaponview != 'tiles') $weaponview = "";
if (!is_numeric($start) || $start < 0) $start = 0;
if (!is_numeric($limit) || $limit < 0) $limit = 50;

$data['contentfile'] = "weapons_body.html";
if ($weaponview) $data['contentfile'] = "weapons_body_$weaponview.html";


foreach ($validfields as $var) {
	$data[$var] = $$var;
}

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'weapons';


$data['totalweapons'] = $ps->get_total_weapons(array(), $smarty);
$data['weapons'] = $ps->get_weapon_list(array(
	'sort'		=> $sort,
	'order'		=> $order,
	'start'		=> $start,
	'limit'		=> $limit,
), $smarty);
if ($xml) {
	$ary = array();
	foreach ($data['weapons'] as $w) {
		unset($w['dataid']);
		$ary[ $w['uniqueid'] ] = $w;
	} 
	print_xml($ary);
}

$data['pagerstr'] = pagination(array(
	'baseurl'	=> "$PHP_SELF?limit=$limit&sort=$sort&order=$order",
	'total'		=> $data['totalweapons'],
	'start'		=> $start,
	'perpage'	=> $limit,
	'prefix'	=> $ps_lang->trans("Goto:") . ' ',
        'next'          => $ps_lang->trans("Next"),
        'prev'          => $ps_lang->trans("Previous"),
));


$data['PAGE'] = 'weapons';
$smarty->assign($data);
$smarty->parse($themefile);
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . "/includes/footer.php");
?>
