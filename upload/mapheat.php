<?php
define("PSYCHOSTATS_PAGE", true);
include(dirname(__FILE__) . "/includes/common.php");
include_once(PS_ROOTDIR . "/includes/PS/Heatmap.php");
$cms->init_theme($ps->conf['main']['theme'], $ps->conf['theme']);
$ps->theme_setup($cms->theme);
$cms->theme->page_title = 'PsychoStats - Heatmap';

$validfields = array('id', 'sort', 'order', 'start', 'limit');
$cms->theme->assign_request_vars($validfields, true);

$heat = new PS_Heatmap($ps);
print_r($heat->get_map_heatmaps($id));

$sort = strtolower($sort);
$order = strtolower($order);
if (!preg_match('/^\w+$/', $sort)) $sort = 'kills';
if (!in_array($order, array('asc','desc'))) $order = 'desc';
if (!is_numeric($start) || $start < 0) $start = 0;
if (!is_numeric($limit) || $limit < 0) $limit = 10;

$totalmaps = $ps->get_total_maps();
$maps = $ps->get_map_list(array(
	'sort'		=> 'kills',
	'order'		=> 'desc',
	'start'		=> 0, //$start,
	'limit'		=> 50, //$limit,
));

// a map name was given; look up the ID for it
if (!is_numeric($id) and !empty($id)) {
	list($id) = $ps->db->fetch_list("SELECT mapid FROM $ps->t_map WHERE uniqueid=" . $ps->db->escape($id, true));
}

$map = $ps->get_map(array( 
	'mapid' => $id 
));

$cms->theme->page_title .= ' for ' . $map['uniqueid'];

if ($map['mapid']) {
	$map['overlay'] = '/overlays/' . $map['uniqueid'] . '_overlay.jpg';
}


$cms->theme->assign(array(
	'maps'		=> $maps,
	'map'		=> $map,
	'mapimg'	=> $ps->mapimg($map, array( 'noimg' => '' )),
	'totalmaps'	=> $totalmaps,
));

$basename = basename(__FILE__, '.php');
if ($map['mapid']) {
	// allow mods to have their own section on the left side bar
	$ps->map_left_column_mod($map, $cms->theme);

	$cms->theme->add_css('css/2column.css');	// this page has a left column
	$cms->theme->add_js('js/heatmap.js');
	$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');
} else {
	$cms->full_page_err($basename, array(
		'message_title'	=> $cms->trans("No Map Found!"),
		'message'	=> $cms->trans("Invalid map ID specified.") . " " . $cms->trans("Please go back and try again.")
	));
}

?>
