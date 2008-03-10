<?php
define("PSYCHOSTATS_PAGE", true);
include(dirname(__FILE__) . "/includes/common.php");
$cms->init_theme($ps->conf['main']['theme'], $ps->conf['theme']);
$ps->theme_setup($cms->theme);

// change this if you want the default sort of the player listing to be something else like 'kills'
$DEFAULT_SORT = 'kills';

$validfields = array('sort','order','start','limit','xml');
$cms->theme->assign_request_vars($validfields, true);

$sort = trim(strtolower($sort));
$order = trim(strtolower($order));
if (!preg_match('/^\w+$/', $sort)) $sort = $DEFAULT_SORT;
if (!in_array($order, array('asc','desc'))) $order = 'desc';
if (!is_numeric($start) || $start < 0) $start = 0;
if (!is_numeric($limit) || $limit < 0 || $limit > 500) $limit = 100;

$totalmaps = $ps->get_total_maps();
$maps = $ps->get_map_list(array(
	'sort'		=> $sort,
	'order'		=> $order,
	'start'		=> $start,
	'limit'		=> $limit,
));

$pager = pagination(array(
	'baseurl'	=> ps_url_wrapper(array( 'limit' => $limit, 'sort' => $sort, 'order' => $order )),
	'total'		=> $totalmaps,
	'start'		=> $start,
	'perpage'	=> $limit,
	'pergroup'	=> 5,
	'separator'	=> ' ', 
	'force_prev_next'=> true,
        'next'          => $cms->trans("Next"),
        'prev'          => $cms->trans("Previous"),
));

// build a dynamic table that plugins can use to add custom columns of data
$table = $cms->new_table($maps);
$table->if_no_data($cms->trans("No Maps Found"));
$table->attr('class', 'ps-table ps-map-table');
$table->sortable(true);
//$table->sort_baseurl(array( '_base' => 'maps.php' ));
$table->start_and_sort($start, $sort, $order);
$table->columns(array(
	'+'		=> '#',
	'_mapimg'	=> array( 'nolabel' => true, 'callback' => 'ps_table_map_link' ),
	'uniqueid'	=> array( 'label' => $cms->trans("Map"), 'callback' => 'ps_table_map_text_link' ),
	'kills'		=> array( 'label' => $cms->trans("Kills"), 'modifier' => 'commify' ), 
	'ffkills'	=> array( 'label' => $cms->trans("FF Kills"), 'tooltip' => $cms->trans('Friendly Fire Kills') ),
//	'headshotkills'	=> 'Headshots',
	'games'		=> $cms->trans("Games"),
	'rounds'	=> $cms->trans("Rounds"),
	'onlinetime'	=> array( 'label' => $cms->trans("Online"), 'modifier' => 'compacttime' ),
	'lasttime'	=> array( 'label' => $cms->trans("Last"), 'modifier' => 'ps_date_stamp' ),
));
$table->column_attr('uniqueid', 'class', 'left');
$table->header_attr('uniqueid', 'colspan', '2');
$table->column_attr('_mapimg', 'width', '40');
$ps->maps_table_mod($table);
$cms->filter('maps_table_object', $table);

// assign variables to the theme
$cms->theme->assign(array(
	'maps'		=> $maps,
	'maps_table'	=> $table->render(),
	'totalmaps'	=> $totalmaps,
	'pager'		=> $pager,
));

// display the output
$basename = basename(__FILE__, '.php');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');

?>
