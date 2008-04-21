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
$cms->theme->page_title('PsychoStats - Theme Gallery');

// collect url parameters ...
$validfields = array('t');
$cms->theme->assign_request_vars($validfields, true);

$t = trim($t);

$themes = $cms->theme->get_theme_list();

// update the user's theme if they selected one from the list
if ($t) {
	if ($cms->theme->is_theme($t, true)) {
		$cms->session->opt('theme', $t);
		$cms->session->save_session_options();
	} else {
		// report an error?
		// na... just silently ignore the language
//		trigger_error("Invalid theme specified!", E_USER_WARNING);
	}
	previouspage($PHP_SELF);
}

// assign variables to the theme
$cms->theme->assign(array(
	'themes'	=> $themes,
));

// display the output
$basename = basename(__FILE__, '.php');
//$cms->theme->add_js('js/themes.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');

?>
