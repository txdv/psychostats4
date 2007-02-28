<?php
/**********

	This HALFLIFE class will automatically determine if the server being queried is HL1 or HL2.
	The query codes below are for your reference, incase you're interested. It should be noted that 
	the recent versions of HL1 use the same query codes as HL2 (response codes are slightly different).
	That means that the older HL1 versions may not work correctly.

	There is an 'oldhalflife' querytype that can be used to force older halflife queries.

	Query:		HL1 send:	HL1 recv: 	HL2 send: 	HL2 recv:
	'info'		'T'		'm'		'T'		'I'	(different packet stream)
	'players'	'U'		'D'		'U'		'D'	(the same)
	'rules'		'V'		'o'		'V'		'E'	(different codes, same packet stream)
	'ping'		'i'		'j'		'i'		'j'	(the same)

	RCON commands are also supported for both versions, HL1 and HL2 (transparently)

**********/

if (!defined("CLASS_PQ_PHP")) die("Access Denied!");
if (defined("CLASS_PQ_HALFLIFE_PHP")) return 1;
define("CLASS_PQ_HALFLIFE_PHP", 1);

// REGION global constants for master server queries. Use these when using query_master()
define("PQ_HL2_REGION_USEAST", 		0);
define("PQ_HL2_REGION_USWEST", 		1);
define("PQ_HL2_REGION_SOUTHAMERICA", 	2);
define("PQ_HL2_REGION_EUROPE", 		3);
define("PQ_HL2_REGION_ASIA", 		4);
define("PQ_HL2_REGION_AUSTRALIA", 	5);
define("PQ_HL2_REGION_MIDDLEEAST", 	6);
define("PQ_HL2_REGION_AFRICA", 		7);

// HL2 RCON response codes, these are never used outside of the class.
define("PQ_HL2_SERVERDATA_EXECCOMMAND",		2);
define("PQ_HL2_SERVERDATA_AUTH",		3);
define("PQ_HL2_SERVERDATA_AUTH_RESPONSE", 	2);
define("PQ_HL2_SERVERDATA_RESPONSE_VALUE", 	0);

class PQ_halflife extends PQ_PARENT {

function __construct($conf) {
	$this->PQ_halflife($conf);
}

function PQ_halflife($conf) {
	$this->conf = $conf;		// always save the config to the class variable first
	$this->init();			// always run the class initialization method
}

function init() {
	parent::init();			// always run the parent init method
	// add your own initialization steps here.
	// ...

	$this->halflife_version = 0;	// 0 = unknown version
	$this->infostr = 'TSource Engine Query' . pack('x');
	$this->plrstr = 'U';
	$this->rulestr = 'V';
	$this->pingstr = 'i';

	$this->challenge_id = '';
}

function gametype() {
	return "halflife";
}

function modtype() {
	$m = $this->data['gamedir'];
	if ($m == 'czero') $m = 'cstrike';
	return $m;
}

// sets and returns the halflife version to properly decode packets
function _hlver($header=NULL) {
	// If no header is passed in we use $this->raw instead.
	// If no header was passed in and we already have a version, return it.
	// If you want to force a version check, pass in the header bytes yourself.
	if ($header === NULL and $this->halflife_version) {
		return $this->halflife_version;
	}
	if ($header === NULL) {
		$header = $this->raw;
	}
	if ($header === NULL) {		// if header is still null, we have no packet bytes to check
		$this->halflife_version = 0;		
	} else {
		$code = @substr($header, 4, 1);
		if (!$code) {
			$this->halflife_version = 0;
		} elseif (in_array($code, array('m', 'o'))) {
			$this->halflife_version = 1;
		} else {
			$this->halflife_version = 2;
		}
	}
	return $this->halflife_version;
}

function _getchallenge($ip=NULL) {
	if (!$ip) $ip = $this->ipaddr();
	if (!$ip) return '';
	if (!$this->challenge_id) {
		$res = $this->_sendquery($ip, 'W');
		if (!$res) return $this->challenge_id;
		if ($this->raw != '') {
			$this->raw = substr($this->raw, 5);		// strip off response header bytes
			$this->challenge_id = $this->_getlong();
		}
	}
	return $this->challenge_id;
}

// The sub-class must always contain this method to query basic server information
function query_info($ip=NULL) {
	if (!$ip) $ip = $this->ipaddr();
	if (!$ip) return FALSE;
	$start = $this->_getmicrotime();
	$res = $this->_sendquery($ip, $this->infostr);
	$end = $this->_getmicrotime();
	if (!$res) return FALSE;
	$ver = $this->_hlver();				// get proper version 
	$this->data = array();					// query_info always resets the data array (so call this before other queries)
	if ($this->raw != '') {
		$this->data['ping'] = ceil(($end - $start) * 1000);	// return the time (ms) it took for the packet to return (ping)
		$this->data['ipport'] = $ip;
		list($this->data['ip'], $this->data['port']) = explode(':', $this->data['ipport']);

		if ($ver == 1) {
			return $this->_parse_info_halflife1();
		} elseif ($ver == 2) {
			return $this->_parse_info_halflife2();
		} else {
			$this->errstr = "Unknown server version; Unable to decode network packet";
			trigger_error($this->errstr, E_USER_WARNING);
			return FALSE;
		}
	}
	return FALSE;
}

// internal function to parse halflife verion 1 'info' packets
function _parse_info_halflife1() {
	$this->raw = substr($this->raw, 5);		// strip off response header bytes
	$this->data['int_ipport'] 	= $this->_getnullstr();
	list($this->data['int_ip'], $this->data['int_port']) 	= explode(':', $this->data['int_ipport']);
	$this->data['name'] 		= $this->_getnullstr();
	$this->data['map'] 		= $this->_getnullstr();
	$this->data['gamedir'] 		= $this->_getnullstr();
	$this->data['gamename'] 	= $this->_getnullstr();
	$this->data['totalplayers']	= $this->_getbyte();
	$this->data['maxplayers']	= $this->_getbyte();
	$this->data['protocol']		= $this->_getbyte();	// 47
	$this->data['servertype']	= $this->_getchar();
	$this->data['serveros']		= $this->_getchar();
	$this->data['serverlocked']	= $this->_getbyte();
	$this->data['modrunning']	= $this->_getbyte();
	$this->data['modurl']		= $this->data['modrunning'] ? $this->_getnullstr() : '';
	$this->data['modftp']		= $this->data['modrunning'] ? $this->_getnullstr() : '';
	$this->_getnullstr(); 					// depreciated string, ignore it
	$this->data['modver']		= $this->_getshort() . '.' . $this->_getshort();
	$this->data['modsize']		= $this->_getlong();
	$this->data['modserveronly']	= $this->_getbyte();
	$this->data['modclientdll']	= $this->_getbyte();
	return $this->data;
}

// internal function to parse halflife version 2 'info' packets
function _parse_info_halflife2() {
	$this->raw = substr($this->raw, 5);	// strip off response header bytes
	$this->data['protocol']			= $this->_getbyte();	// 6
	$this->data['name'] 			= $this->_getnullstr();
	$this->data['map'] 			= $this->_getnullstr();
	$this->data['gamedir'] 			= $this->_getnullstr();
	$this->data['gamename'] 		= $this->_getnullstr();
	$this->data['appid']			= $this->_getbyte() | ($this->_getbyte() << 8);
	$this->data['totalplayers']		= $this->_getbyte();
	$this->data['maxplayers']		= $this->_getbyte();
	$this->data['maxbots']			= $this->_getbyte();
	$this->data['servertype']		= $this->_getchar();
	$this->data['serveros']			= $this->_getchar();
	$this->data['serverlocked']		= $this->_getbyte();
	$this->data['serversecure']		= $this->_getbyte();
	return $this->data;
}

// The sub-class must always contain this method to query the active players list
function query_players($ip=NULL) {
	if (!$ip) $ip = $this->ipaddr();
	if (!$ip) return FALSE;
	$res = $this->_sendquery($ip, $this->plrstr . pack("V", $this->_getchallenge($ip)));
	if (!$res) return FALSE;
	if (!empty($this->raw)) {
		$this->raw = substr($this->raw, 5);		// strip off response header bytes
		$this->data['activeplayers'] 	= $this->_getbyte();
		$this->data['players'] 		= array();
		for ($i=1; $i <= $this->data['activeplayers']; $i++) {
			if ($this->raw == '') break;
			$this->data['players'][] = array(
				'id'		=> $this->_getbyte(),
				'name'		=> $this->_getnullstr(),
				'kills'		=> $this->_getlong(),
				'onlinetime'	=> (int)$this->_getfloat()
			);
		}
		$this->data['activeplayers'] = count($this->data['players']);
		return $this->data;
	}
	return FALSE;
}

// The sub-class must always contain this method to query the server rules list
function query_rules($ip=NULL) {
	if (!$ip) $ip = $this->ipaddr();
	if (!$ip) return FALSE;
	$res = $this->_sendquery($ip, $this->rulestr . pack("V", $this->_getchallenge($ip)));
	if (!$res) return FALSE;
	if (!empty($this->raw)) {
		$this->raw = substr($this->raw, 5);		// strip off response header bytes
		$this->data['totalrules'] = ($this->_getbyte() | ($this->_getbyte() << 8)) - 1;
		$this->data['rules'] = array();
		for ($i=1; $i <= $this->data['totalrules']; $i++) {
			$this->data['rules'][ trim($this->_getnullstr()) ] = trim($this->_getnullstr());
		}
		return $this->data;
	}
	return FALSE;
}

// The sub-class must always contain this method to 'ping' the server.
function query_ping($ip=NULL) {
	if (!$ip) $ip = $this->ipaddr();
	if (!$ip) return FALSE;
	$start = $this->_getmicrotime();
	$this->_sendquery($ip, $this->pingstr);
	$end = $this->_getmicrotime();
	if ($this->raw == '') {				// server did not respond, or did not respond properly
		return FALSE;
	} else {					// we got SOMETHING, so calculate the ping time
		return ($end - $start) * 1000;
	}
}

// The sub-class does not have to provide an rcon method, but it's recommended (if possible)
function query_rcon($command, $password=NULL, $ip=NULL){
	if (!$ip) $ip = $this->ipaddr();
	if (!$ip) return FALSE;

	// If we don't know the version of the server, try to figure it out automatically
	if (!$this->_hlver()) {
		$olddata = $this->data;			// save any current data
		$this->query_info($ip);			// 'info' query will tell us what version we're using
		$this->data = $olddata;			// restore our data
		if (!$this->_hlver()) {			// if, for some reason, we still don't know the version, return false
			$this->errstr = "Unknown server version; Unable to complete RCON command";
			trigger_error($this->errstr, E_USER_WARNING);
			return FALSE;
		}
	}

	// now we can query the server as we normally would
	$v = $this->_hlver();
	if ($v == 2) {
		return $this->query_rcon2($command, $password, $ip);
	} elseif ($v == 1) {
		return $this->query_rcon1($command, $password, $ip);
	} else {
		$this->errstr = "Unknown server version; Unable to complete RCON command";
		trigger_error($this->errstr, E_USER_WARNING);
		return FALSE;
	}
}

// issues an RCON command to a halflife version 1 server (non-source)
function query_rcon1($cmd, $password=NULL, $ip=NULL) {
	if (!$ip) $ip = $this->ipaddr();
	if (!$ip) return FALSE;

	if (empty($password)) $password = $this->rconpass;
	if (!$this->rconchallenge) {
		$res = $this->_sendquery($ip, "challenge rcon\n");
		if (preg_match('/^....challenge rcon (\\d+)/', $this->raw, $m)) {
			$this->rconchallenge = $m[1];
		}
	}

	$this->_sendquery($ip, sprintf("rcon %s \"%s\" %s", $this->rconchallenge, $password, $cmd));
	if ($output = preg_replace('/^....l/', '', $this->raw)) {
		// insert custom 'formatting' logic here .... someday
		return $output;
	} else {
		return FALSE;
	}
}

// issues an RCON command to a halflife version 2 server (source)
function query_rcon2($command, $password=NULL, $ip=NULL) {
	if (!$ip) $ip = $this->ipaddr();
	if (!$ip) return FALSE;
	if ($password == NULL) $password = $this->rconpass;
	if (strpos($ip, ":") !== FALSE) {
		list($ip, $port) = explode(':', $ip);
	} else {
		$port = '27015';
	}

	// connect to the server's tcp port
	$this->rconsock = @fsockopen("tcp://$ip", $port, $this->errno, $this->errstr);
	$this->_set_timeout($this->conf['timeout'], $this->rconsock);
	if (!$this->rconsock) {
		trigger_error("Failed to connect to socket on $ip:$port", E_USER_WARNING);
		return FALSE;
	}

	// Authenticate ...
	if (!$this->_rconauth2($password)) {
		$this->errstr = "RCON Authentication Failure";
		trigger_error($this->errstr, E_USER_WARNING);
		return FALSE;
	}


	$output = $this->_rconcmd2($command);
	@fclose($this->rconsock);

	return $output;
}

function _rconcmd2($command) {
	$output = "";

	if (!$this->_rconwrite2(PQ_HL2_SERVERDATA_EXECCOMMAND, $command)) {
		$this->errstr = "Failure sending RCON command";
		trigger_error($this->errstr, E_USER_WARNING);
		return FALSE;
	}
	$result = $this->_rconread2();

	return is_array($result) ? $result['string1'] : FALSE;
}

function _rconauth2($password=NULL) {
	if ($password == NULL) $password = $this->rconpass;
	if (!$this->_rconwrite2(PQ_HL2_SERVERDATA_AUTH, $password)) {
		trigger_error("Failure sending authentication request", E_USER_WARNING);
		return FALSE;
	}
	$ack = $this->_rconread2();		// ignore the first packet returned, it's empty (SERVERDATA_RESPONSE_VALUE)
/*
	if ($ack['responseid'] != PQ_HL2_SERVERDATA_RESPONSE_VALUE) {
		trigger_error("Unexpected packet response", E_USER_WARNING);
		return FALSE;
	}
*/

	$res = $this->_rconread2();		// read actual result packet
	return ($res['responseid'] == PQ_HL2_SERVERDATA_AUTH_RESPONSE && $res['requestid'] != -1);
}

// read a command response from the open RCON stream.
function _rconread2() {
	if (!$this->rconsock) return FALSE;
	$packet = array();
	$this->raw = "";
	$psize = 0;
	$size = 0;
	$total = 0;
	$string1 = "";
	$string2 = "";

	$first = 1;
	$expected = 0;
	do {
		if (!($psize = @fread($this->rconsock, 4))) {	// get the size of the packet (packed)
			break;
		}
		$size = $this->_unpack('V', $psize);		// convert packed size into an integer

		$this->raw = @fread($this->rconsock, $size);

		if ($this->DEBUG) print "DEBUG: Received (size: $size):\n" . $this->hexdump($psize . $this->raw) . "\n";
		$packet = array(
			'requestid'	=> $this->_getlong(),
			'responseid'	=> $this->_getlong(),
			'string1'	=> $this->_getnullstr(),
			'string2'	=> $this->_getnullstr(),
		);

		// combine multi-part-packets into single strings
		$string1 .= $packet['string1'];
		$string2 .= $packet['string2'];

		$expected = ($size >= 3096);			// if the size was >= ~3096 we should expect another packet
		$first = 0;					// first packet has gone through
	} while ($expected);

	if ($packet) {
		$packet['string1'] = $string1;
		$packet['string2'] = $string2;
	}
	return $packet;
}

// write a command to the open RCON steam, does not wait for a response.
function _rconwrite2($cmd, $str1="", $str2="") {
	if (!$this->rconsock) return FALSE;
//	$authid = ++$this->rcon_auth_id;		// get next id
	$authid = $this->rcon_auth_id;
	$data = pack("VV", $authid, $cmd) . $str1 . "\0" . $str2 . "\0";
	$packet = pack("V", strlen($data)) . $data;
	if ($this->DEBUG) print "DEBUG: Sending rcon packet:\n" . $this->hexdump($packet) . "\n";
	return @fwrite($this->rconsock, $packet, strlen($packet));
}

// query the master server for an IP listing (steam master servers only!)
function query_master($ip=NULL, $filter=array(), $callback=NULL) {
	if (!$ip) $ip = $this->ipaddr();
	if (!$ip) return FALSE;
	$filter += array(				// setup filter defaults
		'type'		=> '',
		'secure'	=> '',
		'gamedir'	=> '',
		'map'		=> '',
		'linux'		=> '',		
		'empty'		=> '',
		'proxy'		=> '',
		'region'	=> 0,			// 0..7
	);
	$filterstr = "";
	$region = $filter['region'];
	foreach ($filter as $key => $value) {
		if ($key == 'region') continue;		// not part of the filters
		if ($value !== '') {
			$filterstr .= "\\$key\\$value";
		}
	}
	if ($filterstr != '') $filterstr .= "\\";	// add trailing slash
	$filterstr .= "\0";				// null terminate string

	$iplist = array();
	$lastip = '0.0.0.0:0';
	$res = 0;
	do {
		$res = $this->_sendmasterquery($ip, $lastip, $region, $filterstr);
		if ($res) {
			$this->raw = substr($this->raw, 6);		// strip off header bytes
			$currentlist = array();
			// decode ip addresses and ports from the raw data
			while ($this->raw != '') {
				$newip = "";
				$newip .= $this->_getbyte() . '.';
				$newip .= $this->_getbyte() . '.';
				$newip .= $this->_getbyte() . '.';
				$newip .= $this->_getbyte() . ':';
				$newip .= ($this->_getbyte() << 8) | $this->_getbyte();
				if ($newip != $lastip) {
					$lastip = $newip;
					if ($lastip != '0.0.0.0:0') {
						$currentlist[] = $lastip;
					}
				}
			}
			$iplist = array_merge($iplist, $currentlist);
			if ($callback and function_exists($callback)) {
				$callback($currentlist);
			}
		}
	} while ($res and $lastip != '0.0.0.0:0');	// loop until the last ip seen is 0.

	return $iplist;
}

// returns the connect string url that allows you to connect to a server from a web page.
// Note: a query_info() must have already of been performed for this to work
function connect_url($connectip = NULL) {
	return "steam://connect/" . ($connectip ? $connectip : $this->ipaddr());
/* old method of connecting (before updates roughly around November '05).
// Thanks to JTP10181 (www.thekingpin.net) for the code
	$launchdir = "";
	if (!$this->data['appid']) { 
		switch ($this->data['gamedir']) { 
			case "cstrike":		$this->data['appid'] = 10; break; 
			case "tfc":		$this->data['appid'] = 20; break; 
			case "dod":		$this->data['appid'] = 30; break; 
			case "dmc":		$this->data['appid'] = 40; break; 
			case "gearbox":		$this->data['appid'] = 50; break; 
			case "ricochet":	$this->data['appid'] = 60; break; 
			case "valve":		$this->data['appid'] = 70; break; 
			case "czero":		$this->data['appid'] = 80; break; 
			//Third party mods 
			default: 
				$this->data['appid'] = 70; 
				$launchdir = " -game " . $this->data['gamedir']; 
				break; 
		}
	}
//	return "steam:" . urlencode(sprintf("\"-applaunch %s%s +connect %s\"", 
	return sprintf("steam:%%22-applaunch %s%s +connect %s%%22", 
		$this->data['appid'],
		$launchdir,
		$this->ipaddr()
	);
*/
}

// internal function to send a non-authoritative query to a halflife server (NOT RCON COMMANDS)
function _sendquery($ipport, $cmd) {
	list($ip,$port) = explode(':', $ipport);
	if (!$port) $port = '27015';
	$retry = 0;
	$oldmqr = get_magic_quotes_runtime();

	if (!$this->_connectsocket($ip, $port)) {
		trigger_error("Failed to connect to socket on $ip:$port", E_USER_WARNING);
		return FALSE;
	}

	$packets = array();					// stores each packet seperately, so we can combine them afterwards
# on some systems using 0xFFFFFFFF causes PHP to actually pack 0x7FFFFFFF and it breaks the query
	$command = pack("V", -1) . $cmd;
#	$command = pack("V", 0xFFFFFFFF) . $cmd;
#	$command = pack("v", 0xFFFF) . pack("v", 0xFFFF) . $cmd;
	$this->raw = "";

	if ($oldmqr) set_magic_quotes_runtime(0);
	if ($this->DEBUG) print "DEBUG: Sending query to $ip:$port:\n" . $this->hexdump($command) . "\n";
	fwrite($this->sock, $command, strlen($command));
	$start = $this->_getmicrotime();

	$expected = 0;						// # of packets we're expecting
	do {
		$packet = fread($this->sock, 1500);
		if (strlen($packet) == 0) {
			$retry++;
			if ($this->DEBUG) print "DEBUG: Resending query to $ip:$port:\n" . $this->hexdump($command) . "\n";
			fwrite($this->sock, $command, strlen($command));
#			$start = $this->_getmicrotime();
			$expected = 1;
			continue;
		}

		$time = sprintf("%0.4f", $this->_getmicrotime() - $start);
		if ($this->DEBUG) print "DEBUG: ($time latency) Received " . strlen($packet) . " bytes from $ip:$port:\n" . $this->hexdump($packet) . "\n";

		$header = substr($packet, 0, 4);				// get the 4 byte header
		$ack = @unpack("N1split", $header);
		$split = sprintf("%u", $ack['split']);
		if ($this->DEBUG) print "DEBUG: ACK = " . sprintf("0x%X", $ack['split']) . "\n";
		if ($split == 0xFeFFFFFF) {				// we need to deal with multiple packets
			$packet = substr($packet, 4);				// strip off the leading 4 bytes
			$header = substr($packet, 0, 5);			// get the 'sub-header ack'
			$packet = substr($packet, 5);				// strip off 32bit int ID, seq# and total packet#
			$info = @unpack("N1id/C1byte", $header);		// we don't really care about the ID
			if ($this->DEBUG) printf("DEBUG: Sub ACK: %X (%08b)\n", $info['byte'], $info['byte']);
			if (!$expected) $expected = $info['byte'] & 0x0F;	// now we know how many packets to receive
			$seq = (int)($info['byte'] >> 4);			// get the sequence number of this packet
			$packets[$seq] = $packet;				// store the packet
			$expected--;
		} elseif ($split == 0xFFFFFFFF) {				// we're dealing with a single packet
			$packets[0] = $packet;
			$expected = 0;
		}
	} while ($expected and $retry < $this->maxretries());

	fclose($this->sock);
	if ($oldmqr) set_magic_quotes_runtime(1);
	ksort($packets, SORT_NUMERIC);
	$this->raw = implode('', $packets);				// glue the packets together to make our final data string
	return TRUE;
}

// internal function to send a query to the master halflife servers (steam) for an IP list.
// returns a single packet. Call method repeatedly with a new $startip to get a full list.
function _sendmasterquery($ipport, $startip, $region=0, $filterstr="\0") {
	list($ip,$port) = explode(':', $ipport);
	if (!$port) $port = '27010';
	$retry = 0;
	$oldmqr = get_magic_quotes_runtime();

	if (!$this->masterconnected) {
		if (!$this->_connectsocket($ip, $port)) {
			trigger_error("Failed to connect to socket on $ip:$port", E_USER_WARNING);
			$this->masterconnected = FALSE;
			return FALSE;
		} else {
			$this->masterconnected = TRUE;
		}
	}

	$command = '1' . pack("C", $region) . $startip . "\0" . $filterstr;
	$this->raw = "";

	if ($oldmqr) set_magic_quotes_runtime(0);
	if ($this->DEBUG) print "DEBUG: Sending query to $ip:$port:\n" . $this->hexdump($command) . "\n";
	fwrite($this->sock, $command, strlen($command));
	
	do {
		$this->raw = fread($this->sock, 1500);
		if (strlen($this->raw) == 0) {
			if ($retry >= $this->maxretries()) {
				fclose($this->sock);
				$this->masterconnected = FALSE;
				return FALSE;
			}
			$retry++;
			if ($this->DEBUG) print "DEBUG: Resending query to $ip:$port:\n" . $this->hexdump($command) . "\n";
			fwrite($this->sock, $command, strlen($command));
			continue;
		} else {
			if ($this->DEBUG) print "DEBUG: Received " . strlen($this->raw) . " bytes from $ip:$port:\n" . $this->hexdump($this->raw) . "\n";
			break;
		}
	} while ($retry < $this->maxretries());

#	fclose($this->sock);
#	$this->masterconnected = FALSE;
	if ($oldmqr) set_magic_quotes_runtime(1);
	return TRUE;
}

} // end of PQ_halflife class

?>