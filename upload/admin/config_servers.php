<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

include_once("_config_servers.php");

if ($register_admin_controls) {
	$menu =& $PSAdminMenu->getSection( $ps_lang->trans("Configuration") );

	$opt =& $menu->newOption( $ps_lang->trans("Live Servers"), 'config_servers' );
	$opt->link(ps_url_wrapper(array('c' => 'config_servers')));

	return 1;
}

$data['PS_ADMIN_PAGE'] = "config_servers";

if ($cancel) previouspage('admin.php');

$validfields = array('srv');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

$t = 'servers';

// form fields ...
$formfields = array(
	'section'	=> array('label' => $ps_lang->trans("Host:Port"), 'val' => 'B', 'statustext' => $ps_lang->trans("Server IP (or host) and port for the game server")),
	'querytype'	=> array('label' => $ps_lang->trans("Query Type"), 'val' => 'B', 'statustext' => $ps_lang->trans("What type of game server is running")),
	'rcon'		=> array('label' => $ps_lang->trans("RCON"), 'val' => '', 'statustext' => $ps_lang->trans("Password for remote administration")),
	'enabled'	=> array('label' => $ps_lang->trans("Enable"), 'val' => '', 'statustext' => $ps_lang->trans("Should this server appear in the live server view?")),
);

$form = array();
$errors = array();
$formfields2 = array();

// load the config specified
if (!array_key_exists($t, $ps->conf)) {
	$ps->load_config($t);
}
$servers = $ps->conf[$t];

// this is done so the theme can reference any errors for each server directly
foreach ($servers as $ip => $s) {
	$formfields2[$ip] = $formfields;
}

if ($submit and $_SERVER['REQUEST_METHOD'] == 'POST') {
	$errors = 0;
	$list = array();
	if (!is_array($srv)) $srv = array();
	foreach ($srv as $idx => $s) {
		trim_all($s);
		$section = $s['section'];
		$s['enabled'] = !empty($s['enabled']) ? 1 : 0;
		if ($section == '') continue; 	// remove server from config

		if (!in_array($s['querytype'], array('halflife','oldhalflife','quake3','gamespy'))) {
			$formfields2[$section]['querytype']['error'] = $ps_lang->trans("Invalid query type selected");
			$errors++;
			continue;
		}

		$list[] = $s;

	} 

//	if (!$errors and count($list)) {
	if (!$errors) {
		collect_server_ids();
		$ps_db->begin();
		$ps_db->delete($ps->t_config, 'conftype', 'servers');	// delete all servers
		$idx = 0;
		$commit = 1;
		foreach ($list as $s) {					// add each server back in
			$set = array(
				'idx'		=> ++$idx,
				'conftype'	=> 'servers',				
				'section'	=> $s['section']
			);

			// only save valid variables for the server
			foreach (array('querytype','rcon','enabled') as $i => $var) {
				$ps_db->insert($ps->t_config, $set + array(
					'id'		=> $ps_db->next_id($ps->t_config),
					'var'		=> $var,
					'value'		=> $s[$var]
				));
			}
		}		
		if ($commit) $ps_db->commit();

		$ps->load_config($t);
		$servers = $ps->conf[$t];
	}

} else {

}

// add an extra blank server so a new one can be added
$servers[''] = array('enabled' => 1);

$data['servers'] = $servers;
$data['formfields'] = $formfields;
$data['form'] = $formfields2;
$data['errors'] = $errors;
$data['adminpage'] = "config_$t";

?>
