<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . "/includes/common.php");
include(PS_ROOTDIR . "/includes/forms.php");

$minpwlen = 5;

$validfields = array('themefile','submit','cancel','ref','id','search','add','del','members','plrids','dosearch');
globalize($validfields);

foreach ($validfields as $var) {
	$data[$var] = $$var;
}

if ($cancel) previouspage('index.php');
if (!user_logged_on()) gotopage("login.php?ref=" . urlencode($PHP_SELF . "?id=$id"));

// form fields ...
$formfields = array(
	// use 'clanname' instead of 'name' so PsychoNuke works. 'name' still needs to be defined in the formfields array.
	'clanname'	=> array('label' => $ps_lang->trans("Clan Name"). ':', 		'val' => '', 'statustext' => $ps_lang->trans("Enter the real name of the clan")),
	'name'		=> array(							'val' => ''),
	'locked'	=> array('label' => $ps_lang->trans("Lock Members?"), 		'val' => '', 'statustext' => $ps_lang->trans("Lock the member list from automatic updates")),
	'icon'		=> array('label' => $ps_lang->trans("Icon"). ':',	 	'val' => '', 'statustext' => $ps_lang->trans("Choose an interesting icon that represents your clan")),
	'email'		=> array('label' => $ps_lang->trans("Email Address") .':', 	'val' => 'E', 'statustext' => $ps_lang->trans("An email address that other players can use to contact you about the clan")),
	'aim'		=> array('label' => $ps_lang->trans("AIM Screen name"). ':',	'val' => '', 'statustext' => $ps_lang->trans("AOL Instant Messenger (AIM) screen name")),
	'icq'		=> array('label' => $ps_lang->trans("ICQ Number"). ':',		'val' => '', 'statustext' => $ps_lang->trans("ICQ Number")),
	'msn'		=> array('label' => $ps_lang->trans("MSN Email Address"). ':',	'val' => '', 'statustext' => $ps_lang->trans("Microsoft MSN email address")),
	'website'	=> array('label' => $ps_lang->trans("Website"). ':',		'val' => '', 'statustext' => $ps_lang->trans("Enter your website if you have one")),
	'logo'		=> array('label' => $ps_lang->trans("Logo HTML"). ':',		'val' => '', 'statustext' => $ps_lang->trans("Your logo is displayed exactly as entered (HTML included)")),
);

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'editclan';
$data['PAGE'] = 'editclan';

$form = array();
$errors = array();

// no clan id? default to current user clan (user might not have a clanid either)
if (empty($id)) $id = $ps_user['clanid'];
$edit_allowed = (($id == $ps_user['clanid'] && user_is_clanadmin()) || user_is_admin());
$allow_icon_upload = ($ps->conf['theme']['allow_icon_upload'] or user_is_admin());
$allow_icon_overwrite = ($ps->conf['theme']['allow_icon_overwrite'] or user_is_admin());

// if the ID is still empty then we're most likely an admin with no clan associated
if (empty($id)) {
	abort('nomatch', $ps_lang->trans("No clan ID"), $ps_lang->trans("You must specify a clan ID"));
}

// a non-numeric ID was given
if (!is_numeric($id)) {
	abort('nomatch', $ps_lang->trans("Invalid clan ID"), $ps_lang->trans("An invalid clan ID was specified"));
}

// Current user is not an admin and is trying to edit someone else
if (!$edit_allowed) {
	abort('nomatch', $ps_lang->trans("Access Denied!"), $ps_lang->trans("You do not have privilege to edit other clans"));
}

$theclan = load_clan($id);
if (!$theclan) {
	abort('nomatch', $ps_lang->trans("No Clan Found!"), $ps_lang->trans("The clan ID does not exist"));
}
$clanmembers = load_clan_members($id);

$data['profile'] = $theclan;	// allow the form to reference the original profile information
$data['allow_icon_upload'] = $allow_icon_upload;
$data['allow_icon_overwrite'] = $allow_icon_overwrite;


// process submitted form
if ($submit and $_SERVER['REQUEST_METHOD'] == 'POST') {
	$form = packform($formfields);
	trim_all($form);
//	if (get_magic_quotes_gpc()) stripslashes_all($form);

	// make sure 'website' variable has a protocol prefix
	if (!empty($form['website'])) {
		if (!preg_match('|^\w+://|', $form['website'])) {
			$form['website'] = "http://" . $form['website'];
		}
	}

	if (!empty($form['logo']) and strlen($form['logo']) > $ps->conf['theme']['format']['max_logo_size']) {
		$form['logo'] = substr($form['logo'], 0, $ps->conf['theme']['format']['max_logo_size']);
	}

	// automatically verify all fields
	foreach ($formfields as $key => $ignore) {
		form_checks($form[$key], $formfields[$key]);
	}

	$errors = all_form_errors($formfields);

	// If there are no errors act on the data given
	if (!count($errors)) {
		$set = $form;
		$set['logo'] = ps_strip_tags($set['logo']);
		$clanset = array();
		$clanset['locked'] = $set['locked'];
		$set['name'] = $set['clanname'];		// so PsychoNuke works
		unset($set['locked'], $set['clanname']);

		trimset($clanset, $theclan);
		trimset($set, $theclan);

		$ok = $ok1 = $ok2 = 1;
		if (count($clanset)) {
			$ok1 = $ps_db->update($ps->t_clan, $clanset, 'clanid', $theclan['clanid']);
		} 
		if (count($set)) {
			$ok2 = $ps_db->update($ps->t_clan_profile, $set, 'clantag', $theclan['clantag']);
		}
		$ok = ($ok1 && $ok2);
		if ($ok) previouspage('index.php');
	}

	$data += $form;	

} elseif ($del and is_array($members)) {
	del_member($members);
	$clanmembers = load_clan_members($id);
	$data += $theclan;
	$data['clanname'] = $data['name'];

} elseif (($dosearch and $search) or ($add and is_array($plrids))) {
	$added = false;
	// add members from list
	if ($add) $added = add_member($plrids);

	// redo search
	if ($search) {
		$ps->search_players(array(
			'search' 	=> $search,
			'ranked' 	=> 0,
			'limit'		=> 100,		// limit our results ... 
			'ignoresingle'	=> true,	// must be true, or you get confusing results sometimes
//			'where'		=> "p.clanid != '0'",
			'where'		=> sprintf("p.clanid != '%s'", $ps->db->escape($id)),
		));
		$res = $ps->get_search_results();
		$total = $res['results'] ? count(explode(',',$res['results'])) : 0;
		if ($total == 1 and !$added) {
			add_member(array( $res['results'] ));
//			$data['search'] = '';
		} else {
//			$data['searchresults'] = $ps->db->fetch_rows(1, "SELECT p.plrid,pp.uniqueid,pp.name FROM $ps->t_plr p, $ps->t_plr_profile pp WHERE p.plrid IN (" . $ps->db->escape($res['results']) . ") AND p.uniqueid=pp.uniqueid and p.clanid != '" . $ps->db->escape($id) . "' ORDER BY pp.name");
			$data['searchresults'] = $ps->db->fetch_rows(1, "SELECT p.plrid,pp.uniqueid,pp.name FROM $ps->t_plr p, $ps->t_plr_profile pp WHERE p.plrid IN (" . $ps->db->escape($res['results']) . ") AND p.uniqueid=pp.uniqueid ORDER BY pp.name");
			if (!$data['searchresults']) {
				$data['nosearchresults'] = $ps_lang->trans("No players found");
			}
		}
	}
	$clanmembers = load_clan_members($id);
	$data += $theclan;
	$data['clanname'] = $data['name'];

} else {		// init defaults, if any
	// pack all the variables together and merge them with the data
	$data += $theclan;
	$data['clanname'] = $data['name'];
}

$data['icons'] = load_icons(catfile($ps->conf['theme']['rootimagesdir'], 'icons'));
$data['form'] = $formfields;
$data['errors'] = $errors;
$data['clanmembers'] = $clanmembers;

$smarty->assign($data);
$smarty->parse($themefile);
ps_showpage($smarty->showpage());

function del_member($plrids) {
	global $ps,$id,$data,$ps_lang;
	if (!is_array($plrids)) $plrids = array( $plrids );
	for ($i=0; $i < count($plrids); $i++) {
		if (!is_numeric($plrids[$i])) unset($plrids[$i]);			
	}
	$ps->db->query("UPDATE $ps->t_plr SET clanid=0 WHERE plrid IN (" . implode(',',$plrids) . ")");
	if ($ps->db->affected_rows()) {
		$data['msg'] = $ps_lang->trans(sprintf("%s members removed from clan roster", $ps->db->affected_rows()));
	}
}

function add_member($plrids) {
	global $ps,$id,$data,$ps_lang;
	$added = false;
	if (!is_array($plrids)) $plrids = array( $plrids );
	for ($i=0; $i < count($plrids); $i++) {
		if (!is_numeric($plrids[$i])) unset($plrids[$i]);			
	}
	$ps->db->query("UPDATE $ps->t_plr SET clanid='" . $ps->db->escape($id) . "' WHERE plrid IN (" . implode(',',$plrids) . ")");
	if ($ps->db->affected_rows()) {
		$added = true;
		$data['msg'] = $ps_lang->trans(sprintf("%s members added to clan roster", $ps->db->affected_rows()));
		$ps->del_search_results();
	}
	return $added;
}


include(PS_ROOTDIR . "/includes/footer.php");
?>
