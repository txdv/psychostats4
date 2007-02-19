<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");
/*
	Extra configuration for clan queries
	The 'TYPES' here are mostly identical to the same TYPES found in the PS3 Perl code
*/

$this->CLAN_TYPES = array(
#	'dataid'	=> '=', 
#	'plrid'		=> '=',
#	'statdate'	=> '=',
	'onlinetime'	=> '+',
	'kills'		=> '+',
	'deaths'	=> '+', 
	'killsperdeath'	=> array( 'ratio', 'kills', 'deaths' ),
	'killsperminute'=> array( 'ratio_minutes', 'kills', 'onlinetime' ),
	'headshotkills'	=> '+',
	'headshotpct'	=> array( 'percent', 'headshotkills', 'kills' ),
	'headshotdeaths'=> '+',
	'ffkills'	=> '+',
	'ffkillspct'	=> array( 'percent', 'ffkills', 'kills' ),
	'ffdeaths'	=> '+',
	'ffdeathspct'	=> array( 'percent', 'ffdeaths', 'deaths' ),
	'kills_streak'	=> '>',
	'deaths_streak'	=> '>',
	'damage'	=> '+',
	'shots'		=> '+',
	'hits'		=> '+',
	'shotsperkill'	=> array( 'ratio', 'shots', 'kills' ),
	'accuracy'	=> array( 'percent', 'hits', 'shots' ),
	'suicides'	=> '+', 
	'games'		=> '+',
	'rounds'	=> '+',
	'kicked'	=> '+',
	'banned'	=> '+',
	'cheated'	=> '+',
	'connections'	=> '+',
	'lasttime'	=> '>',
);

$this->CLAN_WEAPON_TYPES = array(
#	dataid		=> '=', 
#	weaponid	=> '=',
#	statdate	=> '=',
	'kills'		=> '+',
	'deaths'	=> '+',
	'ffkills'	=> '+',
	'ffkillspct'	=> array( 'percent', 'ffkills', 'kills' ),
	'headshotkills'	=> '+',
	'headshotpct'	=> array( 'percent', 'headshotkills', 'kills' ),
	'damage'	=> '+',
	'hits'		=> '+',
	'shots'		=> '+',
	'shot_chest'	=> '+',
	'shot_head'	=> '+',
	'shot_leftarm'	=> '+',
	'shot_leftleg'	=> '+',
	'shot_rightarm'	=> '+',
	'shot_rightleg'	=> '+',
	'shot_stomach'	=> '+',
	'accuracy'	=> array( 'percent', 'hits', 'shots' ),
	'shotsperkill'	=> array( 'ratio', 'shots', 'kills' ),
);

$this->CLAN_ROLE_TYPES = array(
#	dataid		=> '=', 
#	roleid		=> '=',
#	statdate	=> '=',
	'kills'		=> '+',
	'deaths'	=> '+',
	'ffkills'	=> '+',
	'ffkillspct'	=> array( 'percent', 'ffkills', 'kills' ),
	'headshotkills'	=> '+',
	'headshotpct'	=> array( 'percent', 'headshotkills', 'kills' ),
	'damage'	=> '+',
	'hits'		=> '+',
	'shots'		=> '+',
	'shot_chest'	=> '+',
	'shot_head'	=> '+',
	'shot_leftarm'	=> '+',
	'shot_leftleg'	=> '+',
	'shot_rightarm'	=> '+',
	'shot_rightleg'	=> '+',
	'shot_stomach'	=> '+',
	'accuracy'	=> array( 'percent', 'hits', 'shots' ),
	'shotsperkill'	=> array( 'ratio', 'shots', 'kills' ),
	'joined'	=> '+',
);

$this->CLAN_MAP_TYPES = array(
#	dataid		=> '=',
#	plrid		=> '=',
#	mapid		=> '=',
#	statdate	=> '=',
	'games'		=> '+',
	'rounds'	=> '+',
	'kills'		=> '+',
	'deaths'	=> '+', 
	'killsperdeath'	=> array( 'ratio', 'kills', 'deaths' ),
	'killsperminute'=> array( 'ratio_minutes', 'kills', 'onlinetime' ),
	'ffkills'	=> '+',
	'ffkillspct'	=> array( 'percent', 'ffkills', 'kills' ),
	'ffdeaths'	=> '+',
	'ffdeathspct'	=> array( 'percent', 'ffdeaths', 'deaths' ),
	'connections'	=> '+',
	'onlinetime'	=> '+',
	'lasttime'	=> '>',
);

$this->CLAN_VICTIM_TYPES = array(
#	dataid		=> '=',
#	plrid		=> '=',
#	victimid	=> '=',
#	statdate	=> '=',
	'kills'		=> '+',
	'deaths'	=> '+', 
	'killsperdeath'	=> array( 'ratio', 'kills', 'deaths' ),
	'headshotkills'	=> '+',
	'headshotpct'	=> array( 'percent', 'headshotkills', 'kills' ),
	'headshotdeaths'=> '+',
);

$this->PLR_SESSIONS_TYPES = array( 
#	plrid		=> '=',
	'sessionstart'	=> '=',
	'sessionend'	=> '=',
	'skill'		=> '=',
	'kills'		=> '+',
	'deaths'	=> '+', 
	'killsperdeath'	=> array( 'ratio', 'kills', 'deaths' ),
#	'killsperminute'=> array( 'ratio_minutes', 'kills', 'onlinetime' ),
	'headshotkills'	=> '+',
	'headshotpct'	=> array( 'percent', 'headshotkills', 'kills' ),
	'headshotdeaths'=> '+',
	'ffkills'	=> '+',
	'ffkillspct'	=> array( 'percent', 'ffkills', 'kills' ),
	'ffdeaths'	=> '+',
	'ffdeathspct'	=> array( 'percent', 'ffdeaths', 'deaths' ),
	'damage'	=> '+',
	'shots'		=> '+',
	'hits'		=> '+',
	'shotsperkill'	=> array( 'ratio', 'shots', 'kills' ),
	'accuracy'	=> array( 'percent', 'hits', 'shots' ),
	'suicides'	=> '+', 
);


// if gametype is maliciously changed into a filepath,
// basename() will make sure it remains 'sane'.
$g = basename($this->conf['main']['gametype']);
if (@file_exists("includes/PS/$g.php")) include("includes/PS/$g.php");

?>
