<?php
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
include("../includes/common.php");
include("./common.php");

$validfields = array('ref','start','limit','filter');
$cms->theme->assign_request_vars($validfields, true);

if (!is_numeric($start) or $start < 0) $start = 0;
if (!is_numeric($limit) or $limit < 0) $limit = 100;

$cmd = "SELECT * FROM $ps->t_plr_aliases";
if ($filter) {
	$f = $ps->db->escape($filter, false);
	$cmd .= " WHERE uniqueid LIKE '%$f%' OR alias LIKE '%$f%'";
}
$cmd .= " ORDER BY uniqueid ASC,alias ASC LIMIT $start,$limit";
$aliases = $ps->db->fetch_rows(1, $cmd);
$total = $ps->db->count($ps->t_plr_aliases);
$pager = pagination(array(
	'baseurl'	=> ps_url_wrapper(array('limit' => $limit, 'filter' => $filter)),
	'total'		=> $total,
	'start'		=> $start,
	'perpage'	=> $limit, 
	'pergroup'	=> 5,
	'separator'	=> ' ', 
	'force_prev_next' => true,
	'next'		=> $cms->trans("Next"),
	'prev'		=> $cms->trans("Previous"),
));

$cms->crumb('Manage', ps_url_wrapper($_SERVER['REQUEST_URI']));
$cms->crumb('Player Aliases', ps_url_wrapper($PHP_SELF));

// assign variables to the theme
$cms->theme->assign(array(
	'aliases'	=> $aliases,
	'total'		=> $total,
	'pager'		=> $pager,
	'page'		=> basename(__FILE__, '.php'), 
));

// display the output
$basename = basename(__FILE__, '.php');
$cms->theme->add_css('css/2column.css');
$cms->theme->add_css('css/forms.css');
//$cms->theme->add_js('js/jquery.interface.js');
//$cms->theme->add_js('js/forms.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
