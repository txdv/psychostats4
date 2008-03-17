<?php
/**
 *	PsychoStats Heatmap class
 *	$Id$
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
