<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if ($register_admin_controls) {
	$menu =& $PSAdminMenu->getSection( $ps_lang->trans("Configuration") );

	$opt =& $menu->newOption( $ps_lang->trans("Servers"), 'servers' );
	$opt->link(ps_url_wrapper(array('c' => 'servers')));

	return 1;
}

$data['PS_ADMIN_PAGE'] = "servers";

if ($cancel) previouspage("admin.php?c=".urlencode($c));

$validfields = array('s','id','move','act','new','del','export','import','msg');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

if ($import) gotopage("$PHP_SELF?c={$c}_import");

// form fields ...
$formfields = array(
	// act = 'edit'
	'idx'		=> array('label' => $ps_lang->trans("Order").':',		'val' => 'N', 'statustext' => $ps_lang->trans("Order of appearance of servers")),
	'_serverip'	=> array('label' => $ps_lang->trans("Server IP"),		'val' => 'B', 'statustext' => $ps_lang->trans("IP of the game server")),
	'serverport'	=> array('label' => $ps_lang->trans("Port").':',		'val' => 'BN','statustext' => $ps_lang->trans("Port that the server is listening on")),
	'connectip'	=> array('label' => $ps_lang->trans("Connect IP").':',		'val' => '',  'statustext' => $ps_lang->trans("Some servers need a different 'connect' IP for Internet users. Set this if needed.")),
	'query'		=> array('label' => $ps_lang->trans("Query Type").':',		'val' => 'B', 'statustext' => $ps_lang->trans("How to query the server.")),
	'rcon'		=> array('label' => $ps_lang->trans("RCON Password").':',	'val' => '',  'statustext' => $ps_lang->trans("RCON Password; optional. Allows you to issue simple commands to the remote server from the stats site (admins only)")),
	'enabled'	=> array('label' => $ps_lang->trans("Enabled?").':',		'val' => 'BN','statustext' => $ps_lang->trans("If enabled, the server will appear in the 'Severs' tab in the stats.")),	
);

$act = strtolower($act);
$move = strtolower($move);
if (!is_numeric($id)) $id = 0;
if (!is_numeric($move)) $move = 0;

$servers = array();
$servers = $ps_db->fetch_rows(1, "SELECT *,INET_NTOA(serverip) _serverip FROM $ps->t_config_servers ORDER BY idx,serverip,serverport");

# export the data and exit
if ($export) {
	$list = $servers;
	// get the first item in the list so we can determine what keys are available
	$i = $list[0];
	unset($i['_serverip'], $i['serverip'], $i['serverport'], $i['id']);	// remove unwanted keys
	$keys = array_keys($i);					// get a list of the keys (no values)
	array_unshift($keys, 'serverip', 'serverport');		// make sure these are always the first key

	$csv = csv($keys);				// 1st row is always the key order
	foreach ($list as $i) {
		$set = array();
		foreach ($keys as $k) {			// we want to make sure our key order is the same
			$set[] = $i[$k];		// and we only use keys from the original $keys list
		}
		$csv .= csv($set);
	}

	// remove all pending output buffers first 
	while (@ob_end_clean());
	header("Pragma: no-cache");
	header("Content-Type: text/csv");
	header("Content-Length: " . strlen($csv));
	header("Content-Disposition: attachment; filename=\"ps-servers.csv\"");
	print $csv;
	exit();
}

$srv = array();
if ($id) {
	$srv = $ps_db->fetch_row(1, "SELECT *,INET_NTOA(serverip) _serverip FROM $ps->t_config_servers WHERE id='" . $ps_db->escape($id) . "'");
}

# re-order the list
if ($move and $srv['id']) {
	$list = $servers;
	$move = ($move > 0) ? 15 : -15;	

	$idx = 0;
	foreach ($list as $i => $a) {
		$list[$i]['idx'] = ++$idx * 10;
		if ($list[$i]['id'] == $id) $list[$i]['idx'] += $move;
	}
	usort($list, 'sortidx');

	$idx = 0;
	$ps_db->begin();
	foreach ($list as $i) {
		$ps_db->update($ps->t_config_servers, array('idx' => ++$idx), 'id', $i['id']);
	}
	$ps_db->commit();

	# update the array in memory so things sort correctly on the webpage
	$servers = $list;
}

# load the querytype's allowed by the PQ object.
$querytypes = pq_query_types();

$form = array();
$errors = array();

if ($submit and $del and $srv['id']) {
	$ps_db->delete($ps->t_config_servers, 'id', $srv['id']);
	previouspage(sprintf("$PHP_SELF?c=%s", urlencode($c)));

} elseif ($submit and $act == 'edit' and $_SERVER['REQUEST_METHOD'] == 'POST') {
	$form = packform($formfields);
	trim_all($form);

	// verify IP is valid
	$ip = ip2long($form['_serverip']);
	if ($ip === FALSE or $ip == -1) {
		$formfields['_serverip']['error'] = $ps_lang->trans("Invalid IP entered");
	}

	// verify port
	if ($form['serverport'] == '') {
		$form['serverport'] = '27015';
	}
	if (!is_numeric($form['serverport']) or $form['serverport'] < 1 or $form['serverport'] > 65535) {
		$formfields['serverport']['error'] = $ps_lang->trans("Port must be between 1 .. 65535");
	}

	// verify a valid querytype was given
	if (!array_key_exists($form['query'], $querytypes)) {
		$formfields['query']['error'] = $ps_lang->trans("Invalid query type selected");
	}

	// automatically verify all fields
	foreach ($formfields as $key => $ignore) {
		form_checks($form[$key], $formfields[$key]);
	}
	$errors = all_form_errors($formfields);

	if (!count($errors)) {
		$set = $form;
		unset($set['_serverip']);
		$set['serverip'] = sprintf("%u", ip2long($form['_serverip']));
		if (!$id) {
			$set['id'] = $ps_db->next_id($ps->t_config_servers);
		}

		$ok = 0;
		$ps_db->begin();
		if ($id) {
			$ok = $ps_db->update($ps->t_config_servers, $set, 'id', $id);
		} else {
			$ok = $ps_db->insert($ps->t_config_servers, $set);
		}

		if ($ok) {
			$ps_db->commit();
			previouspage(ps_url_wrapper(array( 'c' => $c )));
		} else {
			$errors['fatal'] = $ps_lang->trans("Error updating database") . "<br/>" . $ps_db->errstr;
			$ps_db->rollback();
		}
	}
	$data += $form;	
	$data['adminpage'] = 'servers_edit';
} elseif ($act == 'edit') {
	$data += $srv;
	$data['adminpage'] = 'servers_edit';
} elseif ($submit and $new) {
	$data += array( 'enabled' => 1 );
	$data['adminpage'] = 'servers_edit';
}

$data['form'] = $formfields;
$data['errors'] = $errors;
$data['servers'] = $servers;
$data['server'] = $srv;
$data['id'] = $id;
$data['act'] = $act;
$data['querytypes'] = $querytypes;


?>

