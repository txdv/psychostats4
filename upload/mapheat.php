<?php
/**
 *	This file is part of PsychoStats.
 *
 *	Written by Jason Morriss <stormtrooper@psychostats.com>
 *	Copyright 2008 Jason Morriss
 *
 *	PsychoStats is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	PsychoStats is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with PsychoStats.  If not, see <http://www.gnu.org/licenses/>.
 *
 *	Version: $Id$
 */
define("PSYCHOSTATS_PAGE", true);
include(dirname(__FILE__) . "/includes/common.php");
include_once(PS_ROOTDIR . "/includes/PS/Heatmap.php");
$cms->init_theme($ps->conf['main']['theme'], $ps->conf['theme']);
$ps->theme_setup($cms->theme);
$cms->theme->page_title = 'PsychoStats - Heatmap';

$validfields = array('id', 'heatid');
$cms->theme->assign_request_vars($validfields, true);

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

$heatmap_list = array();
if ($map['mapid']) {
	$heat = new PS_Heatmap($ps);
	$heatmap_list = $heat->get_map_heatmaps($id);
	uasort($heatmap_list,'sort_heatmaps');

	if ($heatmap_list) {
		// default to the first heatmap type available
		if (!$heatid or !isset($heatmap_list[$heatid])) {
			reset($heatmap_list);
			$m = current($heatmap_list);
			$heatid = $m['heatid'];
		}
//		print_r($heatmap_list[$heatid]);
		$map['overlay'] = $ps->overlayimg($map['uniqueid']);
		$map['heatmap_images'] = $heat->get_heatmap_images($map['mapid'], $heatmap_list[$heatid]);
	}
}

$cms->theme->assign(array(
	'heatid'	=> $heatid,
	'maps'		=> $maps,
	'map'		=> $map,
	'mapimg'	=> $ps->mapimg($map, array( 'noimg' => '' )),
	'totalmaps'	=> $totalmaps,
	'heatmap_list'	=> $heatmap_list,
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

function sort_heatmaps($a,$b) {
	return strcasecmp($a['label'], $b['label']);
}

?>
