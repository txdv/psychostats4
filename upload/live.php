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
include(PS_ROOTDIR . '/includes/PS/Live.php');
$cms->init_theme($ps->conf['main']['theme'], $ps->conf['theme']);
$ps->theme_setup($cms->theme);
$cms->theme->page_title('PsychoStats - PsychoLive');

// Total seconds to delay recording. This is approximate. Due to how events are
// inserted at the start of a new game by the game engine there is usually a
// built in delay of about 15 seconds. This value ensures that playback is
// delayed by at least this amount.
$delay = 15;

$live = new PS_Live($ps, $delay);

// collect url parameters ...
$validfields = array(
	// playback variables
	'game','req','advance','o','s',
	// game listing variables
	'q','start','limit','sort','order'
);
$cms->theme->assign_request_vars($validfields, true);

$req = strtolower($req);

// verify a valid given ID is given with the request
if ($req and $game != '' and (!is_numeric($game) or $game < 0)) {
	output_result(0, $cms->trans('Invalid game selected for setup!'));
}

// if $game is ZERO then we automatically set it to the newest game.
if ($game != '' and intval($game) == 0) {
	$game = $live->newest_game_id();
	if (!$game) {
		// no games are available
		output_result(0, $cms->trans('No games found!'));
	}
}

if ($req == 'update' and !empty($game)) {
	// REQUEST: Return a list of events starting at the offset specified
	
	// $s is the total seconds of events that should be returned.
	// Make sure this value is 'sane.'
	if (!is_numeric($s) or $s < 1 or $s > 60) {
		$s = 5;
	}
	
	// $o is the offset to start our events from. Starts at 0
	if (!is_numeric($o) or $o < 0) {
		$o = 0;
	}

	$info = $live->get_game_info($game, false, true);
	$events = $live->get_game_events($game, $o, $s);

	// get the ending time of the game, if available.
	//$_id = $ps->db->escape($game, true);
	//$end_time = $ps->db->fetch_item("SELECT end_time FROM {$ps->t_live_games} WHERE game_id=$_id");
	//if ($end_time) {
	//	$end_idx = $ps->db->fetch_item("SELECT MAX(event_idx) FROM {$ps->t_live_events} WHERE game_id=$_id");
	//}

	$result = array(
		'oldest_timestamp' 	=> $events ? $events[0]['event_time'] : 0,
		'newest_timestamp' 	=> $events ? $events[ count($events)-1 ]['event_time'] : 0,
		'next_offset'		=> $events ? $events[ count($events)-1 ]['event_idx']+1 : $o,
		//'end_time'		=> $end_time,
		//'end_idx'		=> $end_idx,
		'game'			=> &$info,
		'events'		=> &$events
	);
	if ($cms->input['debug']) {
		print "<span style='display:block;font-size:70%'>" . join('<br/>',$live->ps->db->queries) . "</span>\n";
		var_dump($result);
		exit;
	}
	output_result(1, $result);
	
} else if ($req == 'setup' and !empty($game)) {
	// REQUEST: Return the basic game setup information for a game.
	$g = $live->get_game_info($game, $advance);
	if (!$g) {
		output_result(0, array('err' => $cms->trans('Game not found!')));
	}
	//$g['overlay'] = false;	// testing...
	if ($cms->input['debug']) {
		print "<span style='display:block;font-size:70%'>" . join('<br/>',$live->ps->db->queries) . "</span>\n";
		var_dump($g);
		exit;
	}
	output_result(1, $g);		// all is well, output game info
	
} else {
	if (empty($game)) $game = 0;
	
	// build a list of games that are available.
	$gamelist = $live->get_game_list(array(
		'sort'	=> $sort,
		'order'	=> $order,
		'start'	=> $start,
		'limit' => $limit,
		'filter'=> $q
	));
	
	// assign variables to the theme
	$cms->theme->assign(array(
		'game'		=> $game,
		'games'		=> $gamelist
	));
}

// display the output
$basename = basename(__FILE__, '.php');
//$cms->theme->add_css('css/2column.css');	// this page has a left column
$cms->theme->add_css('css/psycholive.css');
$cms->theme->add_js('js/jquery.ui.effects.js');	// optional
$cms->theme->add_js('js/jquery.psycholive.js');
//$cms->theme->add_js('js/live.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');

function output_result($code, $message = array(), $json = true) {
	$data = array();
	if (!is_array($message)) {
		$data = array( 'code' => $code, 'message' => $message );
	} else {
		$data = array_merge(array('code' => $code), $message);
	}
	
	if ($json) {
		header("Content-Type: application/x-javascript");
		print json_encode($data);
	} else {
		header("Content-Type: text/xml", true);
		print xml_response($data);
	}
	exit;
}

?>
