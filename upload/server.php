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
$cms->theme->page_title('PsychoStats - Live Server Views');

// collect url parameters ...
$validfields = array('s');
$cms->theme->assign_request_vars($validfields, true);

$servers = array();
$servers = $ps->db->fetch_rows(1, 
	"SELECT * " . 
	"FROM $ps->t_config_servers " . 
	"WHERE enabled=1 " . 
	"ORDER BY idx,host,port"
);

for ($i=0; $i < count($servers); $i++) {
	$servers[$i]['ip'] = gethostbyname($servers[$i]['host']);
}

// assign variables to the theme
$cms->theme->assign(array(
	'servers'	=> $servers
));

// display the output
$basename = basename(__FILE__, '.php');
$cms->theme->add_css('css/2column.css');	// this page has a left column
$cms->theme->add_css('css/query.css');
$cms->theme->add_js('js/' . $basename . '.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');

?>
