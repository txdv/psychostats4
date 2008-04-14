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
define("PSYCHOSTATS_ADMIN_PAGE", true);
include("../includes/common.php");
include("./common.php");
include_once(PS_ROOTDIR . "/includes/class_themeManager.php");

$validfields = array('id','start','limit','action','submit','confirm','url','cancel');
$cms->theme->assign_request_vars($validfields, true);

$message = '';
$cms->theme->assign_by_ref('message', $message);

if (!is_numeric($start) or $start < 0) $start = 0;
if (!is_numeric($limit) or $limit < 0) $limit = 1000;
$sort = 'name';
$order = 'asc';

$_order = array(
	'start'	=> $start,
	'limit'	=> $limit,
	'order' => $order, 
	'sort'	=> $sort
);

$theme_dir = $ps->conf['theme']['template_dir'];

// make sure the server environment will allow themes to be installed 
$allow = array();
$allow['url'] = (bool)ini_get('allow_url_fopen');
$allow['write'] = is_writable($theme_dir);

$allow['install'] = ($allow['url'] && $allow['write']);

ini_set('user_agent', "PsychoStats Theme Installer");
$newtheme = new PsychoThemeManager( $ps->db, $ps->conf['theme']['template_dir'] );

if ($cancel) {
	$submit = false;
}

// attempt to install new theme if one is submitted
if ($submit and $url and $allow['install']) {
	$newtheme->load_theme($url);
	if ($newtheme->error()) {
		$submit = false;
		if ($newtheme->code() != PSTHEME_ERR_XML) {
			$message = $cms->message('theme-failure', array( 
				'message_title'	=> "Error installing theme",
				'message'	=> $newtheme->error()
			));
		} else {
			$message = $cms->message('theme-failure', array( 
				'url' => $url,
				'invalid' => $newtheme->invalid_list()
			));
		}
	} elseif ($newtheme->xml_parent()) {
		list($exists) = $ps->db->fetch_list("SELECT 1 FROM $ps->t_config_themes WHERE name LIKE " . $ps->db->escape($newtheme->xml_parent(), true));
		if (!$exists) {
			$submit = false;
			$message = $cms->message('theme-failure', array( 
				'message_title'	=> "Error installing theme",
				'message'	=> "The selected theme requires a parent theme '" . $newtheme->xml_parent() . "' which you do not have installed."
			));
		}
	}


	// if there are no errors and 'confirm' was specified then install the theme
	if ($submit and $confirm) {
		if ($newtheme->install()) {
			// insert a record for this theme in the database
			$exists = $ps->db->fetch_row(1, "SELECT * FROM $ps->t_config_themes WHERE name LIKE " . $ps->db->escape($newtheme->xml_name(), true));
			$set = array();
			$set['name']		= $newtheme->xml_name();
			$set['parent']		= $newtheme->xml_parent() ? $newtheme->xml_parent() : null;
			$set['enabled'] 	= $exists ? ($exists['enabled']?1:0) : 1;
			$set['version']		= $newtheme->xml_version();
			$set['title']		= $newtheme->xml_title();
			$set['author']		= $newtheme->xml_author();
			$set['website']		= $newtheme->xml_website();
			$set['source']		= $newtheme->xml_source();
			$set['image']		= $newtheme->xml_image();
			$set['description']	= $newtheme->xml_description();

			if ($exists) {
				$ok = $ps->db->update($ps->t_config_themes, $set, 'name', $set['name']);
			} else {
				$ok = $ps->db->insert($ps->t_config_themes, $set);
			}
			if ($ok) {
				$message = $cms->message('success', array( 
					'message_title'	=> "Theme installed successfully!",
					'message'	=> "Theme \"" . $set['title'] . "\" was installed successfully and is now available for use."
				));
			} else {
				$message = $cms->message('failure', array( 
					'message_title'	=> "Error installing theme",
					'message'	=> "Unable to add theme to configuration: " . $ps->db->errstr
				));
			}

		} else { // theme did not install properly
			$message = $cms->message('failure', array( 
				'message_title'	=> "Error installing theme",
				'message'	=> $newtheme->error()
			));
		}
	}
} else {
	$submit = false;
}

// do something with an installed theme
if (!empty($action)) $action = strtolower($action);
if ($id and in_array($action, array('default','disable','enable','uninstall'))) {
	$t = $ps->db->fetch_row(1, "SELECT * FROM $ps->t_config_themes WHERE name=" . $ps->db->escape($id, true));
	if (!$t['name']) {
		$data = array(
			'message' => $cms->trans("Invalid Theme Specified"),
		);
		$cms->full_page_err(basename(__FILE__, '.php'), $data);
		exit();		
	}

	$res = 'success';
	$msg = '';
	$title = $cms->trans("Operation was successful!");
	if ($action == 'default')  {
		if ($ps->conf['main']['theme'] != $t['name']) {
			$ok = $ps->db->query(sprintf("UPDATE $ps->t_config SET value=%s WHERE conftype='main' AND (section='' OR ISNULL(section)) AND var='theme'", 
				$ps->db->escape($t['name'], true)
			));
			if ($ok) {
				$msg = $cms->trans("Theme '%s' is now the default theme", $t['name']);
				$ps->conf['main']['theme'] = $t['name'];
				// always make sure the new default theme is enabled
				if (!$t['enabled']) {
					$ps->db->update($ps->t_config_themes, array( 'enabled' => 1 ), 'name', $t['name']);
				}
			} else {
				$res = 'failure';
				$msg = $cms->trans("Error writting to database: ") . $ps->db->errstr;
			}
		}

	} elseif ($action == 'uninstall') {
		if ($ps->conf['main']['theme'] == $t['name'] or $t['name'] == 'default') {
			$res = 'failure';
			$msg = $cms->trans("You can not uninstall the default theme!");
		} else {
			$ok = $ps->db->delete($ps->t_config_themes, 'name', $t['name']);
			if (!$ok) {
				$res = 'failure';
				$msg = $cms->trans("Error writting to database: ") . $ps->db->errstr;
			} else {
				$msg = $cms->trans("Theme '%s' was uninstalled successfully. It was not deleted from the server.", $t['title']);
			}
		}
	} else {
		$enabled = ($action == 'enable') ? 1 : 0;
		if ($ps->conf['main']['theme'] == $t['name'] and !$enabled) {
			$res = 'failure';
			$title = $cms->trans("Operation Failed!");
			$msg = $cms->trans('You can not disable the default theme');
		} else if ($t['enabled'] != $enabled) {
			$ok = $ps->db->update($ps->t_config_themes, array( 'enabled' => $enabled ), 'name', $t['name']);
			if ($ok) {
				$msg = $enabled ? $cms->trans("Theme '%s' was enabled", $t['name']) 
						: $cms->trans("Theme '%s' was disabled", $t['name']);
			} else {
				$res = 'failure';
				$msg = $cms->trans("Error writting to database: ") . $ps->db->errstr;
			}
		}
	}

	if ($msg) $message = $cms->message($res, array(
		'message_title'	=> $title,
		'message'	=> $msg
	));
}

// load the themes
$list = $ps->db->fetch_rows(1, "SELECT * FROM $ps->t_config_themes " . $ps->getsortorder($_order));
$total = $ps->db->count($ps->t_config_themes);
$themes = array();
foreach ($list as $t) {
	if ($t['parent']) {
		$themes[ $t['parent'] ]['children'][] = $t;
	} else {
		$themes[ $t['name'] ] = $t;
		$themes[ $t['name'] ]['children'] = array();
	}
}

$pager = pagination(array(
	'baseurl'	=> ps_url_wrapper(array('sort' => $sort, 'order' => $order, 'limit' => $limit)),
	'total'		=> $total,
	'start'		=> $start,
	'perpage'	=> $limit, 
	'pergroup'	=> 5,
	'separator'	=> ' ', 
	'force_prev_next' => true,
	'next'		=> $cms->trans("Next"),
	'prev'		=> $cms->trans("Previous"),
));

$cms->crumb("Themes", ps_url_wrapper());

// assign variables to the theme
$cms->theme->assign(array(
	'url'		=> $url,
	'allow'		=> $allow,
	'newtheme'	=> $newtheme->theme_xml(),
	'themes'	=> $themes,
	'total_themes'	=> $total,
	'submit'	=> $submit,
	'confirm'	=> $confirm,
	'page'		=> basename(__FILE__, '.php'), 
));

// display the output
$basename = basename(__FILE__, '.php');
$cms->theme->add_css('css/forms.css');
$cms->theme->add_js('js/themes.js');
$cms->theme->add_js('js/message.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
