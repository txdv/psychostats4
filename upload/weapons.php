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
$cms->init_theme($ps->conf['main']['theme'], $ps->conf['theme']);
$ps->theme_setup($cms->theme);
$cms->theme->page_title('PsychoStats - Weapon Usage');

// change this if you want the default sort of the player listing to be something else like 'kills'
$DEFAULT_SORT = 'kills';

$validfields = array('sort','order','xml','v');
$cms->theme->assign_request_vars($validfields, true);

$v = strtolower($v);
$sort = trim(strtolower($sort));
$order = trim(strtolower($order));
$start = 0;
$limit = 100;
if (!preg_match('/^\w+$/', $sort)) $sort = $DEFAULT_SORT;
if (!in_array($order, array('asc','desc'))) $order = 'desc';

$stats = $ps->get_sum(array('kills','damage'), $ps->c_plr_data);

$weapons = $ps->get_weapon_list(array(
	'sort'		=> $sort,
	'order'		=> $order,
	'start'		=> $start,
	'limit'		=> $limit,
));
$totalweapons = count($weapons);

// calculate some extra percentages for each weapon and determine max values
$max = array();
$keys = array('kills', 'damage', 'headshotkills');
for ($i=0; $i < count($weapons); $i++) {
	foreach ($keys as $k) {
		if ($stats[$k]) {
			$weapons[$i][$k.'pct'] = ($stats[$k]) ? ceil($weapons[$i][$k] / $stats[$k] * 100) : 0;
		}
		if ($weapons[$i][$k] > $max[$k]) $max[$k] = $weapons[$i][$k];
	}
}
// calculate scale width of pct's based on max
$scale = 200;
$ofs   = $scale; // + 40;
for ($i=0; $i < count($weapons); $i++) {
	foreach ($keys as $k) {
		if ($max[$k] == 0) {
			$weapons[$i][$k.'width'] = $ofs - ceil($weapons[$i][$k] / 1 * $scale);
		} else {
			$weapons[$i][$k.'width'] = $ofs - ceil($weapons[$i][$k] / $max[$k] * $scale);
		}
	}
}

if ($xml) {
	$ary = array();
	foreach ($weapons as $w) {
		unset($w['dataid']);
		$ary[ $w['uniqueid'] ] = $w;
	} 
	print_xml($ary);
}

// organize the weapons by 'class'
$weaponclasses = array();
foreach ($weapons as $w) {
	$class = !empty($w['class']) ? $w['class'] : $cms->trans('Unclassified');
	$weaponclasses[$class][] = $w;
}
ksort($weaponclasses);


// build a dynamic table that plugins can use to add custom columns of data
$table = $cms->new_table($weapons);
$table->if_no_data($cms->trans("No Weapons Found"));
$table->attr('class', 'ps-table ps-weapon-table');
$table->start_and_sort($start, $sort, $order);
$table->columns(array(
	'uniqueid'		=> array( 'label' => $cms->trans("Weapon"), 'callback' => 'ps_table_weapon_link' ),
	'kills'			=> array( 'label' => $cms->trans("Kills"), 'modifier' => 'commify' ),
	'headshotkills'		=> array( 'label' => $cms->trans("HS"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Headshot Kills") ),
	'headshotkillspct'	=> array( 'label' => $cms->trans("HS%"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Headshot Kills Percentage") ),
	'ffkills'		=> array( 'label' => $cms->trans("FF"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Friendly Fire Kills") ),
	'ffkillspct'		=> array( 'label' => $cms->trans("FF%"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Friendly Fire Kills Percentage") ),
	'accuracy'		=> array( 'label' => $cms->trans("Acc"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Accuracy") ),
	'shotsperkill' 		=> array( 'label' => $cms->trans("S:K"), 'tooltip' => $cms->trans("Shots Per Kill") ),
	'damage' 		=> array( 'label' => $cms->trans("Dmg"), 'modifier' => 'abbrnum0', 'tooltip' => $cms->trans("Damage") ),
));
$table->column_attr('uniqueid', 'class', 'first');
$ps->weapons_table_mod($table);
$cms->filter('weapons_table_object', $table);

// assign variables to the theme
$cms->theme->assign(array(
	'weapons_by_class'	=> $weaponclasses,	// allow a theme to use either ...
	'weapons'		=> $weapons,		// ... way to display weapons
	'weapons_table'		=> $table->render(),
	'weapon_classes'	=> array_keys($weaponclasses),
	'totalweapons'		=> $totalweapons,
	'totalkills'		=> $stats['kills'],
	'totaldamage'		=> $stats['damage'],
));

// display the output
$basename = basename(__FILE__, '.php');
//$cms->theme->add_css('css/tabs.css');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');

?>
