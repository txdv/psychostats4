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

/**
 *	PsychoStats Heatmap class
 *
 *	PsychoStats heatmaps show spatial information related to where players are on a map when they do something.
 *	A "Death Map" is the most common usage to show areas where people are killed the most on a map.
 *
 *	@package PsychoStats
 */
if (defined("CLASS_PS_HEATMAP_PHP")) return 1;
define("CLASS_PS_HEATMAP_PHP", 1);

class PS_Heatmap {
var $ps = null;

// $ps is an PS object
function PS_Heatmap(&$ps) {
	$this->ps = &$ps;
}

// returns a list of available heatmaps for the map specified
function get_map_heatmaps($mapid, $criteria = array()) {

}

// returns a list of available heatmaps for the player specified
function get_player_heatmaps($plrid, $criteria = array()) {
	// ...
}

// generates a heatkey with the criteria specified
function heatkey($criteria = array()) {
	// this order is very important and should never change
	static $order = array( 'mapid', 'weaponid', 'who', 'pid', 'kid', 'team', 'kteam', 'vid', 'vteam', 'headshot' );
	$criteria += array( 'who' => 'victim' );	 // 'who' is the only criteria that doesn't default to NULL
	$key = "";
	foreach ($order as $k) {
		if (array_key_exists($k, $criteria) and $criteria[$k] !== NULL) {
			$key .= $criteria[$k];
		} else {
			$key .= 'NULL';
		}
		$key .= '-';
	}
	return sha1(substr($key,0,-1));
}

} // END OF class PS_Heatmap
?>
