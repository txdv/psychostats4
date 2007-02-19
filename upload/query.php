<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . "/includes/common.php");

$validfields = array('themefile','ip','cmd');
globalize($validfields);

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'query';

$rulefilters = array('_tutor_','coop','deathmatch','pausable');

list($ip,$port) = explode(':', $ip);

$server = array();
$server = $ps_db->fetch_row(1, 
	"SELECT s.*,INET_NTOA(serverip) ip, serverport port, CONCAT_WS(':', INET_NTOA(serverip),serverport) ipport " . 
	"FROM $ps->t_config_servers s " . 
	"WHERE enabled=1 " . 
	"AND s.serverip=INET_ATON('" . $ps_db->escape($ip) . "') AND serverport='" . $ps_db->escape($port) . "' "
);

if ($server['id']) {
	// resolve server hostname to an IP (and separate the port)
	if (!preg_match('|^\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}$|', $ip)) {
		$ip = gethostbyname($ip);
	}
	$row = $ps_db->fetch_row(1, "SELECT c.cc, c.cn FROM $ps->t_geoip_ip ip, $ps->t_geoip_cc c WHERE c.cc=ip.cc AND (" . sprintf("%u", ip2long($ip)) . " BETWEEN start AND end)");
	if ($row) $server += $row;
} else {
	abort('nomatch', $ps_lang->trans("Invalid Server"), $ps_lang->trans("Unauthorized server specified"));
}

$data['cmd'] = $cmd;
$data['server'] = $server;
$data['pq'] = array();
if ($server['ip']) {
	$pq = PQ::create($server + array('timeout' => 1, 'retries' => 2));
	$pqinfo = $pq->query(array('info','players','rules'));
	if ($pqinfo === FALSE) $pqinfo = array();
	if ($pqinfo) {
		$pqinfo['connect_url'] = $pq->connect_url($server['connectip']);
		if ($pqinfo['players']) usort($pqinfo['players'], 'killsort');
		if ($pqinfo['rules']) $pqinfo['rules'] = filter_rules($pqinfo['rules'], $rulefilters);
	} else {
		$pqinfo['timedout'] = 1;
	}
	$data['pq'] = $pqinfo;
	$data['pqobj'] = $pq;
#	$smarty->register_object('pqobj',$pq);

	// If we have an RCON command to send (and the user is an admin)
	$rcon_result = '';
	if (user_is_admin() and !empty($cmd)) {
		if (!empty($server['rcon'])) {
			$rcon_result = $pq->rcon($cmd, $server['rcon']);
		}
	}

	$data['rcon_result'] = $rcon_result;
}

function killsort($a, $b) {
  if ($a['kills'] == $b['kills']) return onlinesort($a,$b);	// sort by onlinetime if the kills are equal
//  if ($a['kills'] == $b['kills']) return 0;			// remove the above line if 'killsort' is not the original sort!
  return ($a['kills'] > $b['kills']) ? -1 : 1;
}

function onlinesort($a, $b) {
  if ($a['onlinetime'] == $b['onlinetime']) return 0;
  return ($a['onlinetime'] > $b['onlinetime']) ? -1 : 1;
}

function filter_rules($orig, $rulefilters=array()) {
	$ary = array();
	if (!$rulefilters) return $orig;
	foreach ($orig as $rule => $value) {
		$match = 0;
		foreach ($rulefilters as $filter) {
			if (empty($filter)) continue;
			if (preg_match("/$filter/", $rule)) {
				$match++;
				break;
			}
		}
		if (!$match) $ary[$rule] = $value;
	}
	return $ary;
}

$data['PAGE'] = 'query';
$smarty->assign($data);
$smarty->parse($themefile);
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . "/includes/footer.php");
?>
.
