<?php
/*
	Default User handler. 
	If you have a portal/user system on your site already and want to integrate PsychoStats
	into your site all you have to do is replace these routines with the proper queries, functions, etc,
	to actually authenticate users and handle the session.
*/
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

// set/override some theme settings
//$ps->conf['theme']['show_login']	= true;		// show login/out links
//$ps->conf['theme']['show_register']	= true;		// show register link
//$ps->conf['theme']['']		= false;


if (!defined("NOSESSION")) {
	// Initialize the session handler
	$ps_session = &new PS_Session(array(		// must always get a new REFERENCE (&) for the session, or it won't work
		'login_callback_func'	=> 'ps_do_login',
		'dbhandle'		=> &$ps_db,	// pass a DB reference
		'dbsessiontable'	=> $ps_db->dbtblprefix . "sessions",

// If your users table does not have these fields (or they're different) either change these values or set them to an empty value
		'dbusertable'		=> 'ps_user',
		'dbusersessionlast'	=> 'session_last',
		'dbuserlastvisit'	=> 'lastvisit',
		'dbuserid'		=> 'userid',

		'cookiename'		=> 'ps_sess',
		'cookiepath'		=> '/',
		'cookiesecure'		=> $ps->conf['theme']['usessl'] ? 1 : 0,
		'cookiedomain'		=> '', 
	));

	// Load the user if they're logged in
	if (user_logged_on()) {
		$ps_user = load_user();
	}

	$ps_user_opts = session_load_user_opts();
//	print_r($ps_user_opts);die;
}

function session_sidprefix() {
	global $ps_session;
	return $ps_session->config['cookiename'];
}

function session_sidname($suffix='_id') {
	global $ps_session;
	return $ps_session->sidname($suffix);
}

function session_sid() {
	global $ps_session;
	return $ps_session->sid;
}

function session_delete($sid) {
	global $ps_session;
	$ps_session->_delete_session($sid);
}

function session_load_user_opts() {
	$sidname = session_sidname('_opts');
	$o = array();
	if (array_key_exists($sidname, $_COOKIE)) {
		// decode -> deflate -> unserialize
		$str = $_COOKIE[$sidname];
		$decoded = $str;
		$decoded = base64_decode($str);
		if (function_exists('gzinflate')) $decoded = gzinflate($decoded);
		if ($decoded === FALSE) session_save_user_opts(array());
		$o = unserialize($decoded);
	}
	if (!is_array($o)) $o = array();
	return $o;
}

// deflate reduces the cookie size by about 1/2
function session_save_user_opts($opts=NULL) {
	global $ps_user_opts;
	if ($opts===NULL) $opts = $ps_user_opts;
	if (!is_array($opts)) $opts = array();
	// serialize -> deflate -> encode
	$str = serialize($opts);
	if (function_exists('gzdeflate')) $str = gzdeflate($str);
	$encoded = $str;
	$encoded = base64_encode($str);
	session_cookie($encoded, time()+60*60*24*30, '_opts');
//	session_cookie(strlen($encoded), time()+60*60*24*30, '_opts_size');	// debug
}

// returns true if the session is a BOT
function session_is_bot() {
	global $ps_session;
	return $ps_session->is_bot();
}

// should return the method of which we found a session ID: 'get', 'post', or 'cookie' 
function session_method() {
	global $ps_session;
	return $ps_session ? $ps_session->sidmethod : 'get';
}

function session_online_status($online, $userid=NULL) { 
	global $ps_session;
	return $ps_session ? $ps_session->onlinestatus($online, $userid) : 0;
}

// $password is already an MD5 hash
function session_save_autologin($userid, $password) {
	global $ps_session;
	if ($ps_session) $ps_session->saveAutoLogin($userid, $password);
}

function session_bot_name($i) {
	global $ps_session;
	return $ps_session->bot_name($i);
}

// closes (Ends) the current session
function session_close() {
	global $ps_session;
	if ($ps_session) $ps_session->close();
}

// sends a cookie
function session_cookie($data, $expire=0, $suffix='') {
	global $ps_session;
	if (!$ps_session) return; 
	$ps_session->sendcookie($data, $expire, $suffix);
	$_COOKIE[ $ps_session->sidname($suffix) ] = $data;		// fake the cookie for the current session
}

// used as the callback function for 'autoLogins' from the session handler routines
// the $password is an MD5 hash of the users normal password
function ps_do_login($id, $password) {
	global $ps;
//	print "$id == $password"; die;
	list($userid,$acl,$confirmed) = $ps->db->fetch_row(0, "SELECT userid,accesslevel,confirmed FROM $ps->t_user WHERE userid='" . $ps->db->escape($id) . "' AND password='" . $ps->db->escape($password) . "'");
	if ($acl < ACL_USER or !$confirmed) $userid = 0;
	return $userid;
}

// loads the user information, including profile and basic player information (if available)
// this function should return as much information as it can. Do not fail just because there's no plr or profile.
function load_user($userid=NULL) {
	global $ps, $ps_session;
	if ($userid === NULL) $userid = $ps_session->userid();
	$u = array();
	if (!$userid) return $u;
	$u = $ps->db->fetch_row(1, "SELECT pp.*,plr.*,u.* FROM $ps->t_user u
		LEFT JOIN $ps->t_plr_profile pp ON pp.userid=u.userid
		LEFT JOIN $ps->t_plr plr ON plr.uniqueid=pp.uniqueid
		WHERE u.userid='" . $ps->db->escape($userid) . "'"
	);
	return $u;
}

// loads the user information from userID or username. Do not include any related player information.
// If userid is NUMERIC then it's assumed to be an userid to load the user. Unless force_username is TRUE
// You only need to specify $force_username in your code if you ever feel it's possible that a
// username MIGHT be a number
// 	some variables must always be returned in the user array:
//		username, userid, password, confirmed, accesslevel
//	If your user_handler uses different variable names for some of those you need to convert them
// 	before returning the array of information.
function load_user_only($userid=NULL, $force_username=FALSE) {
	global $ps, $ps_session;
	if ($userid === NULL) $userid = $ps_session->userid();
	$u = array();
	if (!$userid) return $u;
	$u = $ps->db->fetch_row(1, sprintf("SELECT * FROM $ps->t_user u WHERE %s='%s'", 
		(is_numeric($userid) && !$force_username) ? 'u.userid' : 'u.username', 
		$ps->db->escape($userid)
	));
	return $u;
}

// loads all users that are currently logged in (and were active, at most, 15 minutes ago)
function load_online_users($timeframe=900) {
	global $ps;
	$list = array();
	$now = time();
#	$list = $ps->db->fetch_rows(1, "SELECT pp.*,plr.*,u.*,session_id,session_ip,session_start,s.session_last,session_logged_in,session_is_bot,cc.cc,cc.cn " .
	$list = $ps->db->fetch_rows(1, "SELECT pp.*,plr.*,u.*,session_id,session_ip,session_start,s.session_last,session_logged_in,session_is_bot " .
		"FROM $ps->t_sessions s " .
		"LEFT JOIN $ps->t_user u ON u.userid=s.session_userid " .
		"LEFT JOIN $ps->t_plr_profile pp ON pp.userid=u.userid " .
		"LEFT JOIN $ps->t_plr plr ON plr.uniqueid=pp.uniqueid " .
#		"LEFT JOIN $ps->t_geoip_ip ci ON session_ip BETWEEN start AND end " . 
#		"LEFT JOIN $ps->t_geoip_cc cc ON cc.cc=ci.cc " . 
		"WHERE s.session_last + $timeframe > $now ORDER BY u.username,pp.name,session_ip,session_start DESC"
	);
# Note: The geoip lookup slows this query down a lot; so I'm commenting it out for now
	return $list;
}

// same as 'load_user' except it uses a 'plrid' (and there may not be a user associated)
// this does not load any stats
function load_player($plrid=NULL) {
	global $ps, $ps_session, $ps_user;
	if ($plrid === NULL) $plrid = $ps_user['plrid'];
	$p = array();
	if (!$plrid) return $p;
	$p = $ps->db->fetch_row(1, "
		SELECT pp.*,plr.*,u.* FROM $ps->t_plr plr
		LEFT JOIN $ps->t_plr_profile pp ON pp.uniqueid=plr.uniqueid
		LEFT JOIN $ps->t_user u ON u.userid=pp.userid
		WHERE plr.plrid='" . $ps->db->escape($plrid) . "'"
	);
	return $p;
}

function update_user($set, $id) {
	global $ps;
	return $ps->db->update($ps->t_user, $set, 'userid', $id);
}

function insert_user($set) {
	global $ps;
	return $ps->db->insert($ps->t_user, $set);
}

function delete_user($id) {
	global $ps;
	$ps->db->delete($ps->t_user, 'userid', $id);
}

// returns true if the username specified already exists
function username_exists($username) {
	global $ps;
	return $ps->db->exists($ps->t_user, 'username', $username);
}

// returns the next valid userid that can be used for a new user
function next_user_id() {
	global $ps;
	return $ps->db->next_id($ps->t_user, 'userid');
}

// returns true if the session currently viewing the site is logged in or not.
// If a user is connected it returns the users ID, otherwise returns FALSE.
function user_logged_on() {
	global $ps_session;
	if (!$ps_session) return FALSE;
	$on = $ps_session->onlinestatus();
	return $on ? $ps_session->userid() : 0;
}

// Determines if the user or accesslevel given is higher than the level provided.
// $acl is either a $ps_user array, or a single integer representing a users accesslevel.
function user_has_access($acl, $minlevel=ACL_ADMIN) {
	$level = is_array($acl) ? $acl['accesslevel'] : $acl;
	return ($level >= $minlevel);
}

// Shortcut function to determine if the user logged in (or passed in $u) has standard USER privileges
function user_is_allowed($u=NULL) {
	global $ps_user;
	if ($u === NULL) $u = $ps_user;
	return user_has_access($u['accesslevel'], ACL_USER);
}

// Shortcut function to determine if the user logged in (or passed in $u) has CLAN ADMIN privileges
function user_is_clanadmin($u=NULL) {
	global $ps_user;
	if ($u === NULL) $u = $ps_user;
	return user_has_access($u['accesslevel'], ACL_CLANADMIN);
}

// Shortcut function to determine if the user logged in (or passed in $u) has ADMIN privileges
function user_is_admin($u=NULL) {
	global $ps_user;
	if ($u === NULL) $u = $ps_user;
	if (!$u) return FALSE;
	return user_has_access($u['accesslevel'], ACL_ADMIN);
}

// all URL's in PS output are run through this wrapper
function ps_url_wrapper($arg) {
	return url($arg);
}

// prints the output directly to the user (usually called with the output from theme->showpage)
function ps_showpage($output) {
	print $output;
}

?>
