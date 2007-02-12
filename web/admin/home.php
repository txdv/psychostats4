<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if ($register_admin_controls) {
	return 1;
}

session_close();	// save the current session (so it'll be up-to-date below)

$list = array();
$userlist = array();
$guestlist = array();
$list = load_online_users();
foreach ($list as $s) {
	unset($s['password'], $s['session_id']);	// we do not want these passed to the theme
	$s['session_totaltime'] = $s['session_last'] - $s['session_start'];
	$s['session_idle'] = time() - $s['session_last'];
	$s['session_bot_name'] = $s['session_is_bot'] ? session_bot_name($s['session_is_bot']) : '';
	if ($s['userid']) {
#		if ($userlist[ $s['userid'] ]) {	// ignore duplicate users?
#			continue;
#		}
#		$userlist[ $s['userid'] ] = $s;

		$userlist[] = $s;
	} else {
		$guestlist[] = $s;
	}
}
unset($list);


$data['install_dir_readable'] = @is_readable(catfile(dirname(dirname(__FILE__)), 'install'));


$data['totalsessions'] = count($userlist) + count($guestlist);
$data['userlist'] = $userlist;
$data['guestlist'] = $guestlist;

?>
