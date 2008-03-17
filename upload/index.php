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
$cms->theme->page_title = 'PsychoStats - Player Rankings';

// change this if you want the default sort of the player listing to be something else like 'kills'
$DEFAULT_SORT = 'skill';
$DEFAULT_LIMIT = 100;

// collect url parameters ...
$validfields = array('sort','order','start','limit','q');
$cms->theme->assign_request_vars($validfields, true);

$sort = trim(strtolower($sort));
$order = trim(strtolower($order));
if (!preg_match('/^\w+$/', $sort)) $sort = $DEFAULT_SORT;
if (!in_array($order, array('asc','desc'))) $order = 'desc';
if (!is_numeric($start) || $start < 0) $start = 0;
if (!is_numeric($limit) || $limit < 0 || $limit > 500) $limit = $DEFAULT_LIMIT;
$q = trim($q);

// If a language is passed from GET/POST update the user's cookie. 
if (isset($cms->input['language'])) {
	if ($cms->theme->is_language($cms->input['language'])) {
		$cms->session->opt('language', $cms->input['language']);
		$cms->session->save_session_options();
	} else {
		// report an error?
		// na... just silently ignore the language
//		trigger_error("Invalid theme specified!", E_USER_WARNING);
	}
	previouspage($PHP_SELF);
}

// fetch stats, etc...
$totalplayers = $ps->get_total_players(array('allowall' => 1, 'filter' => $q));
$overalltotal = $q == '' ? $totalplayers : $ps->get_total_players(array('allowall' => 1));
$totalranked  = $ps->get_total_players(array('allowall' => 0, 'filter' => $q));

$players = $ps->get_player_list(array(
	'filter'	=> $q,
	'sort'		=> $sort,
	'order'		=> $order,
	'start'		=> $start,
	'limit'		=> $limit,
	'joinclaninfo' 	=> false,
));

$pager = pagination(array(
	'baseurl'	=> ps_url_wrapper(array('sort' => $sort, 'order' => $order, 'limit' => $limit, 'q' => $q)),
	'total'		=> $totalranked,
	'start'		=> $start,
	'perpage'	=> $limit, 
	'pergroup'	=> 5,
	'separator'	=> ' ', 
	'force_prev_next' => true,
	'next'		=> $cms->trans("Next"),
	'prev'		=> $cms->trans("Previous"),
));

// build a dynamic table that plugins can use to add custom columns of data
$table = $cms->new_table($players);
$table->if_no_data($cms->trans("No Players Found"));
$table->attr('class', 'ps-table ps-player-table');
$table->sort_baseurl(array( 'q' => $q ));
$table->start_and_sort($start, $sort, $order);
$table->columns(array(
	'rank'			=> array( 'label' => $cms->trans("Rank"), 'callback' => 'dash_if_empty' ),
	'prevrank'		=> array( 'nolabel' => true, 'callback' => 'rankchange' ),
	'name'			=> array( 'label' => $cms->trans("Player"), 'callback' => 'ps_table_plr_link' ),
	'kills'			=> array( 'label' => $cms->trans("Kills"), 'modifier' => 'commify' ),
	'deaths'		=> array( 'label' => $cms->trans("Deaths"), 'modifier' => 'commify' ),
	'killsperdeath' 	=> array( 'label' => $cms->trans("K:D"), 'tooltip' => $cms->trans("Kills Per Death") ),
	'headshotkills'		=> array( 'label' => $cms->trans("HS"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Headshot Kills") ),
	'headshotkillspct'	=> array( 'label' => $cms->trans("HS%"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Headshot Kills Percentage") ),
	'onlinetime'		=> array( 'label' => $cms->trans("Online"), 'modifier' => 'compacttime' ),
	'activity'		=> array( 'label' => $cms->trans("Activity"), 'modifier' => 'activity_bar' ),
	'skill'			=> array( 'label' => $cms->trans("Skill"), 'callback' => 'plr_skill' ),
));
$table->column_attr('name', 'class', 'left');
$table->column_attr('skill', 'class', 'right');
//$table->column_attr('rank', 'class', 'left');
$table->header_attr('rank', 'colspan', '2');
$ps->index_table_mod($table);
$cms->filter('players_table_object', $table);


// assign variables to the theme
$cms->theme->assign(array(
	'q'		=> $q,
	'search_blurb'	=> $cms->trans('Search criteria "<em>%s</em>" matched %d ranked players out of %d total', ps_escape_html($q),$totalplayers,$totalranked),
	'players'	=> $players,
	'players_table'	=> $table->render(),
	'overalltotal'	=> $overalltotal,
	'totalplayers'	=> $totalplayers,
	'totalranked' 	=> $totalranked,
	'pager'		=> $pager,
	'language_list'	=> $cms->theme->get_language_list(),
	'theme_list'	=> $cms->theme->get_theme_list(),
	'language'	=> $cms->theme->language,
));

// display the output
$basename = basename(__FILE__, '.php');
//$cms->theme->add_js('js/index.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');

function activity_bar($pct) {
//	$out = $pct > 0 ? sprintf("%0.0f%%", $pct) : '-';
	$out = pct_bar(array(
		'pct' => $pct
	));
	return $out;
}

function dash_if_empty($val) {
	return !empty($val) ? $val : '-';
}

function rankchange($val, $plr) {
	return rank_change($plr);
}

function skillchange($val, $plr) {
	return skill_change($plr);
}

function plr_skill($val, $plr) {
	return $val . " " . skill_change($plr);
}

function plr_rank($val, $plr) {
	return rank_change($plr) . " " . $val;
}

?>
