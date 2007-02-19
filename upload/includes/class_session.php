<?php
/**
	2005 (c) Standard SESSION class written by Jason Morriss <stormtrooper@psychostats.com>
	Feel free to use this software anyway you wish. If you do all I ask
	is that you leave this copywrite notice in place and possibly
	let me know how you're using this software. Please also let me know
	if you have any suggestions/updates for the software.

	This software is provided as-is with NO WARRANTIES. If something breaks
	for you due to this software Jason Morriss can not be held liable.

	NOTE: This class assumes that all request variables have already had their 
	quotes stripped (if magic_quotes_gpc is on). Also this class assumes the 
	$db handle is my class_DB.php.
*/

// MYSQL session table for this session class is at the bottom of this file.
if (!defined('VALID_PAGE')) die(basename(__FILE__) . " 666 Error");
if (defined("CLASS_PSSESSION_PHP")) return 1;
define('CLASS_PSSESSION_PHP', 1);

class PS_Session { 
	var $config = array();
	var $_is_bot = NULL;
	var $_is_new = 0;
	var $db = 0;
	var $ended = 0;
	var $sidmethod = 'get';
	var $SESSION_BOTS = array();
	var $sessdata = array(				// stores all the session data (stored in the database, not the cookie)
		'session_id'		=> '',			// always 32 characters long
		'session_userid'	=> 0,
		'session_start'		=> 0,
		'session_last'		=> 0,
		'session_ip'		=> 0,
		'session_logged_in'	=> 0,
		'session_is_bot'	=> 0,
	);

// session constructor. Sets defaults and will automatically start a new session or load an existing one
function PS_Session($_config=array()) {
	$this->config = array(
		'delaystart'		=> 0,
		'cookielife'		=> 60 * 60,			// 1 hour
		'cookiedomain'		=> '',
		'cookiepath'		=> '/',
		'cookiename'		=> 'sess',
		'cookiesecure'		=> 0,
		'secretkey'		=> '',				// mcrypt module must be installed if this is !empty	
		'login_callback_func'	=> '',
		'match_agent_ip'	=> FALSE,
		'dbuser'		=> '',
		'dbpass'		=> '',
		'dbhost'		=> 'localhost',
		'dbname'		=> 'sessions',
		'dbsessiontable'	=> 'sessions',
		'dbusertable'		=> '', //'users',
		'dbusersessionlast'	=> 'session_last',		// table field name to update the users last session request
		'dbuserlastvisit'	=> 'lastvisit',			// table field name to update the users last (previous) visit
		'dbuserid'		=> 'userid',			// table field name for the users "user id"
		'dbhandle'		=> 0,
	);

	// agent, name, ip substrs
	$this->SESSION_BOTS = array();
	$this->SESSION_BOTS[] = NULL;		// we don't want index 0 to be used
	$this->SESSION_BOTS[] = array('Google', 	'Googlebot', 	'216.239.46.|64.68.8|64.68.9|164.71.1.|192.51.44.|66.249.71.|66.249.64.|66.249.65.|66.249.66.');
	$this->SESSION_BOTS[] = array('ia_archiver', 	'Alexa', 	'66.28.250.|209.237.238.');
	$this->SESSION_BOTS[] = array('Slurp/', 	'Inktomi', 	'216.35.116.|66.196.|66.94.230.|202.212.5.');
	$this->SESSION_BOTS[] = array('Infoseek', 	'Infoseek', 	'204.162.9|205.226.203|206.3.30.|210.236.233.');
	$this->SESSION_BOTS[] = array('Scooter', 	'Alta Vista', 	'194.221.84.|204.123.28.|208.221.35|212.187.226.|66.17.148.');
	$this->SESSION_BOTS[] = array('Lycos', 		'Lycos', 	'208.146.27.|209.202.19|209.67.22|202.232.118.');
	$this->SESSION_BOTS[] = array('alltheweb', 	'FAST', 	'146.101.142.2|216.35.112.|64.41.254.2|213.188.8.');
	$this->SESSION_BOTS[] = array('WISEnut', 	'WiseNut', 	'64.241.243.|209.249.67.1|216.34.42.|66.35.208.');
	$this->SESSION_BOTS[] = array('msnbot/', 	'MSN',  	'131.107.3.|204.95.98.|131.107.1|65.54.164.95|65.54.164.3|65.54.164.4|65.54.164.5|65.54.164.6|207.46.98.');
	$this->SESSION_BOTS[] = array('MARTINI', 	'Looksmart', 	'64.241.242.|207.138.42.212');
	$this->SESSION_BOTS[] = array('teoma', 		'Ask Jeeves', 	'216.200.130.|216.34.121.|63.236.92.1|64.55.148.|65.192.195.|65.214.36.');
	//$this->SESSION_BOTS[] = array('', '', '');
	// This is used as debugging...
//	$this->SESSION_BOTS[] = array('.', 	'Test bot', 	'69.140.42.132');


	$this->config = array_merge($this->config, $_config);
	$this->db = $this->config['dbhandle'];
	$this->is_bot();

	$this->_initkey();
	if (!$this->config['delaystart']) $this->start();		// start session if its not 'delayed'
}

function is_bot() {
	if (!is_null($this->_is_bot)) return $this->_is_bot;
	$ip = $_SERVER['REMOTE_ADDR'];
	$agent = $_SERVER['HTTP_USER_AGENT'];
	$ip_match = $this->conf['match_agent_ip'] ? 0 : 1;
	$agent_match = 0;

	foreach ($this->SESSION_BOTS as $idx => $row) {
#		print "$idx => $value<br>";
		if (!$row) continue;
		foreach (explode('|', $row[0]) as $bot_agent) {
#			print "$agent == $bot_agent<BR>";
			if ($bot_agent != '' && preg_match('/' . preg_quote($bot_agent, '/') . '/i', $agent)) {
#				print "AGENT MATCH!!!<BR>\n";
				$agent_match = $idx;
				break;
			}
		}

		if ($agent_match and !$ip_match) {
			foreach (explode('|', $row[2]) as $bot_ip) {
#				print "$ip == $bot_ip<BR>";
				if ($bot_ip != '' && strpos($ip, $bot_ip) === 0) {
#					print "IPADDR MATCH!!!<BR>\n";
					$ip_match = $idx;
					break;
				}
			}
		}

		if ($agent_match and $ip_match) break;
	}

	// agent_match and ip_match will always be the same bot index
	$this->_is_bot = ($agent_match and $ip_match) ? $agent_match : 0;
	return $this->_is_bot;
}

function bot_name($idx) {
	if (array_key_exists($idx, $this->SESSION_BOTS) and is_array($this->SESSION_BOTS[$idx])) {
		return $this->SESSION_BOTS[$idx][1];
	} else {
		return '';
	}
}

// returns the name of the SID cookie
function sidname($suffix='_id') {
	return $this->config['cookiename'] . $suffix;
}

// generates a new random SID. If you provide the $random string it will be used to help generate the md5 hash.
function generate_sid($random="") {
	if ($this->_is_bot) {
		return sprintf("%032d", $this->_is_bot);
	} else {
		return md5(time() . mt_rand()  . $random);
	}
}

// delete expired sessions
function garbage_collect() {
	$now = time();
	$cmd = "DELETE FROM {$this->config['dbsessiontable']} WHERE ($now - session_last > {$this->config['cookielife']})";
	$res = $this->db->query($cmd);
}

// returns the current session SID from a COOKIE or GET data. Returns FALSE if there is none
function _find_user_sid() {
	$this->garbage_collect();
	$name = $this->sidname();
	$sid = FALSE;
	if ($_COOKIE[$name] != '') {
		$this->sidmethod = 'cookie';
		$sid = $_COOKIE[$name];
	} elseif ($_GET[$name] != '') {
		$this->sidmethod = 'get';
		$sid = $_GET[$name];
		$_COOKIE[$name] = $sid;
	} elseif ($_POST[$name]) {
		$this->sidmethod = 'get';		// we do not distinguish between get/post
		$sid = $_POST[$name];    
		$_COOKIE[$name] = $sid;
	} else {
		$this->sidmethod = '';
	}
//	if ($sid != FALSE and get_magic_quotes_gpc()) stripslashes($sid);
	return $sid;
}

function is_new() {
	return $this->_is_new;
}

function is_sid($sid) {
	return ereg('^[a-f0-9]{32}$', strtolower($sid));
}

// sets a cookie for the user based on the cookie settings we have. $suffix is the trailing part of the SID name. 
// '_id' or '_login'
function sendcookie($data, $time=0, $suffix='_id') {
//	print "SENDCOOKIE: '$suffix': $data<br>";
	return setcookie(
		$this->sidname($suffix), 
		$data, 
		$time, 
		$this->config['cookiepath'], 
		$this->config['cookiedomain'], 
		$this->config['cookiesecure']
	);
}

// short-cut method for deleting a users cookie.
function delcookie($suffix='_id') {
//	print "DELCOOKIE: '$suffix' (the next sendcookie line will be the deletion)<br>";
	return ($_COOKIE[ $this->sidname($suffix)]) ? $this->sendcookie("", time()-100000, $suffix) : 0;
}

function _read_session($sid) {
	$res = $this->db->query("SELECT * FROM " . $this->config['dbsessiontable'] . " WHERE session_id='" . addslashes($sid) . "'");
	if (!$res) die("Fatal Session Error at line " . __LINE__ . ": " . $this->db->lasterr());
	$this->sessdata = $this->db->num_rows() > 0 ? $this->db->fetch_row() : $this->_init_new_session();
//	print "READ SESSION ... <br>";
//	print_r($this->sessdata); print "<bR>";
}

function _save_session() {
	if ($this->ended) return 1;		// do not save anything if the session was end()'ed
	$res = $this->db->query("SELECT session_id FROM `" . $this->config['dbsessiontable'] . "` WHERE session_id='" . addslashes($this->sessdata['session_id']) . "'");
	list($exists) = $this->db->fetch_row(0);
	if (!$res) die("Fatal Session Error at line " . __LINE__ . ": " . $this->db->lasterr());
	$prefix = ($exists) ? "UPDATE" : "INSERT INTO";

	$cmd = "$prefix `" . $this->config['dbsessiontable'] . "` SET ";
	foreach ($this->sessdata as $k => $v) $cmd .= "$k='$v', ";
	$cmd = substr($cmd, 0, -2);						// strip off trailing ', '
	if ($exists) $cmd .= " WHERE session_id='" . addslashes($this->sessdata['session_id']) . "'";
//	print "SAVE SESSION: $cmd<br>";
	$res = $this->db->query($cmd);
	if (!$res) die("Fatal Session Error at line " . __LINE__ . ": " . $this->db->lasterr());
}

function _delete_session($sid) {
	return $this->db->delete($this->config['dbsessiontable'], 'session_id', $sid);
}

function _init_new_session() {
//	print "INIT SESSION ... <br>";
	$this->_is_new = 1;
	$this->sid = $this->generate_sid();
	$this->sessdata = array(
		'session_id'		=> $this->sid,
		'session_userid'	=> 0,
		'session_start'		=> time(),
		'session_last'		=> time(),
		'session_ip'		=> sprintf("%u", ip2long($_SERVER['REMOTE_ADDR'])),
		'session_logged_in'	=> 0,
		'session_is_bot'	=> $this->_is_bot,
	);
}

// private method to get or set the users current SID cookie
function _session_start() {
	$sid = $this->_find_user_sid();
	if (!$sid or !$this->is_sid($sid)) {
		print "NEW SESSION STARTING ... <BR>";
		$this->_init_new_session();
		$this->_save_session();				// always SAVE when we create a new session
		$this->sendcookie($this->sid);			// cookie will expire at the end of the browser session
	} else {
		print "PREVIOUS SESSION STARTING ... <BR>";
		$this->_read_session($sid);
		$this->sid = $sid; //$this->sessdata['session_id'];
		if ($this->_expired()) {
			$this->_delete_session($this->sid);		// deletes old session from database
			$this->_init_new_session();			// generate a new dataset
			$this->_save_session();
			$this->delcookie();				// delete old sess_id cookie (since we're only using PHP4 its ok to delete first, before sending another cookie below)
			$this->sendcookie($this->sid);			// send a new cookie
		}
	}
}

// starts the session (no need to call this unless delaystart is true) ----------------------------
function start() {
	$this->ended = 0;
	$this->_session_start();

	$now = time();
	$this->sessdata['session_last'] = $now;

	// If the user is NOT logged in and there is a 'login' cookie set, try to verify and log the user in automatically
	if ($this->onlinestatus()==0 and !empty($_COOKIE[ $this->sidname('_login') ])) {
		$enc = $_COOKIE[ $this->sidname('_login') ];
//		if (get_magic_quotes_gpc()) $enc = stripslashes($enc);
		$data = unserialize(base64_decode($this->decrypt($enc)));		// should be an array with a userid and password
		if (is_array($data) and $data['userid'] and $data['password']) {
			$login_callback = $this->config['login_callback_func'];
			$userid = (function_exists($login_callback)) ? $login_callback($data['userid'], $data['password']) : 0;
			if ($userid) {
				$this->onlinestatus(1, $userid);			// user is now magically online!
			} else {
				$this->delcookie('_login');				// login cookie was invalid, so delete it
			}
		} else {
			$this->delcookie('_login');				// login cookie was invalid, so delete it
		}
	}

	// Update the users LAST event timestamp (in the users database, not the session database). 
	// When the cookie expires this will remain in the user data to keep track of the last time the user did anything.
	// Used for keeping track of NEW messages, etc.
	// This must be done AFTER the autlogin block above! otherwise users that are auto logged in will never have the correct
	// 'last visit' timestamp.
	if (!empty($this->config['dbusertable'])) {
		if ($this->sessdata['session_userid'] > 0 and $this->sessdata['session_logged_in']) {
			$cmd = sprintf("UPDATE %s SET %s=$now WHERE %s='%s'", 
				$this->db->qi($this->config['dbusertable']), 
				$this->db->qi($this->config['dbusersessionlast']), 
				$this->db->qi($this->config['dbuserid']), 
				$this->db->escape($this->sessdata['session_userid'])
			);
//			print "UPDATE USER: $cmd<br>";
			$res = $this->db->query($cmd);
		}
	}
} // end function start()

// Saves the autologin cookie to the users browser so the next time they view the page they will be logged on automatically
function saveAutoLogin($userid, $password) {
	$ary = array('userid' => $userid, 'password' => $password);
	$data = $this->encrypt( base64_encode(serialize($ary)) );
	return $this->sendcookie($data, time()+60*60*24*30, '_login'); 		// autologin cookie is saved for 30 days
}

function removeAutoLogin() {
	$this->delcookie('_login');
	return 1;
}

function _initkey() {
	if (!$this->config['secretkey']) return 0;
	$this->td = mcrypt_module_open(MCRYPT_DES, '', MCRYPT_MODE_ECB, '');			// Open the cipher
	$this->iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($this->td), MCRYPT_DEV_RANDOM);	// Create the IV and determine the keysize length
	$this->ks = mcrypt_enc_get_key_size($this->td);
	$this->key = substr(md5($this->config['secretkey']), 0, $this->ks);			// Create key
	mcrypt_generic_init($this->td, $this->key, $this->iv);					// Intialize encryption
	return 1;
}

function encrypt($str) {
	if (!$this->config['secretkey']) return $str;
	$encrypted = mcrypt_generic($this->td, $str);						// Encrypt data 
	return $encrypted;
}
 
function decrypt($str) {
	if (!$this->config['secretkey']) return $str;
	$decrypted = trim(mdecrypt_generic($this->td, $str));
	return $decrypted;
}

// sets or gets the current online status for the session. If the online status is changed, the previous value is returned.
function onlinestatus($online=-1, $userid=0) {
	$status = $this->sessdata['session_logged_in'];			// get original status by default
	if ($online >= 1) {							// LOGIN THE USER
		$this->sessdata['session_logged_in'] = 1;
		$this->sessdata['session_userid'] = $userid;
		$this->_save_session();
		if (!empty($this->config['dbusertable'])) {
			$res = $this->db->query(sprintf("SELECT %s FROM %s WHERE %s='%s' LIMIT 1",
				$this->db->qi($this->config['dbusersessionlast']),
				$this->db->qi($this->config['dbusertable']),
				$this->db->qi($this->config['dbuserid']),
				$this->db->escape($userid)
			));
			list($last) = ($res) ? $this->db->fetch_row(0) : time();
			$res = $this->db->query(sprintf("UPDATE %s SET %s=$last WHERE %s='%s'",	// update the USER table
				$this->db->qi($this->config['dbusertable']),
				$this->db->qi($this->config['dbuserlastvisit']),
				$this->db->qi($this->config['dbuserid']),
				$this->db->escape($this->sessdata['session_userid'])
			));
		}
 	} elseif ($online == 0) {
		$this->sessdata['session_logged_in'] = 0;
		$this->sessdata['session_userid'] = 0;
		$this->_save_session();
		$this->removeAutoLogin();
	}
	return $status;
}

// returns the total seconds 'online' for the session ------------------------------------------------------------
function secondsonline() {
	$diff = $this->sessdata['session_last'] - $this->sessdata['session_start'];
	return ($diff > 0) ? $diff : 0;
}

function onlinetime() {
	return $this->secondsonline();
}

function userid() {
	return $this->sessdata['session_userid'];
}

// returns the total number of active sessions -------------------------------------------------------------------
// 5 minutes is generally a reasonable amount of time to wait before a session is 'inactive'
// if $wantarray is true, a 2 element array is is returned with the total 'members' and 'guests' online, respectively
function totalonline($timeframe=300, $wantarray=0) {
	$memebers = 0;
	$guests = 0;
	$now = time();

	$res = $this->db->query(sprintf("SELECT count(DISTINCT session_userid) FROM %s WHERE session_userid != 0 AND session_last + $timeframe > $now", 
		$this->db->qi($this->config['dbsessiontable'])
	));
	list($members) = $this->db->fetch_row(0);
	$res = $this->db->query(sprintf("SELECT count(*) FROM %s WHERE session_userid=0 AND session_last + $timeframe > $now", 
		$this->db->qi($this->config['dbsessiontable'])
	));
	list($guests) = $this->db->fetch_row(0);

	if ($wantarray) {
		return array( $members > 0 ? $members : 0, $guests > 0 ? $guests : 0);
	} else {
		$total = $members + $guests;
		return ($total > 0) ? $total : 1;
	}
}

// End/remove the session (including the user's SID cookie)
function end() {
	$this->delcookie('_id');
	$this->delcookie('_login');
	$this->_delete_session($this->sid);
	$this->sid = '';
	$this->sessdata = array();
	$this->_init_new_session();			// generate a new dataset, but it's not saved yet ...
	$this->ended = 1;
}

// closes the session. There's no need to call this unless you want to make sure the session is updated before 
// redirecting to another page.
function close() {
	$this->_save_session();
}

// internal function, returns true if the session has expired
function _expired() {
	return (time() - $this->sessdata['session_last'] > $this->config['cookielife']);
}

} // end of session class

/**

CREATE TABLE `ps_sessions` (
  `session_id` char(32) NOT NULL default '',
  `session_userid` int(10) unsigned NOT NULL default '0',
  `session_start` int(10) unsigned NOT NULL default '0',
  `session_last` int(10) unsigned NOT NULL default '0',
  `session_ip` int(10) unsigned NOT NULL default '0',
  `session_logged_in` tinyint(1) NOT NULL default '0',
  `session_is_bot` tinyint(3) NOT NULL default '0',
  PRIMARY KEY  (`session_id`),
  KEY `session_userid` (`session_userid`)
);

**/

?>
