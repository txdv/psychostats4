<?php
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
include("../includes/common.php");
include("./common.php");
$cms->theme->assign('page', 'plrbonuses');

$validfields = array('ref','start','limit','order','sort','move','id','filter','ajax');
$cms->theme->assign_request_vars($validfields, true);

if (!is_numeric($start) or $start < 0) $start = 0;
if (!is_numeric($limit) or $limit < 0) $limit = 25;
if (!in_array($order, array('asc','desc'))) $order = 'asc';
$sort = 'eventname';

$_order = array(
	'start'	=> $start,
	'limit'	=> $limit,
	'order' => $order, 
	'sort'	=> $sort
);

$where = '';
if ($filter) {
	$f = $ps->db->escape($filter, false);
	$where = "WHERE (gametype LIKE '%$f' OR modtype LIKE '%$f%' OR eventname LIKE '%$f%') ";
}

$list = $ps->db->fetch_rows(1, "SELECT * FROM $ps->t_config_plrbonuses $where" . $ps->getsortorder($_order));
$total = $ps->db->count($ps->t_config_plrbonuses, '*', $where ? substr($where,6) : null);	// remove 'WHERE' from str
$pager = pagination(array(
	'baseurl'	=> ps_url_wrapper(array('sort' => $sort, 'order' => $order, 'limit' => $limit, 'filter' => $filter)),
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
$cms->crumb('Bonuses', ps_url_wrapper(array('_base' => 'bonuses.php' )));

// massage the bonus list a bit so we don't have to do it in the smarty template
$bonuses = array();
foreach ($list as $b) {
	foreach (array('enactor', 'enactor_team', 'victim', 'victim_team') as $e) {
		if ($b[$e]) {
			$b[$e . '_color'] = ($b[$e] > 0) ? '#00cc00' : '#cc0000';
		} else {
			$b[$e . '_color'] = '#000000';
		}
	}
	$bonuses[] = $b;
}

// assign variables to the theme
$cms->theme->assign(array(
	'bonuses'	=> $bonuses,
	'pager'		=> $pager,
));

// display the output
$basename = basename(__FILE__, '.php');
$cms->theme->add_css('css/2column.css');
$cms->theme->add_css('css/forms.css');
//$cms->theme->add_js('js/bonuses.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
