<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if (defined("PSFILE_COMMON_PHP")) return 1;
define("PSFILE_COMMON_PHP", 1);

// If you uncomment the follow line all DB queries on a page will be output at the bottom
//define("DB_DEBUG", true);


define("PS_ROOTDIR", dirname(dirname(__FILE__)));

error_reporting(E_ERROR | E_WARNING | E_PARSE);		// ignore uninit var errors
//error_reporting(E_ALL);

set_magic_quotes_runtime(0);
require_once(PS_ROOTDIR . "/includes/undo_magic_quotes.php");
ob_start();

// seed random number if PHP version is less than 4.2.0
if (version_compare(PHP_VERSION, '4.2.0') == -1) {
	mt_srand(hexdec(substr(md5(microtime()), -8)) & 0x7fffffff);
}

if (!defined("NO_CONTENT_TYPE")) {
	@header('Content-Type: text/html; charset=utf-8');
}

// IIS does not have REQUEST_URI defined (apache specific)
if (!isset($_SERVER['REQUEST_URI'])) {
	$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
	if (!empty($_SERVER['QUERY_STRING'])) {
		$_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
	}
}

// setup global timer object
$TIMER = null;
if (!defined("NOTIMER")) {
	require_once(PS_ROOTDIR . "/includes/class_timer.php");
	$TIMER = new Timer();
}

$is_windows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

require_once(PS_ROOTDIR . "/includes/functions.php");
require_once(PS_ROOTDIR . "/includes/class_DB.php");
require_once(PS_ROOTDIR . "/includes/class_PS.php");
require_once(PS_ROOTDIR . "/includes/class_PQ.php");
require_once(PS_ROOTDIR . "/includes/class_session.php");
require_once(PS_ROOTDIR . "/includes/class_theme.php");
require_once(PS_ROOTDIR . "/includes/class_xml.php");

// init global variables
require_once(PS_ROOTDIR . "/config.php");
$theme		= null;
$newtheme 	= null;
$ps		= null;
$ps_db		= null;
$ps_session	= null;
$ps_lang	= null;
$data		= array();
$ps_user	= array();
$ps_user_opts	= array();	// extra options for the user session (from a cookie)
$PHP_SELF 	= $_SERVER['PHP_SELF'];

// initialize the DB object
$ps_db = DB::create(array(
	'fatal'		=> 0,
	'dbtype'	=> $dbtype,
	'dbhost'	=> $dbhost,
	'dbport'	=> $dbport,
	'dbname'	=> $dbname,
	'dbuser'	=> $dbuser,
	'dbpass'	=> $dbpass,
	'dbtblprefix'	=> $dbtblprefix
));

// initialize the PS object
$ps = new PS($ps_db);

// initialize the user handler
ob_start();
if (empty($userhandler)) $userhandler = 'normal';
$handlerok = include_once(PS_ROOTDIR . "/includes/user_handler_$userhandler.php");
$handlererr = ob_get_contents();
ob_end_clean();
if (!$handlerok and $ps_userhandler != 'normal') include_once(PS_ROOTDIR . "/includes/user_handler_normal.php");

// initialize the theme we want to use for output.
// if "NOTHEME" is defined then no theme class is created and it's up to the main PHP page
// to do something for output (usually images instead).
if (!defined("NOTHEME")) {
	// default to configured theme
	$theme = isset($ps_user_opts['theme']) ? $ps_user_opts['theme'] : $ps->conf['main']['theme'];
	$themeconf =& $ps->conf['theme'];
	if ($themeconf['allow_user_change'] and isset($_REQUEST['newtheme'])) {	// user selected a new theme (and is allowed to)
		$theme = $_REQUEST['newtheme'];
		$newtheme = $theme;
	}
	if (!$themeconf['themedir']) {						// default to local themes directory
		$themeconf['themedir'] = catfile(PS_ROOTDIR, 'themes');
	}
	if (!$themeconf['compiledir']) {					// default to local compile directory
		$themeconf['compiledir'] = tmppath('ps_themes_compiled');
	}

	if (!$themeconf['scripturl']) {
		$themeconf['scripturl'] = dirname($_SERVER['PHP_SELF']);
	}

	if (!$themeconf['themeurl']) {
		$themeconf['themeurl'] = rtrim($themeconf['scripturl'], '/\\') . '/themes/';
	}
	if (!$themeconf['rootimagesdir']) {
		$themeconf['rootimagesdir'] = catfile(PS_ROOTDIR, 'images');
	}
	if (!$themeconf['rootimagesurl']) {
		$themeconf['rootimagesurl'] = rtrim($themeconf['scripturl'], '/\\') . '/images/';
	}
	if (!$themeconf['rootmapsdir']) {
		$themeconf['rootmapsdir'] = catfile($themeconf['rootimagesdir'], 'maps');
	}
	if (!$themeconf['rootmapsurl']) {
		$themeconf['rootmapsurl'] = $themeconf['rootimagesurl'] . 'maps/';
	}
	$themeconf['smallmapsurl'] = catfile($themeconf['rootmapsurl'], $ps->conf['main']['gametype'], $ps->conf['main']['modtype']);
	$themeconf['largemapsurl'] = catfile($themeconf['rootmapsurl'], $ps->conf['main']['gametype'], $ps->conf['main']['modtype'], 'large');
	if (!$themeconf['rootweaponsdir']) {
		$themeconf['rootweaponsdir'] = catfile($themeconf['rootimagesdir'], 'weapons');
	}
	if (!$themeconf['rootweaponsurl']) {
		$themeconf['rootweaponsurl'] = $themeconf['rootimagesurl'] . 'weapons/';
	}
	$themeconf['smallweaponsurl'] = catfile($themeconf['rootweaponsurl'], $ps->conf['main']['gametype'], $ps->conf['main']['modtype']);
	$themeconf['largeweaponsurl'] = catfile($themeconf['rootweaponsurl'], $ps->conf['main']['gametype'], $ps->conf['main']['modtype'], 'large');
	if (!$themeconf['rootrolesdir']) {
		$themeconf['rootrolesdir'] = catfile($themeconf['rootimagesdir'], 'roles');
	}
	if (!$themeconf['rootrolesurl']) {
		$themeconf['rootrolesurl'] = $themeconf['rootimagesurl'] . 'roles/';
	}
	if (!$themeconf['rootflagsdir']) {
		$themeconf['rootflagsdir'] = catfile($themeconf['rootimagesdir'], 'flags');
	}
	if (!$themeconf['rootflagsurl']) {
		$themeconf['rootflagsurl'] = $themeconf['rootimagesurl'] . 'flags/';
	}
	if (!$themeconf['rooticonsdir']) {
		$themeconf['rooticonsdir'] = catfile($themeconf['rootimagesdir'], 'icons');
	}
	if (!$themeconf['rooticonsurl']) {
		$themeconf['rooticonsurl'] = $themeconf['rootimagesurl'] . 'icons/';
	}

	// if we're using a user defined theme and it does not exist default back to original
	if ($newtheme and !@is_dir(catfile($themeconf['themedir'],$newtheme))) {
		$theme = $ps->conf['main']['theme'];
		$newtheme = null;
		unset($ps_user_opts['theme']);
		session_save_user_opts($ps_user_opts);
	}

	define('SMARTY_TEMPLATE_DIR', $themeconf['themedir']);
	define('SMARTY_COMPILE_DIR', $themeconf['compiledir']);
	define('THEME_URL', catfile($themeconf['themeurl'], $theme));
	define('THEME_DIR', catfile(SMARTY_TEMPLATE_DIR, $theme));
	$themedir = THEME_DIR;

	// verify the selected theme directory exists and is readable
	if (!@is_dir($themedir)) {
		$err = "<b>Invalid theme specified '$theme'!</b>";
		$err .= "<br>Directory does not exist or is not readable: <b>$themedir</b><br>";

		$dir = $themedir;
		$lastdir = '';
		while ($dir != $lastdir) {
			if (@is_dir($dir)) {
				$err .= "<b>DEBUG:</b> \"$dir\" is a valid directory";
				if (!@is_readable($dir)) {
					$err .= " (but is not readable; check permissions)";
//					break;
				}
				$err .= "<br>\n";
				break;	// no sense in continuing to show 'valid' directories from this point ...
			} else {
				$err .= "<b>DEBUG:</b> \"$dir\" is <b>NOT</b> a valid directory<br>";
			}
			$lastdir = $dir;
			$dir = dirname($dir);      
		}
		die($err);
	}

	// verify the compiled theme directory exists and is writable. try to create it if possible.
	if (!@is_dir(SMARTY_COMPILE_DIR)) {
		if (!@mkdir(SMARTY_COMPILE_DIR, 0700)) {
			$err = "<b>Invalid compile directory specified!</b>";
			$err .= "<br>Directory does not exist or is not readable: <b>" . SMARTY_COMPILE_DIR . "</b><br>";

			$dir = SMARTY_COMPILE_DIR;
			$lastdir = '';
			while ($dir != $lastdir) {
				if (@is_dir($dir)) {
					$err .= "<b>DEBUG:</b> \"$dir\" is a valid directory";
					if (!@is_readable($dir)) {
						$err .= " (but is not readable; check permissions)";
#						break;
					}
					$err .= "<br>\n";
					break;
				} else {
					$err .= "<b>DEBUG:</b> \"$dir\" is <b>NOT</b> a valid directory<br>";
				}
				$lastdir = $dir;
				$dir = dirname($dir);      
			}
			die($err);
		}
	} elseif (!@is_writable(SMARTY_COMPILE_DIR)) {
		$err = "<b>Invalid compile directory specified!</b>";
		$err .= "<br>Directory is not writable by the webserver: " . SMARTY_COMPILE_DIR . "<br>Themes will not function.";
		die($err);
	}

	if ($ps->conf['theme']['force_compile']) {
		define("THEME_DEV", true);
	}

	// initialize Smarty
	$smarty = new Theme($theme);

	// If a user selected a new language from a pulldown box on any page, process it here.
//	$newtheme was assigned in the THEME routines above
	if ($newtheme) {
		$ps_user_opts['theme'] = $newtheme;
		session_save_user_opts($ps_user_opts);
	}

	$newlang = isset($_REQUEST['newlang']) ? $_REQUEST['newlang'] : '';
	if (!empty($newlang) and $smarty->is_locale($newlang)) {
		$ps_user_opts['lang'] = $newlang;
		session_save_user_opts($ps_user_opts);
		$smarty->current_locale = $newlang;
	}

	$newcss = isset($_REQUEST['newcss']) ? $_REQUEST['newcss'] : '';
	if (!empty($newcss) and $smarty->is_css($newcss)) {
		$ps_user_opts['stylesheet'] = $newcss;
		session_save_user_opts($ps_user_opts);
		$smarty->current_css = $newcss;
	}

	$smarty->load_lang('global');
	if (!defined("PSYCHONUKE")){
		$smarty->load_lang(basename($PHP_SELF,'.php'));			// loads the language file for the current page
	} else {
		$smarty->load_lang(basename($ps3file.'.php','.php'));		// $ps3file is defined in the psychonuke module
	}
	$ps_lang =& $smarty;							// provide shortcut (lang->trans('...'))

	// setup some defaults for theme variables.
	$data['conf'] =& $ps->conf['main'];
	$data['user_opts'] = $ps_user_opts;
	$data['themeconf'] = $ps->conf['theme'];
	$data['themeurl'] = $ps->conf['theme']['themeurl'] . "$theme/";
	$data['imagesurl'] = $data['themeurl'] . 'images/';
	$data['imagesdir'] = catfile(THEME_DIR, 'images');
	$data['use_roles'] = $ps->use_roles;
	$data['ACL_NONE'] = ACL_NONE;
	$data['ACL_DENIED'] = ACL_DENIED;
	$data['ACL_USER'] = ACL_USER;
	$data['ACL_CLANADMIN'] = ACL_CLANADMIN;
	$data['ACL_ADMIN'] = ACL_ADMIN;

}  // if NOTHEME is not defined

// we report this after the theme so the error can be displayed 
if (!$handlerok) {
	$err = "<div style='text-align: left; padding: 10px'>$handlererr</div>";
	abort('nomatch', $ps_lang->trans("Invalid Handler"), 
		sprintf($ps_lang->trans("The user handler configured '%s' is invalid or does not exist."), $userhandler) . $err
	);
}

?>
