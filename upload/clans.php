<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . "/includes/common.php");

$validfields = array('sort','order','start','limit','andor','search','themefile');
globalize($validfields);

$sort = strtolower($sort);
$order = strtolower($order);
if (!preg_match('/^\w+$/', $sort)) $sort = 'skill';
if (!in_array($order, array('asc','desc'))) $order = 'desc';
if (!is_numeric($start) || $start < 0) $start = 0;
if (!is_numeric($limit) || $limit < 0) $limit = 100;
if (!in_array(strtolower($andor), array('and','or','exact'))) $andor = 'or';
if ($search == '') $search = '';
$search = trim($search);

foreach ($validfields as $var) {
	$data[$var] = $$var;
}

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'clans';

$minmembers = 3;

$data['totalclans'] = $ps->get_total_clans(array('allowall' => 1), $smarty);
$data['rankedclans'] = $ps->get_total_clans(array('allowall' => 0), $smarty);

$data['clans'] = $ps->get_clan_list(array(
	'sort'		=> $sort,
	'order'		=> $order,
	'start'		=> $start,
	'limit'		=> $limit,
	'fields'	=> "kills,deaths,killsperdeath",
), $smarty);

$data['pagerstr'] = pagination(array(
	'baseurl'	=> "$PHP_SELF?limit=$limit&sort=$sort&order=$order",
	'total'		=> $data['rankedclans'],
	'start'		=> $start,
	'perpage'	=> $limit,
	'prefix'	=> $ps_lang->trans("Goto") . ': ',
        'next'          => $ps_lang->trans("Next"),
        'prev'          => $ps_lang->trans("Previous"),
));


$data['PAGE'] = 'clans';
$smarty->assign($data);
$smarty->parse($themefile);
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . "/includes/footer.php");
?>
