<?php
/**
	class_valve.php
	Stormtrooper at psychostats dot com 
	http://www.psychostats.com/
	$Id$

	Basic Valve "Steam ID" to "Friend ID" translator. Original concept and functional code from:
	http://www.joe.to/moo/sid_convert.php
	http://forums.alliedmods.net/showthread.php?t=60899

	This class will allow you to translate a players Steamid into a Friend ID that the 
	Valve community website uses to identify players.

	Example of a valve player profile page: http://steamcommunity.com/profiles/123

	This class requires the BCMath routines to be enabled in PHP. http://php.net/bc
	PHP4 is required, but will eventually be moved to PHP5 only as more features are added.

**/

class Valve_AuthId {

/**
* @var string $steam_community_url	Specifies the base URL to the steam community website.
* @access protected
*/
var $steam_community_url = "http://steamcommunity.com/profiles/";

function Valve_AuthId() {
	// nothing to do
}

/**
* Converts the "Steam ID" given into a "Friend ID".
*
* @param string $steamid	A Steam ID.
*/
function get_friend_id($steamid) {

	$parts = explode(':', $steamid);
	if (!$parts or strtoupper(substr($parts[0], 0, 5)) != 'STEAM') {
		trigger_error("Invalid STEAM ID passed to " . get_class($this) . "::get_friend_id($steamid)", E_USER_WARNING);
		return false;
	}

	// STEAM_0:<SERVER_ID>:<AUTH_ID>
	$server = $parts[1];
	$auth = $parts[2];

	// an Auth ID of 0 is invalid
	if ($auth == "0") {
		return "0";
	}

	$friend = bcmul($auth, "2");
	$friend = bcadd($friend, bcadd("76561197960265728", $server)); 
	
	return $friend;
}

/**
* Converts the "Friend ID" given into a "Steam ID".
*
* @param string $friendid	A Friend ID.
*/
function get_steam_id($friendid)
{
	$server = bcmod($friendid, "2") == "0" ? "0" : "1";
	$friendid = bcsub($friendid, $server);
	if (bccomp("76561197960265728",$friendid) == -1) {
		$friendid = bcsub($friendid, "76561197960265728");
	}
	$authid = bcdiv($friendid, "2");
	return "STEAM_0:" . $server . ":" . $authid;
}

/**
* Returns an fully qualified URL to the steamcommunity.com website for the 
* steam_id or friend_id given.
*
* @param string $id	A Steam ID, or Friend ID. The type of ID is auto-discovered.
*/
function steam_community_url($id) {
	if (!preg_match('/^\d+$/', $id)) {
		$id = @$this->get_friend_id($id);
		if (!$id) {
			return false;
		}
	}
	return $this->get_steam_community_url() . $id;
}

/**
* Set's the $steam_community_url base URL.
*
* @param string $url	A fully qualified URL.
*/
function set_steam_community_url($url) {
	$this->steam_community_url = $url;
}

/**
* Returns the $steam_community_url base URL.
*/
function get_steam_community_url() {
	return $this->steam_community_url;
}

} // End of Valve_AuthId

?>
