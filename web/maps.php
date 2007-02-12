<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . "/includes/common.php");

$validfields = array('sort','order','start','limit','themefile');
globalize($validfields);

$sort = strtolower($sort);
$order = strtolower($order);
if (!preg_match('/^\w+$/', $sort)) $sort = 'kills';
if (!in_array($order, array('asc','desc'))) $order = 'desc';
if (!is_numeric($start) || $start < 0) $start = 0;
if (!is_numeric($limit) || $limit < 0) $limit = 50;



foreach ($validfields as $var) {
  $data[$var] = $$var;
}

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'maps';


$data['totalmaps'] = $ps->get_total_maps(array(), $smarty);
$data['maps'] = $ps->get_map_list(array(
	'sort'		=> $sort,
	'order'		=> $order,
	'start'		=> $start,
	'limit'		=> $limit,
), $smarty);

$data['pagerstr'] = pagination(array(
	'baseurl'	=> "$PHP_SELF?limit=$limit&sort=$sort&order=$order",
	'total'		=> $data['totalmaps'],
	'start'		=> $start,
	'perpage'	=> $limit,
	'prefix'	=> $ps_lang->trans("Goto:") . ' ',
        'next'          => $ps_lang->trans("Next"),
        'prev'          => $ps_lang->trans("Previous"),
));

$data['mapsbodyfile'] = $smarty->get_block_file('maps_body');

$data['PAGE'] = 'maps';
$smarty->assign($data);
$smarty->parse($themefile);
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . "/includes/footer.php");
?>
