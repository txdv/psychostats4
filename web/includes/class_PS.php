<?php
if (defined("CLASS_PS_PHP")) return 1;
define("CLASS_PS_PHP", 1);

define("PS_VERSION", "3.0");
define("PS_VERSION_STATUS", "alpha");

class PS {

function PS(&$_db) {
	$this->db 		=& $_db;		// don't make a copy
	$this->explained 	= array();
	$this->conf		= array();
	$this->confid		= array();

	$this->tblprefix = $_db->dbtblprefix;

	// normal tables ...
	$this->t_awards			= $this->tblprefix . 'awards';
	$this->t_awards_plrs		= $this->tblprefix . 'awards_plrs';
	$this->t_clan 			= $this->tblprefix . 'clan';
	$this->t_clan_profile 		= $this->tblprefix . 'clan_profile';
	$this->t_config 		= $this->tblprefix . 'config';
	$this->t_config_awards 		= $this->tblprefix . 'config_awards';
	$this->t_config_clantags 	= $this->tblprefix . 'config_clantags';
	$this->t_config_layout 		= $this->tblprefix . 'config_layout';
	$this->t_config_plrbans 	= $this->tblprefix . 'config_plrbans';
	$this->t_config_plrbonuses 	= $this->tblprefix . 'config_plrbonuses';
	$this->t_errlog 		= $this->tblprefix . 'errlog';
	$this->t_geoip_cc		= $this->tblprefix . 'geoip_cc';
	$this->t_geoip_ip		= $this->tblprefix . 'geoip_ip';
	$this->t_map 			= $this->tblprefix . 'map';
	$this->t_map_data 		= $this->tblprefix . 'map_data';
	$this->t_plr 			= $this->tblprefix . 'plr';
	$this->t_plr_aliases 		= $this->tblprefix . 'plr_aliases';
	$this->t_plr_data 		= $this->tblprefix . 'plr_data';
	$this->t_plr_ids 		= $this->tblprefix . 'plr_ids';
	$this->t_plr_maps 		= $this->tblprefix . 'plr_maps';
	$this->t_plr_profile 		= $this->tblprefix . 'plr_profile';
	$this->t_plr_roles 		= $this->tblprefix . 'plr_roles';
	$this->t_plr_sessions 		= $this->tblprefix . 'plr_sessions';
	$this->t_plr_victims 		= $this->tblprefix . 'plr_victims';
	$this->t_plr_weapons 		= $this->tblprefix . 'plr_weapons';
	$this->t_role 			= $this->tblprefix . 'role';
	$this->t_role_data		= $this->tblprefix . 'role_data';
	$this->t_search 		= $this->tblprefix . 'search';
	$this->t_sessions 		= $this->tblprefix . 'sessions';
	$this->t_state 			= $this->tblprefix . 'state';
	$this->t_state_plrs		= $this->tblprefix . 'state_plrs';
	$this->t_user 			= $this->tblprefix . 'user';
	$this->t_weapon 		= $this->tblprefix . 'weapon';
	$this->t_weapon_data 		= $this->tblprefix . 'weapon_data';

	// load our main config ...
	$this->load_config(array('main','theme','info'));

	$this->tblsuffix = '_' . $this->conf['main']['gametype'] . '_' . $this->conf['main']['modtype'];

	// compiled player/game tables
	$this->c_map_data	= $this->tblprefix . 'c_map_data';
	$this->c_plr_data	= $this->tblprefix . 'c_plr_data';
	$this->c_plr_maps	= $this->tblprefix . 'c_plr_maps';
	$this->c_plr_roles	= $this->tblprefix . 'c_plr_roles';
	$this->c_plr_victims	= $this->tblprefix . 'c_plr_victims';
	$this->c_plr_weapons	= $this->tblprefix . 'c_plr_weapons';
	$this->c_weapon_data	= $this->tblprefix . 'c_weapon_data';
	$this->c_map_data	= $this->tblprefix . 'c_map_data';
	$this->c_role_data	= $this->tblprefix . 'c_role_data';

	$this->use_role = FALSE;

	include(dirname(__FILE__) . '/PS/PS.php');
}

function search_players($args) {
	$args += array(
		'sid'		=> session_sid(),
		'search'	=> '',
		'logic'		=> 'and',
		'ranked'	=> 1,
		'ignoresingle'	=> false,
		'limit'		=> 0,
		'where'		=> '',
	);
	$list = array();
	$res = array();
	$output = array();
	$output2 = array();
	$ranked = $args['ranked'] ? TRUE : FALSE;
	$limit = $args['limit'];
	$where = $args['where'];
	$logic = strtolower(in_array(strtolower($args['logic']), array('or','and','exact')) ? $args['logic'] : 'and');
	$arr = explode('"',$args['search']);

	// not the most perfect method of dealing with double quoted srings... but it works for my needs
	for ($i=0; $i < count($arr); $i++) {
		if ($i % 2 == 0) {
			$output = array_merge($output, explode(" ", $arr[$i]));
		} else {
			$output[] = $arr[$i];
		}
	}
	foreach($output as $word) {
		if (trim($word) != "") $output2[] = $word;
	}
	$words = array_unique($output2);
	$phrase = implode(' ', $words);

	// search for a match of the phrase against player profile names
	$cmd  = "SELECT DISTINCT p.plrid FROM $this->t_plr_profile pp, $this->t_plr p ";
	$cmd .= "WHERE pp.name LIKE '%" . $this->db->escape($phrase) . "%' AND pp.uniqueid=p.uniqueid ";
	if ($ranked) $cmd .= "AND p.allowrank=1 ";
	if ($where) $cmd .= "AND $where ";
	if ($limit) $cmd .= "LIMIT $limit";
	$res = $this->db->fetch_list($cmd);
	$list = array_merge($list, $res);
#	print "$cmd<BR><BR>";

	// try a match for the phrase given from plr_ids and profile name
	// Do not perform the match if we already have a SINGLE match
	if (($args['ignoresingle'] or count($res) != 1) and (!$limit or ($limit and count($res) < $limit))) {
		// The c_plr_data table is included so that only players with compiled stats are returned in the result
		$cmd  = "SELECT DISTINCT i.plrid FROM $this->t_plr_ids i, $this->t_plr p, $this->c_plr_data c ";
		$cmd .= "WHERE p.plrid=i.plrid AND c.plrid=p.plrid AND ";
		if ($ranked) $cmd .= "p.allowrank=1 AND ";
		$cmd .= "( i.name LIKE '%" . $this->db->escape($phrase) . "%' OR ";
		$cmd .= "i.worldid LIKE '%" . $this->db->escape($phrase) . "%' ";
		if (preg_match('|^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$|', $phrase)) {
			$cmd .= " OR i.ipaddr = '" . sprintf("%u", ip2long($phrase)) . "' ";
		}
		$cmd .= ") ";
		if ($where) $cmd .= "AND $where ";
#		if ($limit) $cmd .= "LIMIT " . ($limit - count($res));
		if ($limit) $cmd .= "LIMIT $limit ";
#		print "$cmd<BR><BR>";
		$res = $this->db->fetch_list($cmd);
		$list = array_merge($list, $res);
	}

	$plrs = array_unique($list);
	sort($plrs);
	if ($limit) $plrs = array_slice($plrs, 0, $limit);

	// delete any previous queries older than 4 hours or matching the current sid
	$max = time() - (60*60*4);
	$this->db->query("DELETE FROM $this->t_search WHERE session_id='" . $this->db->escape($args['sid']) . "' OR time < $max");

	// save the results...
	$this->db->insert($this->t_search, array(
		'session_id'	=> $args['sid'],
		'query'		=> $phrase,
		'time'		=> time(),
		'results'	=> join(',', $plrs),
	));
	$this->db->optimize($this->t_sessions);

	return count($plrs);
}

function del_search_results($sid=NULL) {
	if ($sid === NULL) $sid = session_sid();
	return $this->db->delete($this->t_search, 'session_id', $sid);
}

function get_search_results($sid=NULL) {
	if ($sid === NULL) $sid = session_sid();
	$sid = $this->db->escape($sid);
	$res = $this->db->fetch_row(1,"SELECT * FROM $this->t_search WHERE session_id='$sid' LIMIT 1");
	return $res ? $res : array();
}

function get_player($args, &$s) {
	$args += array(
		'var'		=> '',
		'plrid'		=> 0,
		'loadsessions'	=> 0,		// do not want sessions by default
		'loadmaps'	=> 1,
		'loadroles'	=> $this->use_roles,	// default is based on the MODTYPE
		'loadweapons'	=> 1,
		'loadvictims'	=> 1,
		'loadids'	=> 1,
		'loadipaddrs'	=> 1,		// it's possible to hide IP addresses
		'loadgeoinfo'	=> 1,
		'loadcounts'	=> 1,
		'sessionsort'	=> 'kills',
		'sessionorder'	=> 'desc',
		'sessionstart'	=> 0,
		'sessionlimit'	=> 50,
		'weaponsort'	=> 'kills',
		'weaponorder'	=> 'desc',
		'weaponstart'	=> 0,
		'weaponlimit'	=> 50,
		'mapsort'	=> 'kills',
		'maporder'	=> 'desc',
		'mapstart'	=> 0,
		'maplimit'	=> 50,
		'rolesort'	=> 'kills',
		'roleorder'	=> 'desc',
		'rolestart'	=> 0,
		'rolelimit'	=> 50,
		'victimsort'	=> 'kills',
		'victimorder'	=> 'desc',
		'victimstart'	=> 0,
		'victimlimit'	=> 25,
		'idsort'	=> 'totaluses',
		'idorder'	=> 'desc',
		'idstart'	=> 0,
		'idlimit'	=> 255,
	);
	$plr = array();
	$id = $this->db->escape($args['plrid']);
	if (!is_numeric($id)) $id = 0;

	// Load overall player information
	$cmd  = "SELECT data.*,plr.*,pp.*,c.cn FROM ($this->c_plr_data as data, $this->t_plr as plr, $this->t_plr_profile pp) ";
	$cmd .= "LEFT JOIN $this->t_geoip_cc c ON c.cc=pp.cc ";
	$cmd .= "WHERE plr.plrid=data.plrid AND plr.plrid='$id' AND plr.uniqueid=pp.uniqueid ";
	$cmd .= "LIMIT 1 ";
	$plr = $this->db->fetch_row(1, $cmd);

	// Load player clan information
	if ($plr['clanid']) {
		$cmd  = "SELECT clan.*,cp.* FROM $this->t_clan clan, $this->t_clan_profile cp ";
		$cmd .= "WHERE clanid='" . $this->db->escape($plr['clanid']) . "' AND clan.clantag=cp.clantag LIMIT 1";
		$plr['clan'] = $this->db->fetch_row(1, $cmd);
		$plr['clan']['totalmembers'] = $this->db->count($this->t_plr, '*', "clanid='" . $this->db->escape($plr['clanid']) . "'");
	} else {
		$plr['clan'] = array();
	}

	if ($args['loadcounts']) {
		if ($this->conf['main']['plr_save_victims']) {
			$plr['totalvictims'] 	= $this->db->count($this->c_plr_victims, '*', "plrid='$id'");
		}
		$plr['totalmaps'] 	= $this->db->count($this->c_plr_maps, '*', "plrid='$id'");
		$plr['totalweapons'] 	= $this->db->count($this->c_plr_weapons, '*', "plrid='$id'");
		$plr['totalroles'] 	= $this->db->count($this->c_plr_roles, '*', "plrid='$id'");
		$plr['totalids'] 	= $this->db->count($this->t_plr_ids, '*', "plrid='$id'");
		$plr['totalsessions'] 	= $this->db->count($this->t_plr_sessions, '*', "plrid='$id'");
		$plr['totalawards'] 	= $this->db->count($this->t_awards_plrs, '*', "plrid='$id'");
	}

	if ($args['loadsessions']) {
		$plr['sessions'] = $this->get_player_sessions(array(
			'plrid'		=> $args['plrid'],
			'start'		=> $args['sessionstart'],
			'limit'		=> $args['sessionlimit'],
			'sort'		=> $args['sessionsort'],
			'order'		=> $args['sessionorder'],
		), $s);
	}

	// Load weapons for the player
	if ($args['loadweapons']) {
		$cmd  = "SELECT data.*,w.* FROM $this->c_plr_weapons AS data, $this->t_weapon AS w ";
		$cmd .= "WHERE data.plrid='$id' AND w.weaponid=data.weaponid ";
		$cmd .= $this->_getsortorder($args, 'weapon');
		$plr['weapons'] = $this->db->fetch_rows(1, $cmd);
	}

	// Load roles for the player
	if ($args['loadroles']) {
		$cmd  = "SELECT data.*,r.* FROM $this->c_plr_roles AS data, $this->t_role AS r ";
		$cmd .= "WHERE data.plrid='$id' AND r.roleid=data.roleid ";
		$cmd .= $this->_getsortorder($args, 'role');
		$plr['roles'] = $this->db->fetch_rows(1, $cmd);
	}

	// Load maps for the player
	if ($args['loadmaps']) {
		$cmd  = "SELECT data.*,m.* FROM $this->c_plr_maps AS data, $this->t_map AS m ";
		$cmd .= "WHERE data.plrid='$id' AND m.mapid=data.mapid ";
		$cmd .= $this->_getsortorder($args, 'map');
		$plr['maps'] = $this->db->fetch_rows(1, $cmd);
	}

	// Load victim for the player
	if ($args['loadvictims'] and $this->conf['main']['plr_save_victims']) {
		$cmd  = "SELECT plr.*,pp.*,v.* FROM $this->c_plr_victims AS v, $this->t_plr as plr, $this->t_plr_profile pp ";
		$cmd .= "WHERE v.plrid='$id' AND v.victimid=plr.plrid AND pp.uniqueid=plr.uniqueid ";
		$cmd .= $this->_getsortorder($args, 'victim');
		$plr['victims'] = $this->db->fetch_rows(1, $cmd);
	}

	// Load player identities. Note: The order of ids is ALWAYS based on totaluses (descending)
	if ($args['loadids']) {
		$cmd  = "SELECT * FROM $this->t_plr_ids WHERE plrid='$id' ";
		$cmd .= $this->_getsortorder($args, 'id');
		$list = $this->db->fetch_rows(1, $cmd);
		$plr['ids'] = array();
		$first = -1;
		foreach ($list as $ident) {
			// the name with the lowest ID will be the 'first' name ever seen for this player
			if ($first == -1 or $ident['id'] < $first) {
				$first = $ident['id'];
				$plr['ids']['first']['name'] = $ident['name'];
				$plr['ids']['first']['worldid'] = $ident['worldid'];
				$plr['ids']['first']['ipaddr'] = $ident['ipaddr'];
			}
			$plr['ids']['names'][ $ident['name'] ] += $ident['totaluses'];
			$plr['ids']['worldids'][ $ident['worldid'] ] += $ident['totaluses'];
			if ($args['loadipaddrs']) $plr['ids']['ipaddrs'][ $ident['ipaddr'] ] += $ident['totaluses'];
		}
		if ($plr['ids']['names']) arsort($plr['ids']['names']);
		if ($plr['ids']['worldids']) arsort($plr['ids']['worldids']);
		if ($args['loadipaddrs'] and $plr['ids']['ipaddrs']) {
			if ($plr['ids']['ipaddrs']) arsort($plr['ids']['ipaddrs']);
			$plr['iplist'] = array_filter(array_map('long2ip', array_keys($plr['ids']['ipaddrs'])), array($this,'not0'));
			// 10 ip's maximum
			$plr['iplist'] = is_array($plr['iplist']) ? array_slice($plr['iplist'], 0, 10) : array();
		}
//		print "<pre>"; print_r($plr['ids']); die;
	}

	// geocode IP addresses. Only does the first 5 IP's. Lets not overload my server with lookups.
/*
	$plr['geoips'] = array();
	if ($this->conf['theme']['map']['google_key'] and $args['loadipaddrs'] and $args['loadgeoinfo']) {
		foreach ($plr['ids']['ipaddrs'] as $int => $total) {
			$ip = long2ip($int);
			global $TIMER;
#			$TIMER->addmarker('1');
			$h = $this->ip_lookup($ip);
#			$TIMER->addmarker('2');
#			print $TIMER->timediff('1','2') . "<br>";
			if (!$h['x-known-ip']) continue;
			$lng = $h['x-longitude'];
			$lat = $h['x-latitude'];
			$plr['geoips'][] = array('ip' => $ip, 'lng' => $lng, 'lat' => $lat);
			if (count($plr['geoips']) >= 5) break;
		}
	}
*/

	if (!$args['var'] || !$s) return $plr;
	$s->assign($args['var'], $plr);
}
function not0($a) { return ($a != '0.0.0.0'); }

function get_player_sessions($args, &$s) {
	$args += array(
		'var'		=> '',
		'plrid' 	=> 0,
		'sort'		=> 'sessionstart',
		'order'		=> 'desc',
		'start'		=> 0,
		'limit'		=> 10,
		'fields'	=> '',
	);

	$fields = !empty($args['fields']) ? split(',',$args['fields']) : array_keys($this->PLR_SESSIONS_TYPES);
	$values = $this->_calcvalues($fields, $this->PLR_SESSIONS_TYPES);
	$cmd  = "SELECT $values, sessionend-sessionstart AS sessionlength FROM $this->t_plr_sessions ";
	$cmd .= "WHERE plrid='" . $this->db->escape($args['plrid']) . "'";
	$cmd .= $this->_getsortorder($args);
	$list = array();
	$list = $this->db->fetch_rows(1, $cmd);
	return $list;
}

function get_player_awards($args, &$s) {
	$args += array(
		'var'		=> '',
		'plrid' 	=> 0,
		'sort'		=> 'awardname',
		'order'		=> 'asc',
	);
	$cmd  = "SELECT ap.plrid,a.awardname,ap.value,a.awarddate FROM $this->t_awards_plrs ap, $this->t_awards a ";
	$cmd .= "WHERE a.id=ap.awardid AND ap.plrid='" . $this->db->escape($args['plrid']) . "'";
	$cmd .= $this->_getsortorder($args);
	$list = array();
	$list = $this->db->fetch_rows(1, $cmd);
	return $list;
}

function get_clan($args, &$s) {
	$args += array(
		'var'		=> '',
		'clanid'	=> 0,
		'fields'	=> '',
		'allowall'	=> 0,
		'loadmaps'	=> 1,
		'loadroles'	=> 1,
		'loadweapons'	=> 1,
		'loadmembers'	=> 1,
		'loadvictims'	=> 1,
		'membersort'	=> 'kills',
		'memberorder'	=> 'desc',
		'memberstart'	=> 0,
		'memberlimit'	=> 25,
		'memberfields'	=> '',
		'weaponsort'	=> 'kills',
		'weaponorder'	=> 'desc',
		'weaponstart'	=> 0,
		'weaponlimit'	=> 50,
		'weaponfields'	=> '',
		'mapsort'	=> 'kills',
		'maporder'	=> 'desc',
		'mapstart'	=> 0,
		'maplimit'	=> 50,
		'mapfields'	=> '',
		'rolesort'	=> 'kills',
		'roleorder'	=> 'desc',
		'rolestart'	=> 0,
		'rolelimit'	=> 50,
		'rolefields'	=> '',
		'victimsort'	=> 'kills',
		'victimorder'	=> 'desc',
		'victimstart'	=> 0,
		'victimlimit'	=> 25,
		'victimfields'	=> '',
	);
	$clan = array();
	$id = $this->db->escape($args['clanid']);
	if (!is_numeric($id)) $id = 0;

	$values = "clan.clanid,clan.locked,clan.allowrank,cp.*,COUNT(distinct plr.plrid) totalmembers, ROUND(AVG(skill),2) skill, ";

	$types = $this->get_types('CLAN');
	$fields = !empty($args['fields']) ? split(',',$args['fields']) : array_keys($types);
	$values .= $this->_values($fields, $types);

	$cmd  = "SELECT $values ";
	$cmd .= "FROM $this->c_plr_data data, $this->t_plr plr, $this->t_clan clan, $this->t_clan_profile cp ";
	$cmd .= "WHERE clan.clanid=$id AND plr.clanid=clan.clanid AND clan.clantag=cp.clantag AND data.plrid=plr.plrid ";
	if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
	if (trim($args['where']) != '') $cmd .= "AND (" . $args['where'] . ") ";
	$cmd .= "GROUP BY plr.clanid ";
	$cmd .= $this->_getsortorder($args);
	$clan = $this->db->fetch_row(1, $cmd);

	$cmd  = "SELECT COUNT(DISTINCT mapid) FROM $this->c_plr_maps data, $this->t_plr plr ";
	$cmd .= "WHERE plr.clanid='$id' AND plr.plrid=data.plrid ";
	if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
	$clan['totalmaps'] = $this->db->fetch_item($cmd);

	$cmd  = "SELECT COUNT(DISTINCT weaponid) FROM $this->c_plr_weapons data, $this->t_plr plr ";
	$cmd .= "WHERE plr.clanid='$id' AND plr.plrid=data.plrid ";
	if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
	$clan['totalweapons'] = $this->db->fetch_item($cmd);

	$cmd  = "SELECT COUNT(DISTINCT victimid) FROM $this->c_plr_victims data, $this->t_plr plr ";
	$cmd .= "WHERE plr.clanid='$id' AND plr.plrid=data.plrid ";
	if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
	$clan['totalvictims'] = $this->db->fetch_item($cmd);

	if ($args['loadmembers']) {
		$clan['members'] = $this->get_player_list(array(
			'where' => "plr.clanid='$id'",
			'sort'	=> $args['membersort'],
			'order' => $args['memberorder'],
			'start' => $args['memberstart'],
			'limit' => $args['memberlimit'],
			'fields'=> $args['memberfields'],
//			'allowall' => 1,
			'allowall' => $args['allowall'],
		),$s);
	}

	// Load weapons for the clan
	if ($args['loadweapons']) {
		$values = "w.*,";
		$fields = !empty($args['weaponfields']) ? split(',',$args['weaponfields']) : array_keys($this->CLAN_WEAPON_TYPES);
		$values .= $this->_values($fields, $this->CLAN_WEAPON_TYPES);
		$cmd  = "SELECT $values FROM $this->c_plr_weapons data, $this->t_weapon w, $this->t_plr plr ";
		$cmd .= "WHERE plr.plrid=data.plrid AND plr.clanid='$id' AND w.weaponid=data.weaponid ";
		if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
		$cmd .= "GROUP BY data.weaponid ";
		$cmd .= $this->_getsortorder($args, 'weapon');
		$clan['weapons'] = $this->db->fetch_rows(1, $cmd);
	}

	// Load maps for the clan
	if ($args['loadmaps']) {
		$values = "m.*,";
		$map_types = $this->get_types("CLAN_MAP");
		$fields = !empty($args['mapfields']) ? split(',',$args['mapfields']) : array_keys($map_types);
		$values .= $this->_values($fields, $map_types);
		$cmd  = "SELECT $values FROM $this->c_plr_maps data, $this->t_map m, $this->t_plr plr ";
		$cmd .= "WHERE plr.plrid=data.plrid AND plr.clanid='$id' AND m.mapid=data.mapid ";
		if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
		$cmd .= "GROUP BY data.mapid ";
		$cmd .= $this->_getsortorder($args, 'map');
		$clan['maps'] = $this->db->fetch_rows(1, $cmd);
	}

	// Load victim for the clan
	if ($args['loadvictims']) {
		$values = "v.*,vp.*,";
		$fields = !empty($args['victimfields']) ? split(',',$args['victimfields']) : array_keys($this->CLAN_VICTIM_TYPES);
		$values .= $this->_values($fields, $this->CLAN_VICTIM_TYPES);
		$cmd  = "SELECT $values FROM $this->c_plr_victims data, $this->t_plr v, $this->t_plr plr, $this->t_plr_profile vp ";
		$cmd .= "WHERE plr.plrid=data.plrid AND plr.clanid='$id' AND v.plrid=data.victimid AND vp.uniqueid=v.uniqueid ";
		if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
		$cmd .= "GROUP BY data.victimid ";
		$cmd .= $this->_getsortorder($args, 'victim');
		$clan['victims'] = $this->db->fetch_rows(1, $cmd);
//		print "explain " . $this->db->lastcmd . ";";
	}

/*
    // Load roles for the player
    if ($args['loadroles']) {
      $cmd  = "SELECT data.*,roleid,def.name,def.desc FROM ($this->tblplrroles AS data) ";
      $cmd .= "LEFT JOIN $this->tbldefroles AS def ON def.id=data.roleid ";
      $cmd .= "WHERE data.plrid='$id' ";
      $cmd .= $this->_getsortorder($args, 'role');
      $plr['roles'] = $this->db->fetch_rows(1, $cmd);
    }
*/

    if (!$args['var'] || !$s) return $clan;
    $s->assign($args['var'], $clan);
}

function get_weapon($args, &$s) {
	$args += array(
		'var'		=> '',
		'weaponid'	=> 0,
		'fields'	=> '',
	);
	$id = $args['weaponid'];
	if (!is_numeric($id)) $id = 0;

	$fields = $args['fields'] ? $args['fields'] : "data.*";

	$cmd  = "SELECT $fields, def.* ";
	$cmd .= "FROM $this->c_weapon_data as data, $this->t_weapon as def ";
	$cmd .= "WHERE data.weaponid=def.weaponid AND data.weaponid=" . $this->db->escape($id) . " ";
	$cmd .= "LIMIT 1";
	$weapon = $this->db->fetch_row(1, $cmd);

	if (!$args['var'] || !$s) return $weapon;
	$s->assign($args['var'], $weapon);
}

function get_role($args, &$s) {
	$args += array(
		'var'		=> '',
		'roleid'	=> 0,
		'fields'	=> '',
	);
	$id = $args['roleid'];
	if (!is_numeric($id)) $id = 0;

	$fields = $args['fields'] ? $args['fields'] : "data.*";

	$cmd  = "SELECT $fields, def.* ";
	$cmd .= "FROM $this->c_role_data as data, $this->t_role as def ";
	$cmd .= "WHERE data.roleid=def.roleid AND data.roleid=" . $this->db->escape($id) . " ";
	$cmd .= "LIMIT 1";
	$role = $this->db->fetch_row(1, $cmd);

	if (!$args['var'] || !$s) return $role;
	$s->assign($args['var'], $role);
}

function get_award($args, &$s) {
	$args += array(
		'var'		=> '',
		'id'		=> 0,
//		'fields'	=> '',
	);
	$id = $args['id'];
	if (!is_numeric($id)) $id = 0;
//	$fields = $args['fields'] ? $args['fields'] : "data.*";

	$cmd  = "SELECT a.*, ac.enabled, ac.type, ac.class, ac.expr, ac.order, ac.limit, ac.format, ac.desc, plr.*, pp.* ";
	$cmd .= "FROM ($this->t_awards a, $this->t_config_awards ac) ";
	$cmd .= "LEFT JOIN $this->t_plr plr ON plr.plrid=a.topplrid ";
	$cmd .= "LEFT JOIN $this->t_plr_profile pp ON pp.uniqueid=plr.uniqueid ";
	$cmd .= "WHERE a.awardid=ac.id AND a.id='" . $this->db->escape($id) . "' ";
	$cmd .= "LIMIT 1";
	$award = $this->db->fetch_row(1, $cmd);
//	print $this->db->lastcmd;

	if (!$args['var'] || !$s) return $award;
	$s->assign($args['var'], $award);
}

function get_map($args, &$s) {
	$args += array(
		'var'		=> '',
		'mapid'		=> 0,
		'fields'	=> '',
	);
	$id = $args['mapid'];
	if (!is_numeric($id)) $id = 0;

	$fields = $args['fields'] ? $args['fields'] : "data.*";

	$cmd  = "SELECT $fields, def.* ";
	$cmd .= "FROM $this->c_map_data as data, $this->t_map as def ";
	$cmd .= "WHERE data.mapid=def.mapid AND data.mapid=" . $this->db->escape($id) . " ";
	$cmd .= "LIMIT 1";
	$map = $this->db->fetch_row(1, $cmd);

	if (!$args['var'] || !$s) return $map;
	$s->assign($args['var'], $map);
}

function get_award_player_list($args, &$s) {
	$args += array(
		'var'		=> '',
		'id'		=> 0,
		'fields'	=> '',
		'where'		=> '',
		'sort'		=> 'idx',
		'order'		=> 'desc',
		'start'		=> 0,
		'limit'		=> 10,
	);
	$id = $args['id'];
	if (!is_numeric($id)) $id = 0;
	$fields = $args['fields'] ? $args['fields'] : "ap.*, ac.format, ac.desc, plr.*, pp.*";

	$cmd  = "SELECT $fields ";
	$cmd .= "FROM ($this->t_awards_plrs ap, $this->t_awards a, $this->t_config_awards ac) ";
	$cmd .= "LEFT JOIN $this->t_plr plr ON plr.plrid=ap.plrid ";
	$cmd .= "LEFT JOIN $this->t_plr_profile pp ON pp.uniqueid=plr.uniqueid ";
	$cmd .= "WHERE ap.awardid=a.id AND a.awardid=ac.id AND ap.awardid=" . $this->db->escape($id) . " ";
	if ($args['where'] != '') $cmd .= "AND (" . $args['where'] . ") ";
	$cmd .= $this->_getsortorder($args);
	$list = array();
	$list = $this->db->fetch_rows(1, $cmd);
//	print $this->db->lastcmd;

	if (!$args['var'] || !$s) return $list;
	$s->assign($args['var'], $list);
}

function get_weapon_player_list($args, &$s) {
	$args += array(
		'var'		=> '',
		'weaponid'	=> 0,
		'fields'	=> '',
		'where'		=> '',
		'allowall'	=> 0,
		'sort'		=> '',
		'order'		=> 'desc',
		'start'		=> 0,
		'limit'		=> 10,
	);
	$id = $this->db->escape($args['weaponid']);
	if (!is_numeric($id)) $id = 0;
	$fields = $args['fields'] ? $args['fields'] : "data.*, plr.*, pp.*, c.cn";

	$cmd  = "SELECT $fields ";
	$cmd .= "FROM ($this->c_plr_weapons data, $this->t_plr plr, $this->t_plr_profile pp) ";
	$cmd .= "LEFT JOIN $this->t_geoip_cc c ON c.cc=pp.cc ";
	$cmd .= "WHERE plr.plrid=data.plrid AND data.weaponid=$id AND pp.uniqueid=plr.uniqueid ";
	if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
	if ($args['where'] != '') $cmd .= "AND (" . $args['where'] . ") ";
	$cmd .= $this->_getsortorder($args);
	$list = array();
	$list = $this->db->fetch_rows(1, $cmd);

	if (!$args['var'] || !$s) return $list;
	$s->assign($args['var'], $list);
}

function get_role_player_list($args, &$s) {
	$args += array(
		'var'		=> '',
		'roleid'	=> 0,
		'fields'	=> '',
		'where'		=> '',
		'allowall'	=> 0,
		'sort'		=> '',
		'order'		=> 'desc',
		'start'		=> 0,
		'limit'		=> 10,
	);
	$id = $this->db->escape($args['roleid']);
	if (!is_numeric($id)) $id = 0;
	$fields = $args['fields'] ? $args['fields'] : "data.*, plr.*, pp.*, c.cn";

	$cmd  = "SELECT $fields ";
	$cmd .= "FROM ($this->c_plr_roles data, $this->t_plr plr, $this->t_plr_profile pp) ";
	$cmd .= "LEFT JOIN $this->t_geoip_cc c ON c.cc=pp.cc ";
	$cmd .= "WHERE plr.plrid=data.plrid AND data.roleid=$id AND pp.uniqueid=plr.uniqueid ";
	if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
	if ($args['where'] != '') $cmd .= "AND (" . $args['where'] . ") ";
	$cmd .= $this->_getsortorder($args);
	$list = array();
	$list = $this->db->fetch_rows(1, $cmd);

	if (!$args['var'] || !$s) return $list;
	$s->assign($args['var'], $list);
}

function get_map_player_list($args, &$s) {
	$args += array(
		'var'		=> '',
		'mapid'		=> 0,
		'fields'	=> '',
		'where'		=> '',
		'allowall'	=> 0,
		'sort'		=> '',
		'order'		=> 'desc',
		'start'		=> 0,
		'limit'		=> 10,
	);
	$id = $this->db->escape($args['mapid']);
	if (!is_numeric($id)) $id = 0;
//	$fields = $args['fields'] ? $args['fields'] : "data.*, plr.*, pp.*, c.cn";
	$fields = $args['fields'] ? $args['fields'] : "data.*";

	$cmd  = "SELECT $fields, plr.*, pp.*, c.cn ";
	$cmd .= "FROM ($this->c_plr_maps data, $this->t_plr plr, $this->t_plr_profile pp) ";
	$cmd .= "LEFT JOIN $this->t_geoip_cc c ON c.cc=pp.cc ";
	$cmd .= "WHERE plr.plrid=data.plrid AND data.mapid=$id AND pp.uniqueid=plr.uniqueid ";
	if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
	if ($args['where'] != '') $cmd .= "AND (" . $args['where'] . ") ";
	$cmd .= $this->_getsortorder($args);
	$list = array();
	$list = $this->db->fetch_rows(1, $cmd);

	if (!$args['var'] || !$s) return $list;
	$s->assign($args['var'], $list);
}

function get_player_list($args, &$s) {
	$args += array(
		'var'		=> '',
		'allowall'	=> 0,
		'start'		=> 0,
		'limit'		=> 100,
		'sort'		=> 'skill',
		'order'		=> 'desc',
		'fields'	=> '',
		'where'		=> '',
		'search'	=> FALSE,
		'joinclaninfo'	=> 0,
		'joinccinfo'	=> 1,
	);
	$values = "";
	if (trim($args['fields']) == '') {
		if ($args['joinclaninfo']) $values .= "clan.*, ";
		$values .= "data.*,plr.*,pp.* ";
		if ($args['joinccinfo']) $values .= ",c.* ";
	} else {
		$values = $args['fields'];
	}

	$search = $this->_getsearch($args['search']);

	$cmd  = "SELECT $values FROM ($this->t_plr plr, $this->t_plr_profile pp, $this->c_plr_data data) ";
	if ($args['joinccinfo']) {
		$cmd .= "LEFT JOIN $this->t_geoip_cc c ON c.cc=pp.cc ";
	}
	if ($args['joinclaninfo']) {
		$cmd .= "LEFT JOIN $this->t_clan clan ON clan.clanid=plr.clanid ";
	}
	$cmd .= "WHERE pp.uniqueid=plr.uniqueid AND data.plrid=plr.plrid ";
	if (!$args['allowall']) $cmd .= "AND plr.allowrank=1 ";
	if (trim($args['where']) != '') $cmd .= "AND (" . $args['where'] . ") ";
	if ($search != '') $cmd .= " AND ($search) ";
	$cmd .= $this->_getsortorder($args);
	$list = array();
	$list = $this->db->fetch_rows(1, $cmd);
#	print $cmd;

	if (!$args['var'] || !$s) return $list;
	$s->assign($args['var'], $list);
}

function get_clan_list($args, &$s) {
	$args += array(
		'var'		=> '',
		'start'		=> 0,
		'limit'		=> 100,
		'sort'		=> 'skill',
		'order'		=> 'desc',
		'fields'	=> '',
		'where'		=> '',
		'allowall'	=> 0,
	);
	$values = "clan.clanid,clan.locked,clan.allowrank,cp.*,COUNT(*) totalmembers, ROUND(AVG(skill),2) skill, ";

	$types = $this->get_types("CLAN");
	$fields = !empty($args['fields']) ? split(',',$args['fields']) : array_keys($types);
	$values .= $this->_values($fields, $types);

	$cmd  = "SELECT $values ";
	$cmd .= "FROM $this->t_clan clan, $this->t_plr plr, $this->c_plr_data data, $this->t_clan_profile cp ";
	$cmd .= "WHERE (plr.clanid=clan.clanid AND plr.allowrank=1) AND clan.clantag=cp.clantag AND data.plrid=plr.plrid ";
	if (!$args['allowall']) $cmd .= "AND clan.allowrank=1 ";
	if (trim($args['where']) != '') $cmd .= "AND (" . $args['where'] . ") ";
	$cmd .= "GROUP BY clan.clanid ";
//	$cmd .= "HAVING totalmembers > " . $this->conf['main']['clans']['min_members'] . " ";
	$cmd .= $this->_getsortorder($args);
	$list = array();
	$list = $this->db->fetch_rows(1, $cmd);

//	print "explain " . $this->db->lastcmd;

	if (!$args['var'] || !$s) return $list;
	$s->assign($args['var'], $list);
}


function get_weapon_list($args, &$s) {
	$args += array(
		'var'		=> '',
		'start'		=> 0,
		'limit'		=> 100,
		'sort'		=> 'skill',
		'order'		=> 'desc',
		'fields'	=> '',
		'where'		=> '',
	);

	$values = "";
	if (trim($args['fields']) == '') {
		$values .= "data.*,w.*";
	} else {
		$values = $args['fields'];
	}

	$cmd  = "SELECT $values FROM $this->c_weapon_data data, $this->t_weapon w ";
	$cmd .= "WHERE data.weaponid=w.weaponid ";
	if ($args['where'] != '') {
		$cmd .= "AND (" . $args['where'] . ") ";
	}
	$cmd .= $this->_getsortorder($args);

	$list = $this->db->fetch_rows(1, $cmd);

	if (!$args['var'] || !$s) return $list;
	$s->assign($args['var'], $list);
}

function get_role_list($args, &$s) {
	$args += array(
		'var'		=> '',
		'start'		=> 0,
		'limit'		=> 100,
		'sort'		=> 'skill',
		'order'		=> 'desc',
		'fields'	=> '',
		'where'		=> '',
	);

	$values = "";
	if (trim($args['fields']) == '') {
		$values .= "data.*,r.*";
	} else {
		$values = $args['fields'];
	}

	$cmd  = "SELECT $values FROM $this->c_role_data data, $this->t_role r ";
	$cmd .= "WHERE data.roleid=r.roleid ";
	if ($args['where'] != '') {
		$cmd .= "AND (" . $args['where'] . ") ";
	}
	$cmd .= $this->_getsortorder($args);

	$list = $this->db->fetch_rows(1, $cmd);

	if (!$args['var'] || !$s) return $list;
	$s->assign($args['var'], $list);
}

function get_map_list($args, &$s) {
	$args += array(
		'var'		=> '',
		'start'		=> 0,
		'limit'		=> 100,
		'sort'		=> 'skill',
		'order'		=> 'desc',
		'fields'	=> '',
		'where'		=> '',
	);

	$values = "";
	if (trim($args['fields']) == '') {
		$values .= "data.*,m.*";
	} else {
		$values = $args['fields'];
	}

	$cmd  = "SELECT $values FROM $this->c_map_data data ";
	$cmd .= "LEFT JOIN $this->t_map m ON m.mapid=data.mapid ";
	if ($args['where'] != '') {
		$cmd .= "WHERE " . $args['where'] . " ";
	}
	$cmd .= $this->_getsortorder($args);
	$list = $this->db->fetch_rows(1, $cmd);
//	print "explain " . $this->db->lastcmd . ";";

	if (!$args['var'] || !$s) return $list;
	$s->assign($args['var'], $list);
}

function get_total_players($args, &$s) {
	$args += array(
		'var'		=> '',
		'allowall'	=> 0,
	);

	$cmd  = "SELECT count(*) FROM $this->t_plr plr ";
	if (!$args['allowall']) $cmd .= "WHERE plr.allowrank=1 ";
	$this->db->query($cmd);
	list($total) = $this->db->fetch_row(0);
#	print "$cmd<BR><BR>";

	if (!$args['var'] || !$s) return $total;
	$s->assign($args['var'], $total);
}

function get_total_clans($args, &$s) {
	$args += array(
		'var'		=> '',
		'allowall'	=> 0,
		'where'		=> '',
	);
	$cmd  = "SELECT count(*) total FROM $this->t_clan clan ";
	if (!$args['allowall'] and $args['where']) {
		$cmd .= "WHERE clan.allowrank=1 AND " . $args['where'] . " ";
	} elseif (!$args['allowall']) {
		$cmd .= "WHERE clan.allowrank=1 ";
	} elseif ($args['where']) {
		$cmd .= $args['where'] . " ";
	}
	$this->db->query($cmd);
	list($total) = $this->db->fetch_row(0);

	if (!$args['var'] || !$s) return $total;
	$s->assign($args['var'], $total);
}

function get_total_weapons($args, &$s) {
	$args += array(
		'var'		=> '',
	);
	$cmd  = "SELECT count(distinct weaponid) FROM $this->c_weapon_data LIMIT 1";
	$this->db->query($cmd);
	list($total) = $this->db->fetch_row(0);

	if (!$args['var'] || !$s) return $total;
	$s->assign($args['var'], $total);
}

function get_total_roles($args, &$s) {
	$args += array(
		'var'		=> '',
	);
	$cmd  = "SELECT count(distinct roleid) FROM $this->c_role_data LIMIT 1";
	$this->db->query($cmd);
	list($total) = $this->db->fetch_row(0);

	if (!$args['var'] || !$s) return $total;
	$s->assign($args['var'], $total);
}

function get_total_awards($args, &$s) {
	$args += array(
		'var'		=> '',
		'type'		=> '',
	);
	return 0; #########################################################
	$where = $args['type'] ? "WHERE type='" . $this->db->escape($args['type']) . "' " : "";
	$cmd  = "SELECT count(distinct awardid) FROM $this->t_awards $where LIMIT 1";
	$this->db->query($cmd);
	list($total) = $this->db->fetch_row(0);

	if (!$args['var'] || !$s) return $total;
	$s->assign($args['var'], $total);
}

function get_total_maps($args, &$s) {
	$args += array(
		'var'		=> '',
	);
	$cmd  = "SELECT count(distinct mapid) FROM $this->c_map_data LIMIT 1";
	$this->db->query($cmd);
	list($total) = $this->db->fetch_row(0);

	if (!$args['var'] || !$s) return $total;
	$s->assign($args['var'], $total);
}

// deletes a player and all of his stats. If $keep_profile is true than their profile is saved.
function delete_player($plrid, $keep_profile = TRUE) { 
	$_plrid = $this->db->escape($plrid);
	// get player uniqueid and userid 
	list($uniqueid,$userid) = $this->db->fetch_row(0,"SELECT p.uniqueid,userid FROM $this->t_plr p
		LEFT JOIN $this->t_plr_profile pp ON pp.uniqueid=p.uniqueid
		WHERE p.plrid='$_plrid'"
	);

	// remove historical data related to this player ID
	$tables = array( 't_plr_data', 't_plr_maps' );
	foreach ($tables as $table) {
		$t = $this->$table;
		$ids = $this->db->fetch_list("SELECT dataid FROM $t WHERE plrid=$_plrid");
		while (count($ids)) {
			// limit how many we delete at a time, so we're sure the query is never too large
			$list = array_splice($ids, 0, 100);
			$this->db->query("DELETE FROM " . $t . $this->tblsuffix . " WHERE dataid IN (" . join(', ', $list) . ")");
		}
		$this->db->delete($this->$table, 'plrid', $plrid);
	}

	// remove simple data related to this player ID
	$tables = array( 't_plr_ids', 't_plr_sessions', 't_plr_victims', 't_plr_weapons', 't_plr' );
	foreach ($tables as $table) {
		// don't use $_plrid, since delete() will escape it
		$this->db->delete($this->$table, 'plrid', $plrid);
	}

	// delete the player profile if specified
	if (!$keep_profile) {
		$this->db->delete($this->t_plr_profile, 'uniqueid', $uniqueid);
		delete_user($userid);	// user_handler function
	}

	// remove player from any awards they are ranked in
	// this will probably be the slowest part of a player deletion
	if ($this->db->count($this->t_awards_plrs, '*', "plrid=$_plrid")) {
		$this->db->delete($this->t_awards_plrs, 'plrid', $plrid);
		// fix awards that had this player as #1
		$awardids = $this->db->fetch_list("SELECT id FROM $this->t_awards WHERE topplrid=$_plrid");
		foreach ($awardids as $id) {
			list($topplrid, $topplrvalue) = $this->db->fetch_list("SELECT plrid, value FROM $this->t_awards_plrs WHERE awardid=$id ORDER BY idx LIMIT 1");
			$this->db->update($this->t_awards, array( 'topplrid' => $topplrid, 'topplrvalue' => $topplrvalue ), 'id', $id);
		}
	}

	// delete all compiled stats for this player
	$tables = array( 'c_plr_data', 'c_plr_maps', 'c_plr_victims', 'c_plr_weapons' );
	foreach ($tables as $table) {
		$this->db->delete($this->$table, 'plrid', $plrid);
	}

	// and finally; delete the main plr record
	$this->db->delete($this->t_plr, 'plrid', $plrid);

	return 1;
}

function _getsortorder($args, $prefix='') {
	$str = "";
	if ($args[$prefix . 'sort'] != '') {
		$fieldprefix = $args['fieldprefix'] ? $args['fieldprefix'] . '.' : '';
		$str .= " ORDER BY $fieldprefix" . $args[$prefix . 'sort'];
		if ($args[$prefix . 'order']) $str .= " " . $args[$prefix . 'order'];
	}
	$str .= $this->_getlimit($args, $prefix);
	return $str;    
}

function _getlimit($args, $prefix='') {
	$str = "";
	if ($args[$prefix . 'limit'] && !$args[$prefix . 'start']) {
		$str .= " LIMIT " . $args[$prefix . 'limit'];
	} elseif ($args[$prefix . 'limit'] && $args[$prefix . 'start']) {
		$str .= " LIMIT " . $args[$prefix . 'start'] . "," . $args[$prefix . 'limit'];
	}
	return $str;
}


function _getsearch($results) {
	if (!$results) return '';

	$res = $this->get_search_results();
	if ($res['results'] != '') {
		$list = explode(',',$res['results']);
		$cmd = "";
		foreach ($list as $id) {	// manually make sure each item is numeric for sanity!
			if (is_numeric($id)) $cmd .= "$id,";
		}
		return $cmd != '' ? "plr.plrid IN (" . substr($cmd,0,-1) . ")" : '0';
	} else {
		return '0';
	}
}

function _old_getsearch($string) {
	static $prevstr = "";		// cache the previous str/result so we only do our query once in the same session
	static $prevresult = "";
	if ($string == $prevstr) return $prevresult;
	$list = array();
	$search = "";
	$string = trim($string);
	list($andor, $words) = explode(':', $string, 2);
	$words = trim($words);
	$andor = strtolower(trim($andor));
	if (empty($string) or empty($words)) return '';
	if (!in_array($andor, array('and','or','exact'))) $andor = 'or';
	$wordlist = preg_split('/\s+/', $words);

	$match = "";
	// search plr_ids table for matching names and worldids
	foreach (array('name', 'worldid') as $key) {
		$match .= '(' . $this->_joinwords($key, $wordlist, $andor) . ") OR ";
	}
	// search for ipaddr's
	foreach ($wordlist as $word) {
		if (!preg_match('|^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$|', $word)) continue;
		$ipint = sprintf("%u", ip2long($word));
		$match .= "(ipaddr = '$ipint') OR ";
	}
	$match = substr($match, 0, -3);		// remove trailing 'or'
	$list = $this->db->fetch_list("SELECT DISTINCT plrid FROM $this->t_plr_ids WHERE $match");

	// search plr_profile for matching names
	$match = $this->_joinwords('name', $wordlist, $andor);
	$list2 = $this->db->fetch_list("SELECT plrid FROM $this->t_plr_profile pp, $this->t_plr plr WHERE plr.uniqueid=pp.uniqueid AND $match");
	$list = array_merge($list, $list2);

	$plrids = array_unique($list);
	$prevstr = $string;
	$prevresult = count($plrids) ? "plr.plrid IN (" . implode(',', $plrids) . ')' : "0";
	return $prevresult;
}

function _joinwords($key, $wordlist, $andor) {
	if ($andor == 'exact') {
		return "$key = '" . $this->db->escape(implode(" ", $wordlist)) . "'";
	} else {
		$list = array();
		foreach ($wordlist as $word) {
			$list[] = "$key LIKE '%" . $this->db->escape($word) . "%'";
		}
		return implode(" $andor ", $list);
	}
}

// loads a portion of config into memory
function load_config($type, $include_raw=0) {
	$conflist = array();
	if (!is_array($type)) {
		$conflist = array($type);
	} else {
		$conflist = $type;
	}
	$return = array();
	foreach ($conflist as $conftype) {
		// remove the config if it already existed in memory (so we can over write it)
		$this->conf[$conftype] = array();
		$this->confid[$conftype] = array();
		$this->confidx[$conftype] = array();

		// load the config
		$cmd = "SELECT * FROM $this->t_config WHERE conftype='" . $this->db->escape($conftype) . "' ORDER BY idx,id";
		$list = $this->db->fetch_rows(1, $cmd);
		foreach ($list as $row) {
			if (empty($row['section'])) {
				$this->_assignvar($this->conf[$conftype], $row['var'], $row['value']);
				$this->_assignvar($this->confid[$conftype], $row['var'], $row['id']);
				$this->_assignvar($this->confidx[$conftype], $row['var'], $row['idx']);
			} else {
				$this->_assignvar($this->conf[$conftype][$row['section']], $row['var'], $row['value']);
				$this->_assignvar($this->confid[$conftype][$row['section']], $row['var'], $row['id']);
				$this->_assignvar($this->confidx[$conftype][$row['section']], $row['var'], $row['idx']);
			}
		}
		$return[] = $list;
	}
	return $list;	// return the raw results
}

// writes the error message to the error log
// trims the log if it grows too large (unless $notrim is true)
function errlog($msg, $severity='warning', $userid=NULL, $notrim=false) {
	if (!in_array($severity, array('info','warning','fatal'))) {
		$severity = 'warning';
	}
	$msg = trim($msg);
	if ($msg == '') return;		// do nothing if there is no message
	$this->db->insert($this->t_errlog, array(
		'id'		=> $this->db->next_id($this->t_errlog), 
		'timestamp'	=> time(),
		'severity'	=> $severity,
		'userid'	=> $userid,
		'msg'		=> $msg
	));

	if (!$notrim) {
		$this->trim_errlog();
	}
}

// trims the errlog size to the configured settings. 
// if $all is true then the errlog table is truncated
function trim_errlog($all=false) {
	$maxrows = $this->conf['main']['errlog']['maxrows'];
	$maxdays = $this->conf['main']['errlog']['maxdays'];
	if ($maxrows == '') $maxrows = 5000;
	if ($maxdays == '') $maxdays = 30;
	if (intval($maxrows) + intval($maxdays) == 0) return;		// nothing to trim
	$deleted = 0;
	if ($maxdays) {
		$this->db->query("DELETE FROM $this->t_errlog WHERE " . $this->db->qi('timestamp') . " < " . (time()-60*60*24*$maxdays));
		$deleted++;
	}
	if ($maxrows) {
		$total = $this->db->count($this->t_errlog);
		if ($total <= $maxrows) return;
		$diff = $total - $maxrows;
		$list = $this->db->fetch_list("SELECT id FROM $this->t_errlog ORDER BY " . $this->db->qi('timestamp') . " LIMIT $diff");
		if (is_array($list) and count($list)) {
			$this->db->query("DELETE FROM $this->t_errlog WHERE id IN (" . implode(',', $list) . ")");
			$deleted++;
		}
	}
	if ($deleted) {
		if (mt_rand(1,20) == 1) {	// approximately 20% chance of optimizing the table
			$this->db->optimize($this->t_errlog);
		}
	}
}

function get_types($prefix, $mod=1) {
	$var = $prefix . "_TYPES";
	$modvar = $prefix . "_MODTYPES";
	if ($mod and is_array($this->$modvar)) {
		return $this->$var + $this->$modvar;
	} else {
		return $this->$var;
	}
}

// internal function for load_config. do not call outside of class
function _assignvar(&$c,$var,$val) {
	if (!is_array($c)) $c = array();
	if (array_key_exists($var, $c)) {
		if (!is_array($c[$var])) {
			$c[$var] = array( $c[$var] );
		}
		$c[$var][] = $val;
	} else {
		$c[$var] = $val	;
	}
}

// returns a value string used for certain non-clan statistics (like player sessions)
function _calcvalues($fields, $types) {
	$values = "";
	foreach ($fields as $key) {
		if (array_key_exists($key, $types)) {
			$type = $types[$key];
			if (is_array($type)) {
				$func = "_soloexpr_" . array_shift($type);
				if (method_exists($this->db, $func)) {
					$values .= $this->db->$func($type) . " $key, ";
				}
			} else {
				$values .= "$key, ";
			} 
		} else {
			$values .= "$key, ";
		}
	}
	$values = substr($values, 0, -2);		// trim trailing comma: ", "
	return $values;
}

// returns a value string used in the clan statistics
function _values($fields, $types) {
	$values = "";
	foreach ($fields as $key) {
		if (array_key_exists($key, $types)) {
			$type = $types[$key];
			if (is_array($type)) {
				$func = "_expr_" . array_shift($type);
				if (method_exists($this->db, $func)) {
					$values .= $this->db->$func($type) . " $key, ";
				} else {
					# ignore key
				}
			} else {
				if ($type == '>') {
					$values .= "MAX($key) $key, ";
				} elseif ($type == '<') {
					$values .= "MIN($key) $key, ";
				} elseif ($type == '~') {
					$values .= "AVG($key) $key, ";
				} else {	# $type == '+'
					$values .= "SUM($key) $key, ";
				}
			}
		} else {
			$values .= "$key, ";
		}
	}
	$values = substr($values, 0, -2);		// trim trailing comma: ", "
	return $values;
}

// read a config from a file or string.
// If the TYPE can not be determined the imported variables are ignored.
// set $forcetype to a conftype if you know the type of the config you're loading.
// returns 'FALSE' if no errors, otherwise returns an array of all invalid config options that were ignored.
function import_config($source, $forcetype = false, $opts = array()) {
	$opts += array(
		'replacemulti'	=> 1,
		'ignorenew'	=> 1,
	);
	$SEP = "^";
	if (is_array($source)) {
		$lines = $source;
	} elseif (strlen($source)<=255 and @is_file($source) and @is_readable($source)) {
		$lines = file($source);
	} else {
		$lines = explode("\n", $source);
	}
	$lines = array_map('trim', $lines);	// normalize all lines

	$section = '';
	$errors = array();
	$type = $forcetype !== false ? $forcetype : '';
	if ($type and !array_key_exists($type, $this->conf)) $this->load_config($type);

	$this->_layout = array();
	$this->_import_errors = array();
	$this->_import_multi = array();
	$this->_import_opts = $opts;

	foreach ($lines as $line) {
		if ($forcetype === false and preg_match('/^#\\$TYPE\s*=\s*([a-zA-Z_]+)/', $line, $m)) {
			$type = $m[1];
			if (!array_key_exists($type, $this->conf)) $this->load_config($type);
			$this->_update_layout($type);
			$section = '';
		} 
		if ($line{0} == '#') continue; 		// ignore comments;

		if (preg_match('/^\[([^\]]+)\]/', $line, $m)) {
			$section = $m[1];
			if (strtolower($section) == 'global') $section = '';
		} elseif (preg_match('/^([\w\d_]+)\s*=\s*(.*)/', $line, $m)) {
			if ($type) {
				$this->_import_var($type, $section, $m[1], $m[2]);
			} else {
				$this->_import_errors['unknown_types'][] = $section ? $section . "." . $m[1] : $m[1];
			}
		}
	}

	return count($this->_import_errors) ? $this->_import_errors : false;
}

function _import_var($type, $section, $var, $val) {
#	print "$type:: $section.$var = $val<br>\n";
	$key = $section ? $section . "." . $var : $var;

	// do not allow changes to locked variables
	if ($this->_layout[$key]['locked']) {
		$this->_import_errors['locked_vars'][] = $key;
		return false;
	}

	// verify the variable is 'sane' according to the layout rules
	$field = array( 'val' => $this->_layout[$key]['verifycodes'], 'error' => '' );
	form_checks($val, $field);
	if ($field['error']) {
		$this->_import_errors['invalid_vars'][$key] = $field['error'];
		return false;
	}

	// do not accept NEW vars if 'ignorenew' is enabled
	$exists = (($section and array_key_exists($var, $this->conf[$type][$section])) or 
		(!$section and array_key_exists($var, $this->conf[$type])));
	if ($this->_import_opts['ignorenew'] and !$exists) {
		$this->_import_errors['ignored_vars'][] = $key;
		return false;
	}

	// save the imported settings. Take special care of 'multi' options.
	// first: find the matching ID of the current variable (might be more than 1).
	$id = $this->db->fetch_list(sprintf("SELECT id FROM $this->t_config WHERE conftype='%s' AND section='%s' AND var='%s'",
		$this->db->escape($type),
		$this->db->escape($section),
		$this->db->escape($var)
	));
	// if there's no ID, then this is a new option
	$new = false;
	if (!is_array($id) or !count($id)) {
		$new = true;
		$id = array( $this->db->next_id($this->t_config) );
	}
//	print "ID=" . implode(',',$id) . " ($var) == $val<br>";

	// single options can be simply inserted or updated
	// if a non-multi option ends up having more than 1, only the first fetched from the DB is updated
	if (!$this->_layout[$key]['multiple']) {
		if ($new) {
			$this->db->insert($this->t_config, array( 
				'id' 		=> $id[0],
				'conftype' 	=> $type,
				'section' 	=> $section,
				'var' 		=> $var,
				'value' 	=> $val
			));
		} else {
			$this->db->update($this->t_config, array( 'value' => $val ), 'id', $id[0]);
		}
	} else {
		// remove all multi options related to the variable the first time we see it
		if ($this->_import_opts['replacemulti'] and !$this->_import_multi[$key]) {
			$this->_import_multi[$key] = 1;
			$this->db->query("DELETE FROM $this->t_config WHERE id IN (" . implode(',', $id) . ")");
		}
		// now insert the option
		$this->db->insert($this->t_config, array( 
			'id' 		=> $this->db->next_id($this->t_config),
			'idx'		=> $this->_import_multi[$key]++,
			'conftype' 	=> $type,
			'section' 	=> $section,
			'var' 		=> $var,
			'value' 	=> $val
		));
	}
}

function _update_layout($type) {
	if (array_key_exists($type, $this->_layout)) return;

	$t = $this->db->escape($type);
	$this->db->query("SELECT c.*,l.* FROM $this->t_config c " . 
		"LEFT JOIN $this->t_config_layout l ON (l.conftype='$t' AND l.section=c.section AND l.var=c.var) " . 
		"WHERE c.conftype='$t' AND (isnull(l.locked) OR !l.locked) " 
	);
	while ($r = $this->db->fetch_row()) {
		$key = $r['var'];
		if ($r['section']) $key = $r['section'] . $SEP . $key;
		$this->_layout[$key] = $r;
	}
}

// returns the config as a string to be imported with import_config
// only exports a single config type at a time.
function export_config($type) {
	if (!array_key_exists($type, $this->conf)) $this->load_config($type);

	$config  = "# Configuration exported on " . date("D M j G:i:s T Y") . "\n";
	$config .= "#\$TYPE = $type # do not remove this line\n\n";

	$globalkeys = array();
	$nestedkeys = array();
	$this->_layout = array();
	$this->_update_layout($type);

	foreach (array_keys($this->conf[$type]) as $key) {
		// watch out for items that can be repeated, so we dont treat them like a [section]
		if (is_array($this->conf[$type][$key]) and !$this->_layout[$key]['multiple']) {
			$nestedkeys[$key] = $this->conf[$type][$key];
			ksort($nestedkeys[$key]);
		} else {
			if (is_array($this->conf[$type][$key])) {
				// add each repeated key into the array. 1+ values
				foreach ($this->conf[$type][$key] as $i) {
					$globalkeys[$key][] = $i;
				} 
			} else {
				// there will always only be 1 value in the array
				$globalkeys[$key][] = $this->conf[$type][$key];
			}
		}
	}
	ksort($globalkeys);
	ksort($nestedkeys);

	$width = 1;
	foreach ($globalkeys as $k => $v) if (strlen($k) > $width) $width = strlen($k);
	foreach ($globalkeys as $k => $values) {
		foreach ($values as $v) {
			$config .= sprintf("%-{$width}s = %s\n", $k, $v);
		}
	}

	$config .= "\n";
	foreach ($nestedkeys as $conf => $group) {
		$config .= "[$conf]\n";
		$width = 1;
		foreach ($group as $k => $v) if (strlen($k) > $width) $width = strlen($k);
		foreach ($group as $k => $v) $config .= sprintf("  %-{$width}s = %s\n", $k, $v);
		$config .= "\n";
	}

	return $config;
}

// loads the config in a manner that can be used with the automatic config forms
function load_conf_form($t, $VAR_SEPARATOR = '^', $doglobal = FALSE) {
#	global $conf_idxs,$conf_values,$conf_ids,$conf_varids,$conf_form;
	$conf_idxs = $conf_values = $conf_ids = $conf_varids = $conf_form = array();

	// load config in 'form' fashion, also create a reverse lookup array so we 
	// can verify submitted var/val pairs are valid. ignore options that are locked
	$t_esc = $this->db->escape($t);
	$this->db->query("SELECT c.* FROM $this->t_config c " . 
		"LEFT JOIN $this->t_config_layout l ON (l.conftype='$t_esc' AND l.section=c.section AND l.var=c.var) " . 
		"WHERE c.conftype='$t_esc' AND (isnull(l.locked) OR !l.locked) " . 
		"ORDER BY c.section,c.var,c.idx"
	);
//	print $this->db->lastcmd;
	while ($r = $this->db->fetch_row()) {
		if ($r['var'] == 'logsource') continue;
		$var = $r['section'] . $VAR_SEPARATOR . $r['var'] . $VAR_SEPARATOR . $r['id'];

		$conf_idxs[$var] = $r['idx'];
		$conf_values[$var] = $r['value'];
		$conf_form[$r['section']][$var] = $r['value'];

		$conf_ids[$r['id']] = $var;
		$conf_varids[ $r['section'] . $VAR_SEPARATOR . $r['var'] ] = $r['id'];
	}

	if ($doglobal) {
		$GLOBALS['conf_idxs'] 	= $conf_idxs;
		$GLOBALS['conf_values'] = $conf_values;
		$GLOBALS['conf_ids'] 	= $conf_ids;
		$GLOBALS['conf_varids'] = $conf_varids;
		$GLOBALS['conf_form'] 	= $conf_form;
		return array();
	}

	return array( $conf_idxs, $conf_values, $conf_ids, $conf_varids, $conf_form );
}

// geocode lookup of an ip
function ip_lookup($ip) {
	$url = $this->conf['theme']['map']['iplookup_url'];
	if (!$url) return false;
	$ipstr = is_array($ip) ? implode(',',$ip) : $ip;
	$url = (strpos($url, '$ip') === FALSE) ? $url.$ipstr : str_replace('$ip', $ipstr, $url);
	$lookup = new HTTPRequest($url);
	$text = $lookup->download();
	return $text; //$lookup->getAllHeaders();
}

}  // end of PS class

// original from info at b1g dot de on http://us2.php.net/manual/en/function.fopen.php
// modified by Stormtrooper and slightly enhanced.
class HTTPRequest {
var $_fp;
var $_url;
var $_method;
var $_postdata;
var $_host;
var $_protocol;
var $_uri;
var $_port;
var $_error;
var $_headers;
var $_text;

// scan url
function _scan_url() {
	$req = $this->_url;
	$pos = strpos($req, '://');
	$this->_protocol = strtolower(substr($req, 0, $pos));
	$req = substr($req, $pos+3);
	$pos = strpos($req, '/');
	if($pos === false) $pos = strlen($req);
	$host = substr($req, 0, $pos);
      
	if(strpos($host, ':') !== false) {
		list($this->_host, $this->_port) = explode(':', $host);
	} else {
		$this->_host = $host;
		$this->_port = ($this->_protocol == 'https') ? 443 : 80;
	}

	$this->_uri = substr($req, $pos);
	if ($this->_uri == '') $this->_uri = '/';
}
  
// constructor
function HTTPRequest($url, $method="GET", $data="") {
	$this->_url = $url;
	$this->_method = $method;
	$this->_postdata = $data;
	$this->_scan_url();
}

// returns all headers. only call after download()
function getAllHeaders() {
	return $this->_headers;
}

// return the value of a single header
function header($key) {
	return array_key_exists($key, $this->_headers) ? $this->_headers[$key] : null;
}

function status() {
	return $this->_error;
}

function text() {
	return $this->_text;
}

// download URL to string
function download($follow_redirect = true) {
	$crlf = "\r\n";
      
	// generate request
	$req = $this->_method . ' ' . $this->_uri . ' HTTP/1.0' . $crlf .
		'Host: ' . $this->_host . $crlf . 
		$crlf;
	if ($this->_postdata) $req .= $this->_postdata;

	// fetch
	$this->_fp = fsockopen(($this->_protocol == 'https' ? 'ssl://' : '') . $this->_host, $this->_port);
	fwrite($this->_fp, $req);
	while(is_resource($this->_fp) && $this->_fp && !feof($this->_fp)) {
		$response .= fread($this->_fp, 1024);
	}
	fclose($this->_fp);
      
	// split header and body
	$pos = strpos($response, $crlf . $crlf);
	if($pos === false) return $response;

	$header = substr($response, 0, $pos);
	$body = substr($response, $pos + 2 * strlen($crlf));
      
	// parse headers
	$this->_headers = array();
	$lines = explode($crlf, $header);
	list($zzz, $this->_error, $zzz) = explode(" ", $lines[0], 3); unset($zzz);
	foreach($lines as $line) {
		if(($pos = strpos($line, ':')) !== false) {
			$this->_headers[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos+1));
		}
	}

	// redirection?
	if(isset($headers['location']) and $follow_redirect) {
		$http = new HTTPRequest($headers['location']);
		return($http->download($http));
	} else {
		$this->_text = $body;
		return($body);
	}
}
} // end HTTPRequest

?>
