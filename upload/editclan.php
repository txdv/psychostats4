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
$cms->theme->page_title('PsychoStats - Edit Clan Profile');

$validfields = array('ref','id','del','submit','cancel','memberlist','value','add','del','ajax');
$cms->theme->assign_request_vars($validfields, true);

$message = '';
$cms->theme->assign_by_ref('message', $message);

//print_r($cms->input); die;

// ajax autocomplete request for player searching
$limit = 50;
if ($cms->user->logged_in() and $memberlist) {
	$value = trim($value);
	$match = '%' . $ps->db->escape($value) . '%';
	$list = $ps->db->fetch_rows(1, 
		"SELECT p.plrid,p.uniqueid,pp.name FROM $ps->t_plr p, $ps->t_plr_profile pp " . 
		"WHERE pp.uniqueid=p.uniqueid AND p.clanid=0 AND (pp.uniqueid LIKE '$match' OR pp.name LIKE '$match') " .
		"ORDER BY name " . 
		"LIMIT $limit "	// limit the results
	);

/*
	$xml = '<?xml version="1.0"?><ajaxresponse>';
	foreach ($list as $p) {
		$xml .= sprintf("<item><text><![CDATA[%s]]></text><value><![CDATA[%s]]></value><plrid>%d</plrid></item>\n", 
			"<b>" . $p['name'] . "</b><br/><small>" . $p['uniqueid'] . "</small>",
			$p['name'],
			$p['plrid']
		);
	}
	$xml .= "</ajaxresponse>\n";
	header("Content-Type: text/xml");
	print $xml;
*/

	$html = "";
	foreach ($list as $p) {
		$html .= sprintf("<option value='%d'>%s %s</option>\n", 
			$p['plrid'], 
			$ps->conf['main']['uniqueid'] != 'name' ? ps_escape_html($p['uniqueid']).':' : '',
			ps_escape_html($p['name'])
		);
	}
	print $html; //str_replace('  ', '&nbsp;&nbsp;', $html);

	exit;
}


if ($cancel) {
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'clans.php' )));
}

$clan = array();
$members = array();

// load the matching clan if an ID was given
if ($id) {
	// load the clan based on their clanid
	$clan = $ps->get_clan_profile($id);
	if ($clan and $clan['profile_clantag'] == null) { // no matching profile; lets create one (all clans should have one, regardless)
		$_id = $ps->db->escape($id, true);
		$ps->db->insert($ps->t_clan_profile, array( 'clantag' => $clan['clantag'] ));
	}

	if (!$clan) {
		$data = array( 'message' => $cms->trans("Invalid clan ID Specified") );
		$cms->full_page_err(basename(__FILE__, '.php'), $data);
	}
} else {
	$data = array( 'message' => $cms->trans("Invalid clan ID Specified") );
	$cms->full_page_err(basename(__FILE__, '.php'), $data);
}

// check privileges to edit this clan
if (!ps_user_can_edit_clan($clan['clanid'], ps_user_plrid())) {
	$data = array( 'message' => $cms->trans("Insufficient privileges to edit clan!") );
	$cms->full_page_err(basename(__FILE__, '.php'), $data);
}

// add or delete a member (ajax request)
if ($add) {
	if (!is_array($add)) $add = array( $add );
	$cmd = "SELECT plrid FROM $ps->t_plr p WHERE plrid IN (%s) AND p.clanid=0";
	$ids = array();
	$msg = "";
	foreach ($add as $plrid) {
		if (is_numeric($plrid)) $ids[] = $plrid;
	}
	if (!count($ids)) {
		$ids[] = 0;
		$msg = "error";
	}
	$list = $ps->db->fetch_list(sprintf($cmd, join(',',$ids)));
	$ps->db->update($ps->t_plr, array( 'clanid' => $id ), 'plrid', $ids);
	foreach ($ids as $plrid) {
		$plr = $ps->get_player_profile($plrid);
		$msg .= "<tr>" .
			"<td>" . ($plr['rank'] ? $plr['rank'] : '-') . "</td>" .
			"<td class='item'><a href='" . ps_url_wrapper(array('_base' => 'editplr.php', 'id' => $plr['plrid'])) . "'>{$plr['name']}</a></td>" .
			"<td>{$plr['uniqueid']}</td>" .
			"<td>{$plr['skill']}</td>" .
			"<td><a id='mem-" . $plr['plrid'] . "' href='" . ps_url_wrapper(array('id' => $id, 'del' => $plr['plrid'])) . "'><img src='" . $cms->theme->url() . "/img/icons/delete.png'/></a></td>" . 
			"</tr>\n";
	}
	if ($ajax) {
		print $msg;
		exit;
	} else {
		$message = $cms->message('success', array(
			'message_title'	=> $cms->trans("Member Added!"),
			'message'	=> $cms->trans("%s (%s) was added to the clan.", $plr['name'], $plr['uniqueid'])
		));
	}

} elseif ($del) {
	$plr = $ps->get_player_profile($del);
	$msg = "error";
	if ($plr['clanid'] == $id) {
		$ps->db->update($ps->t_plr, array( 'clanid' => 0 ), 'plrid', $plr['plrid']);
		$msg = "success";
	}

	if ($ajax) {
		print $msg;
		exit();
	} else {
		$message = $cms->message('success', array(
			'message_title'	=> $cms->trans("Member Removed!"),
			'message'	=> $cms->trans("%s (%s) was removed from the clan.", $plr['name'], $plr['uniqueid'])
		));
	}
}



$members = $ps->get_clan_members($id);

// create the form variables
$form = $cms->new_form();
$form->default_modifier('trim');
$form->field('clanname');	// 'clanname' is used instead of 'name' to avoid conflicts with some software (nuke)
$form->field('email');
$form->field('aim');
$form->field('icq');
$form->field('msn');
$form->field('website');
$form->field('icon');
$form->field('cc');
$form->field('logo');
$form->field('locked');

// process the form if submitted
$valid = true;
if ($submit) {
	$form->validate();
	$input = $form->values();
	$valid = !$form->has_errors();
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	$input['name'] = $input['clanname'];
	unset($input['clanname']);

	// force a protocol prefix on the website url (http://)
	if (!empty($input['website']) and !preg_match('|^\w+://|', $input['website'])) {
		$input['website'] = "http://" . $input['website'];
	}

	// strip out any bad tags from the logo.
	if (!empty($input['logo'])) {
		$logo = ps_strip_tags($input['logo']);
		$c1 = md5($logo);
		$c2 = md5($input['logo']);
		if ($c1 != $c2) {
			$form->error('logo', $cms->trans("Invalid tags were removed.") . " " .
				$cms->trans("Resubmit to try again.") 
			);
			$form->set('logo', $logo);
		}
		$input['logo'] = $logo;
	}

	$valid = ($valid and !$form->has_errors());
	if ($valid) {
		$input['clantag'] = $clan['clantag'];

		$locked = $input['locked'] ? 1 : 0;
		unset($input['locked']);

		$input['cc'] = strtoupper($input['cc']);

		if ($id) {
			$ok = $ps->db->update($ps->t_clan_profile, $input, 'clantag', $clan['clantag']);
		} else {
			$ok = $ps->db->insert($ps->t_clan_profile, $input);
		}

		// update 'locked' value, if changed
		if ($ok and $locked != $clan['locked']) {
			$ok = $ps->db->update($ps->t_clan, array( 'locked' => $locked ), 'clantag', $clan['clantag']);
		}

		if (!$ok) {
			$form->error('fatal', "Error updating database: " . $ps->db->errstr);
		} else {
			previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'clans.php' )));
		}

	}

} else {
	// fill in defaults
	if ($id) {
		$clan['clanname'] = $clan['name'];
		$form->input($clan);
	}
}

// save a new form key in the users session cookie
// this will also be put into a 'hidden' field in the form
if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());

$allowed_html_tags = str_replace(',', ', ', $ps->conf['theme']['format']['allowed_html_tags']);
if ($allowed_html_tags == '') $allowed_html_tags = '<em>' . $cms->trans("none") . '</em>';
$cms->theme->assign(array(
	'errors'	=> $form->errors(),
	'clan'		=> $clan,
	'members'	=> $members,
	'allowed_html_tags' => $allowed_html_tags,
	'form'		=> $form->values(),
	'form_key'	=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
));

// display the output
$basename = basename(__FILE__, '.php');
$cms->theme->add_css('css/forms.css');
$cms->theme->add_js('js/jquery.interface.js');	// needed for autocomplete
$cms->theme->add_js('js/forms.js');
$cms->theme->add_js('js/message.js');
$cms->theme->add_js('js/editclan.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
