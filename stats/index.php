<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . '/includes/common.php');

/**
-- get breakdown of all CC's and how many players match each
select pp.cc,count(pp.cc) total,cn from ps_plr_profile pp, ps_geoip_cc c where c.cc=pp.cc group by pp.cc order by total DESC;

**/

$validfields = array('submit','show','sort','order','start','limit','andor','search','themefile','xml');
globalize($validfields);

$sort = trim(strtolower($sort));
$order = trim(strtolower($order));
$show = trim(strtolower($show));
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

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'index';

$data['search_urlencoded'] = urlencode($search);
$data['totalplayers'] = $ps->get_total_players(array('allowall' => 1), $smarty);
$data['rankedplayers'] = $ps->get_total_players(array('allowall' => 0), $smarty);
$totalresults = 0;

// if $submit is true a new 'search' was requested
if ($submit and $search != '') {
	$show = 'results';
	$data['totalplayersearch'] = $totalresults = $ps->search_players(array(
		'search' 	=> $search,
		'ranked' 	=> 1,
	));
} elseif ($show == 'results') {
	$res = $ps->get_search_results();
	$total = $res['results'] ? count(explode(',',$res['results'])) : 0;
	$data['totalplayersearch'] = $total;
} else {
	$show = '';
	$data['totalplayersearch'] = $data['rankedplayers'];
}


$data['players'] = $ps->get_player_list(array(
	'sort'		=> $sort,
	'order'		=> $order,
	'start'		=> $start,
	'limit'		=> $limit,
	'search'	=> $show == 'results' ? TRUE : FALSE,
	'joinclaninfo' 	=> 0,
), $smarty);
//print $ps->db->lastcmd;

// spit out XML string and exit
if ($xml) print_xml($data['players']);

// If we found an exact match from a player search we jump directly to their stats page
//if ($search && $limit > 1 && count($data['players']) == 1 && $data['players'][0]['plrid']) {
if ($search && count($data['players']) == 1 && $data['players'][0]['plrid']) {
	gotopage("player.php?id=" . $data['players'][0]['plrid']);
}

$data['pagerstr'] = pagination(array(
	'baseurl'	=> ps_url_wrapper(array('show' => $show, 'search' => $search, 'sort' => $sort, 'order' => $order, 'limit' => $limit)),
	'total'		=> $data['totalplayersearch'],
	'start'		=> $start,
	'perpage'	=> $limit, 
	'pergroup'	=> 5,
	'next'		=> $ps_lang->trans("Next"),
	'prev'		=> $ps_lang->trans("Previous"),
	'class'		=> 'menu',
));

$data['PAGE'] = 'index';
$smarty->assign($data);
$smarty->parse($themefile);
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . '/includes/footer.php');
?>
