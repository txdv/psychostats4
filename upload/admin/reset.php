<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if ($register_admin_controls) {
	$menu =& $PSAdminMenu->getSection( $ps_lang->trans("Manage Players") );

	$opt =& $menu->newOption( " * " . $ps_lang->trans("Reset All Stats") . " * ", 'reset' );
	$opt->link(ps_url_wrapper(array('c' => 'reset')));

	return 1;
}

$data['PS_ADMIN_PAGE'] = "reset";

if ($cancel) previouspage('admin.php');

$validfields = array('confirm', 'delplrprofiles', 'delclanprofiles', 'delweapons');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

// form fields ...
$formfields = array(
	'confirm'		=> array('label' => $ps_lang->trans("Confirm Reset").':',		'val' => '',   'statustext' => $ps_lang->trans("You must check this box to confirm that you want to reset your stats!")),
	'delplrprofiles'	=> array('label' => $ps_lang->trans("Delete Player Profiles").':',	'val' => '',   'statustext' => $ps_lang->trans("If checked <b>player</b> profiles will be deleted. I recommend leaving this unchecked.")),
	'delclanprofiles'	=> array('label' => $ps_lang->trans("Delete Clan Profiles").':',	'val' => '',   'statustext' => $ps_lang->trans("If checked <b>clan</b> profiles will be deleted. I recommend leaving this unchecked.")),
	'delweapons'		=> array('label' => $ps_lang->trans("Delete Weapons").':',		'val' => '',   'statustext' => $ps_lang->trans("If checked <b>Weapon</b> definitions will be deleted. This is usually unchecked if you want your weapons to retain their real names and weights.")),
);

$empty_c = array( 'c_map_data', 'c_plr_data', 'c_plr_maps', 'c_plr_victims', 'c_plr_weapons', 'c_weapon_data', 'c_role_data', 'c_plr_roles' );
$empty_m = array( 't_map_data', 't_plr_data', 't_plr_maps' );
$empty = array( 
	't_awards', 't_awards_plrs', 
	't_clan', 
	't_errlog',
	't_map', 't_map_data', 
	't_plr', 't_plr_data', 't_plr_ids', 't_plr_maps', 't_plr_roles', 't_plr_sessions', 't_plr_victims', 't_plr_weapons', 
	't_role', 't_role_data', 
	't_search', 't_state', 't_state_plrs', 
	't_weapon_data'
);

$msg = '';

if ($submit and !$confirm) {
	$errors['fatal'] = $ps_lang->trans("You must check the confirmation checkbox!");
} elseif ($confirm) {
	// delete complied data
	foreach ($empty_c as $t) {
		$tbl = $ps->$t;
#		if (!$ps->db->truncate($tbl) and !preg_match("/exist/", $ps->db->errstr)) {
		if (!$ps->db->droptable($tbl) and !preg_match("/unknown table/i", $ps->db->errstr)) {
			$errors['fatal'] .= "$tbl: " . $ps->db->errstr . "<br>";
		}
	}

	// delete most of everything else
	foreach ($empty as $t) {
		$tbl = $ps->$t;
		if (!$ps->db->truncate($tbl) and !preg_match("/exist/", $ps->db->errstr)) {
			$errors['fatal'] .= "$tbl: " . $ps->db->errstr . "<br>";
		}
	}

	// delete mod specific tables
	foreach ($empty_m as $t) {
		$tbl = $ps->$t . $ps->tblsuffix;
		if (!$ps->db->truncate($tbl) and !preg_match("/exist/", $ps->db->errstr)) {
			$errors['fatal'] .= "$tbl: " . $ps->db->errstr . "<br>";
		}
	}

	if ($delplrprofiles) {
		$tbl = $ps->t_plr_profile;
		if (!$ps->db->truncate($tbl)) $errors['fatal'] .= "$tbl: " . $ps->db->errstr . "<br>";
		// should I update the users table here to remove any plr->user relationships?
	} 

	if ($delclanprofiles) {
		$tbl = $ps->t_clan_profile;
		if (!$ps->db->truncate($tbl)) $errors['fatal'] .= "$tbl: " . $ps->db->errstr . "<br>";
	}

	if ($delweapons) {
		$tbl = $ps->t_weapon;
		if (!$ps->db->truncate($tbl)) $errors['fatal'] .= "$tbl: " . $ps->db->errstr . "<br>";
	}

	if (!$errors) {
		$msg = $ps_lang->trans("All player statistics have been reset!");
	}

	$ps->errlog("Player stats have been reset!!!", 'info', $ps_user['userid']);

}

foreach ($validfields as $var) {
	$data[$var] = $$var;
}

$data['errors'] = $errors;
$data['form'] = $formfields;
$data['msg'] = $msg;

?>
