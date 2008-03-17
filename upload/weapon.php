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
$cms->theme->page_title = 'PsychoStats - Weapon Stats';

// default sort for the weapons listing
$DEFAULT_SORT = 'kills';

$validfields = array('id','order','sort');
$cms->theme->assign_request_vars($validfields, true);

$limit = 25;
$sort = trim(strtolower($sort));
$order = trim(strtolower($order));
if (!preg_match('/^\w+$/', $sort)) $sort = $DEFAULT_SORT;
if (!in_array($order, array('asc','desc'))) $order = 'desc';

$totalweapons = $ps->get_total_weapons();
$weapons = $ps->get_weapon_list(array(
	'sort'		=> 'kills',
	'order'		=> 'desc',
	'start'		=> 0,
	'limit'		=> 100		// there's never more than about 25-30 weapons
));

// a weapon name was given; look up the ID for it
if (!is_numeric($id) and !empty($id)) {
	list($id) = $ps->db->fetch_list("SELECT weaponid FROM $ps->t_weapon WHERE uniqueid=" . $ps->db->escape($id, true));
}

$weapon = $ps->get_weapon(array(
	'weaponid' 	=> $id
));

$cms->theme->page_title .= ' for ' . $weapon['label'];

// calculate the hitbox zone percentages
$zone = array('head','chest','leftarm','rightarm','stomach','leftleg','rightleg');
$hits = $weapon['hits'] ? $weapon['hits'] : 0;
foreach ($zone as $z) {
	$weapon['shot_'.$z.'pct'] = $hits ? ceil($weapon['shot_'.$z] / $hits * 100) : 0;
}

// get top10 players .....
$players = array();
if ($weapon['weaponid']) {
	$players = $ps->get_weapon_player_list(array(
		'weaponid' 	=> $id,
		'sort'		=> $sort,
		'order'		=> $order,
		'limit'		=> $limit,
	));
}

// build a dynamic table that plugins can use to add custom columns of data
$table = $cms->new_table($players);
$table->if_no_data($cms->trans("No Players Found"));
$table->attr('class', 'ps-table ps-player-table');
$table->sort_baseurl(array( 'id' => $id ));
$table->start_and_sort(0, $sort, $order);
$table->columns(array(
	'+'			=> array( 'label' => '#' ),
	'name'			=> array( 'label' => $cms->trans("Player"), 'callback' => 'ps_table_plr_link' ),
	'kills'			=> array( 'label' => $cms->trans("Kills"), 'modifier' => 'commify' ),
	'deaths'		=> array( 'label' => $cms->trans("Deaths"), 'modifier' => 'commify' ),
	'headshotkills'		=> array( 'label' => $cms->trans("HS"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Headshot Kills") ),
	'headshotkillspct'	=> array( 'label' => $cms->trans("HS%"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Headshot Kills Percentage") ),
//	'ffkills'		=> array( 'label' => $cms->trans("FF"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Friendly Fire Kills") ),
//	'ffkillspct'		=> array( 'label' => $cms->trans("FF%"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Friendly Fire Kills Percentage") ),
	'accuracy'		=> array( 'label' => $cms->trans("Acc"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Accuracy") ),
	'shotsperkill' 		=> array( 'label' => $cms->trans("S:K"), 'tooltip' => $cms->trans("Shots Per Kill") ),
	'damage' 		=> array( 'label' => $cms->trans("Dmg"), 'callback' => 'dmg', 'tooltip' => $cms->trans("Damage") ),
));
$table->column_attr('name', 'class', 'left');
//$table->column_attr('+', 'class', 'first');
$ps->weapon_players_table_mod($table);
$cms->filter('players_table_object', $table); // same as index.php players table

$cms->theme->assign(array(
	'weapons'	=> $weapons,
	'weapon'	=> $weapon,
	'weaponimg'	=> $ps->weaponimg($weapon, array('path' => 'large', 'noimg' => '') ),
	'totalweapons'	=> $totalweapons,
	'players'	=> $players,
	'players_table'	=> $table->render(),
	'totalplayers'	=> count($players),
));

$basename = basename(__FILE__, '.php');
if ($weapon['weaponid']) {
	$cms->theme->add_css('css/2column.css');	// this page has a left column
	$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');
} else {
	$cms->full_page_err($basename, array(
		'message_title'	=> $cms->trans("No Weapon Found!"),
		'message'	=> $cms->trans("Invalid weapon ID specified.") . " " . $cms->trans("Please go back and try again.")
	));
}

function dmg($dmg) {
	return "<acronym title='" . commify($dmg) . "'>" . abbrnum0($dmg) . "</acronym>";
}

?>
