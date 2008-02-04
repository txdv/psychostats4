<?php
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
include("../includes/common.php");
include("./common.php");

$validfields = array('ref','start','limit','order','sort','filter','all','del');
$cms->theme->assign_request_vars($validfields, true);

$message = '';
$cms->theme->assign_by_ref('message', $message);

if (!is_numeric($start) or $start < 0) $start = 0;
if (!is_numeric($limit) or $limit < 0) $limit = 100;
if (!in_array($order, array('asc','desc'))) $order = 'asc';
if ($all == '') $all = false;
if (!in_array($sort, array('name','skill','username','allowrank'))) $sort = 'name';

$_order = array(
	'start'	=> $start,
	'limit'	=> $limit,
	'order' => $order, 
	'sort'	=> $sort,
	'filter'=> $filter,
	'allowall' => (bool)$all,
);

// delete selected players
if (is_array($del) and count($del)) {
	$total_deleted = 0;
	foreach ($del as $id) {
		if (is_numeric($id)) {
			if ($ps->delete_player($id)) {
				$total_deleted++;
			}
		}
	}	
	$message = $cms->message('success', array(
		'message_title'	=> $cms->trans("Players Deleted!"),
		'message'	=> sprintf($cms->trans("%d players were deleted successfully"), $total_deleted),
	));
}

$players = $ps->get_basic_player_list($_order);
$total = $ps->get_total_players($_order);
$pager = pagination(array(
	'baseurl'	=> ps_url_wrapper(array('sort' => $sort, 'order' => $order, 'limit' => $limit, 'filter' => $filter, 'all' => $all ? 1 : 0)),
	'total'		=> $total,
	'start'		=> $start,
	'perpage'	=> $limit, 
	'pergroup'	=> 5,
	'separator'	=> ' ', 
	'force_prev_next' => true,
	'next'		=> $cms->trans("Next"),
	'prev'		=> $cms->trans("Previous"),
));

$cms->crumb('Manage', ps_url_wrapper(array('_base' => 'manage.php' )));
$cms->crumb('Players', ps_url_wrapper(array('_base' => $PHP_SELF )));


// assign variables to the theme
$cms->theme->assign(array(
	'page'		=> basename(__FILE__, '.php'), 
	'players'	=> $players,
	'pager'		=> $pager,
));

// display the output
$basename = basename(__FILE__, '.php');
$cms->theme->add_css('css/2column.css');
$cms->theme->add_css('css/forms.css');
$cms->theme->add_js('js/players.js');
$cms->theme->add_js('js/message.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
