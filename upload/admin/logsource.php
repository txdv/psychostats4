<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

include_once("_logsource.php");

if ($register_admin_controls) {
	$menu =& $PSAdminMenu->getSection( $ps_lang->trans("Logsources") );

	$opt =& $menu->newOption( " " . $ps_lang->trans("Manage"), 'logsource' );
	$opt->link(ps_url_wrapper(array('c' => 'logsource')));

	$opt =& $menu->newOption( $ps_lang->trans("File settings"), 'config_logsource_file' );
	$opt->link(ps_url_wrapper(array('c' => 'config', 't' => 'logsource_file')));
	$opt =& $menu->newOption( $ps_lang->trans("FTP settings"), 'config_logsource_ftp' );
	$opt->link(ps_url_wrapper(array('c' => 'config', 't' => 'logsource_ftp')));
	$opt =& $menu->newOption( $ps_lang->trans("SFTP settings"), 'config_logsource_sftp' );
	$opt->link(ps_url_wrapper(array('c' => 'config', 't' => 'logsource_sftp')));

	return 1;
}

if ($cancel) previouspage("admin.php?c=".urlencode($c));

$validfields = array('id','act','new','move','t');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

$data['PS_ADMIN_PAGE'] = "logsource";

$validprotocols = array( 'file' => '', 'ftp' => 'FTP', 'ftp_pasv' => 'FTP (passive)', 'sftp' => 'SFTP' );
$protocolport = array( 'file' => '', 'ftp' => '21', 'ftp_pasv' => '21', 'sftp' => '22' );

// form fields ...
$formfields = array(
	// act = 'edit'
	'updatestate'	=> array('label' => $ps_lang->trans("Update State").':', 	'val' => '',  'statustext' => $ps_lang->trans("Should the current state for this logsource be updated to match?")),
	'protocol'	=> array('label' => $ps_lang->trans("Protocol").':', 		'val' => '',  'statustext' => $ps_lang->trans("If logs are remote what protocol should be used to connect")),
	'path'		=> array('label' => $ps_lang->trans("Path").':', 		'val' => 'B', 'statustext' => $ps_lang->trans("Path to game logs")),
	'host'		=> array('label' => $ps_lang->trans("Host").':', 		'val' => '',  'statustext' => $ps_lang->trans("Server Host (or IP) to login to")),
	'port'		=> array('label' => $ps_lang->trans("Port").':', 		'val' => '',  'statustext' => $ps_lang->trans("Server Port (leave blank for default)")),
	'username'	=> array('label' => $ps_lang->trans("Username").':',	 	'val' => '',  'statustext' => $ps_lang->trans("Username to login with")),
	'blankpassword'	=> array('label' => $ps_lang->trans("Blank Password").':',	'val' => '',  'statustext' => $ps_lang->trans("If checked no password is required")),
	'password'	=> array('label' => $ps_lang->trans("Password").':',		'val' => '',  'statustext' => $ps_lang->trans("Password to login with")),
	'password2'	=> array('label' => $ps_lang->trans("Retype Password").':', 	'val' => '',  'statustext' => $ps_lang->trans("Retype Password")),

	// act = 'del'
	'deletestate'	=> array('label' => $ps_lang->trans("Delete State").':',	'val' => '',  'statustext' => $ps_lang->trans("Should the state information be deleted for this logsource too? ")),
	
);

$act = strtolower($act);
$move = strtolower($move);
if (!is_numeric($id)) $id = 0;
if (!is_numeric($move)) $move = 0;
//if (!in_array($move, array('d', 'u'))) $move = '';

// load all logsources and break them apart for editing
$logsources = array();
$sourcelist = $ps_db->fetch_rows(1, "SELECT id,idx,value logsource FROM $ps->t_config WHERE conftype='main' AND var='logsource' ORDER BY idx,id");
$idx = 0;
foreach ($sourcelist as $logsource) {
	$s = $logsource + parsesource($logsource['logsource']);
	$s['idx'] = ++$idx * 10;
	$logsources[ $logsource['id'] ] = $s;
}

// re-order the logsources
if ($move and array_key_exists($id, $logsources)) {
	$move = ($move > 0) ? 15 : -15;	
	$logsources[$id]['idx'] += $move;
	usort($logsources, 'sortidx');
	$idx = 0;
	$ps_db->begin();
	foreach ($logsources as $s) {
		$ps_db->update($ps->t_config, array('idx' => ++$idx), 'id', $s['id']);
	}
	$ps_db->commit();
}

$form = array();
$errors = array();

if ($submit and $act == 'edit' and $_SERVER['REQUEST_METHOD'] == 'POST') {
	$form = packform($formfields);
	trim_all($form);

	// automatically verify all fields
	foreach ($formfields as $key => $ignore) {
		form_checks($form[$key], $formfields[$key]);
	}

	if ($form['protocol'] == '') $form['protocol'] = 'file';	// default to 'file' if it's blank
	if (!array_key_exists($form['protocol'], $validprotocols)) {
		$formfields['protocol']['error'] = $ps_lang->trans("Invalid protocol specified");
	}

	if ($form['protocol'] != 'file') {
		if ($form['port'] != '' and (!is_numeric($form['port']) or $form['port'] < 1 or $form['port'] > 65535)) {
			$formfields['port']['error'] = $ps_lang->trans("Must be a valid port");
		}
		if (!$form['host']) {
			$formfields['host']['error'] = $ps_lang->trans("Must specify a host");
		}
		if (!$form['username']) {
			$formfields['username']['error'] = $ps_lang->trans("Must specify a username");
		}
		if (!$form['blankpassword']) {
			if (!$form['password']) {
				if (!$id) {
					$formfields['password']['error'] = $ps_lang->trans("You must specify a password");
				} else {
					$form['password'] = $logsources[$id]['password'];
				}
			} elseif ($form['password'] != $form['password2']) {
				$formfields['password']['error'] = $ps_lang->trans("Passwords do not match");
				$formfields['password2']['error'] = $ps_lang->trans("Please try again");
			}
		}
	}

	$errors = all_form_errors($formfields);

	if (!count($errors)) {
		$set = array( 'conftype' => 'main', 'var' => 'logsource');
		if (!$id) {
			$set['id'] = $ps_db->next_id($ps->t_config);
			$set['idx'] = $ps_db->max($ps->t_config, 'idx', "conftype='main' AND var='logsource'") + 1;
		}
		if ($form['protocol'] == 'file') {
			$set['value'] = $form['path'];
		} else {
			$set['value'] = sprintf("%s://%s%s@%s%s/%s", 
				$form['protocol'], 
				$form['username'],
				$form['password'] ? ":" . $form['password'] : '',
				$form['host'],
				$form['port'] ? ":" . $form['port'] : '',
				$form['path']
			);
		}

		$ok = 0;
		$ps_db->begin();
		if ($id) {
			$ok = $ps_db->update($ps->t_config, $set, 'id', $id);
			if ($form['updatestate']) {
				$safe = parsesource($set['value']);
				$ps_db->update($ps->t_state, array('logsource' => $safe['safelogsource']), 'logsource', 
					$logsources[$id]['safelogsource']
				);
			}
		} else {
			$ok = $ps_db->insert($ps->t_config, $set);
		}

		if ($ok) {
			$ps_db->commit();
			previouspage("admin.php?c=".urlencode($c));
		} else {
			$ps_db->rollback();
		}
	}
	$data += $form;	
	$data['adminpage'] = 'logsource_edit';

} elseif ($act == 'edit') {	// assign defaults for the form
	if (array_key_exists($id, $logsources)) {
		$data += $logsources[$id];
		$stateid = $ps_db->select_row($ps->t_state, 'id', 'logsource', $logsources[$id]['safelogsource']);
		$data['updatestate'] = $stateid ? 1 : 0;
		$data['stateid'] = $stateid;
	} else {
		$data['protocol'] = 'file';
	}
	$data['adminpage'] = 'logsource_edit';

} elseif ($submit and $act == 'del') {
	if (array_key_exists($id, $logsources)) {
		$ps_db->begin();
		$ps_db->delete($ps->t_config, 'id', $id);
		list($stateid) = $ps_db->select_row($ps->t_state, 'id', 'logsource', $logsources[$id]['safelogsource']);
		if ($stateid) {
			$ps_db->delete($ps->t_state_plrs, 'id', $stateid);
			$ps_db->delete($ps->t_state, 'id', $stateid);
		}
		$ps_db->optimize(array( $ps->t_config, $ps->t_state, $ps->t_state_plrs ));
		$ps_db->commit();
	}
	previouspage("admin.php?c=" . urlencode($c));

} elseif ($act == 'del') {
	if (array_key_exists($id, $logsources)) {
		$data += $logsources[$id];
		$data['deletestate'] = 1;
		$state = $ps_db->fetch_row(1, "SELECT * FROM $ps->t_state WHERE logsource='" . 
			$ps_db->escape($logsources[$id]['safelogsource']) . "'");
		if ($state) {
			$state['totalplayers'] = $ps_db->count($ps->t_state_plrs, "*", "id=".$ps_db->escape($state['id']));
//			$state['lastupdatediff'] = time() - $state['lastupdate'];
		}
		$data['state'] = $state;
	} else {
		gotopage("admin.php?c=" . urlencode($c));
	}	
	$data['adminpage'] = 'logsource_del';
}

$data['form'] = $formfields;
$data['logsources'] = $logsources;
$data['id'] = $id;
$data['act'] = $act;
$data['validprotocols'] = $validprotocols;

?>
