<?php 

if (defined("CLASS_THEME_PHP")) return 1;
define("CLASS_THEME_PHP", 1);

if (!defined("SMARTY_DIR")) define("SMARTY_DIR", dirname(__FILE__) . "/smarty/");
require(SMARTY_DIR . 'Smarty.class.php');


class Theme extends Smarty {

function Theme($theme, $locale="") {
	global $ps, $ps_user, $ps_user_opts;

	$this->themebuffer = "";
	$this->theme = $theme;
	$this->default_locale = isset($ps->conf['theme']['default_locale']) ? $ps->conf['theme']['default_locale'] : 'english';
	$this->current_locale = $this->default_locale;
	$this->lang_table = array();
	$this->pending_table = array();

	$this->Smarty();
	if (defined("THEME_DEV")) {
		$this->force_compile = THEME_DEV;		// DEBUG ONLY
	}
	$this->error_reporting 	= E_ALL & ~E_NOTICE;
	$this->use_sub_dirs 	= FALSE;
	$this->compile_id	= $theme;
	$this->caching 		= FALSE;
	$this->template_dir 	= catfile(SMARTY_TEMPLATE_DIR, $theme, '');
	$this->compile_dir 	= catfile(SMARTY_COMPILE_DIR, '');
	$this->lang_dir 	= catfile($this->template_dir, 'languages', '');

/*
	$this->security		= true;
	$this->secure_dir[]	= $this->template_dir;
	$this->security_settings['IF_FUNCS'][]  = 'user_logged_on';
	$this->security_settings['IF_FUNCS'][]  = 'user_is_admin';
	$this->security_settings['IF_FUNCS'][]  = 'user_is_clanadmin';

	$this->security_settings['MODIFIER_FUNCS'][]  = 'urlencode';
	$this->security_settings['MODIFIER_FUNCS'][]  = 'long2ip';
	$this->security_settings['MODIFIER_FUNCS'][]  = 'ip2long';
	$this->security_settings['MODIFIER_FUNCS'][]  = 'ucfirst';
	$this->security_settings['MODIFIER_FUNCS'][]  = 'abs';
/**/

	// This output filter helps session support be more accurate for users w/o cookies
	if (session_method() != 'cookie') $this->register_outputfilter('ps_output_filter');

	$this->theme_list = $this->get_theme_list();
	$this->lang_list = $this->get_lang_list();
	$this->css_list = $this->get_css_list();
	$this->locale_map = $this->get_locale_map();

	// If a locale was specified (forced) use it, but only if it's actually valid.
	if (!empty($locale) and $this->is_locale($locale)) {
		$this->current_locale = $locale;
	} else {
		if (!empty($_GET['locale']) and $this->is_locale($_GET['locale'])) {	// search for GET locale var
			$this->current_locale = $_GET['locale'];
		} elseif (!empty($ps_user_opts['lang']) and $this->is_locale($ps_user_opts['lang'])) {
			$this->current_locale = $ps_user_opts['lang'];
#		} elseif (($browserlang = $this->getBrowserLanguage()) !== FALSE) {	// search browser for language
#			$this->current_locale = $browserlang;
		} elseif ($this->is_locale($this->default_locale)) {			// use default locale if it's valid
			$this->current_locale = $this->default_locale;
		} else {								// use first locale available
			reset($this->locale_map);
			$this->current_locale = current($this->locale_map);
		}
	}

	$this->register_prefilter("smarty_prefilter_mlang");

/*
	if (defined("PSYCHONUKE")) {
		$this->register_prefilter("smarty_prefilter_psychonuke");
	}
*/

	// Define some common globals for all templates
	$this->assign(array(
		'conf'			=> $ps->conf,
		'info'			=> $ps->conf['info'],
		'theme'			=> $theme,
		'user'			=> $ps_user,
		'languagelist'		=> $this->lang_list,
		'stylelist'		=> $this->css_list,
		'themelist'		=> $this->theme_list,
		'current_locale'	=> $this->current_locale,
		'GET'			=> $_GET,
		'POST'			=> $_POST,
		'REQUEST'		=> $_REQUEST,
		'ACL_DENIED'		=> ACL_DENIED,
		'ACL_USER'		=> ACL_USER,
		'ACL_CLANADMIN'		=> ACL_CLANADMIN,
		'ACL_ADMIN'		=> ACL_ADMIN,
		'THEME_DEV'		=> defined('THEME_DEV') ? THEME_DEV : 0,
		'DB_DEBUG'		=> defined('DB_DEBUG') ? DB_DEBUG : 0,
		'PHP_SELF'		=> $_SERVER['PHP_SELF'],
	) + $_SERVER);

}  // end of constructor

// returns TRUE if the locale specified is within our current map
function is_locale($locale) {
	return array_key_exists($locale, $this->locale_map);
}

function is_css($css) {
	return in_array($css, $this->css_list);
}

function load_lang($filename, $delay=1) {
	if ($delay) {
		$this->pending_table[$this->current_locale][] = $filename;
		return 1;
	}

	if (strpos($filename, '.lng') === FALSE) $filename .= ".lng";

	$ps_language = $this->locale_map[ $this->current_locale ];
	$path = catfile($this->lang_dir, $ps_language, $filename);
//	print "load_lang: ($ps_language) $path<bR>";

	if (file_exists($path)) {
		$lines = file($path);
		$lastkey = '';
		foreach ($lines as $line) {
			$line = trim($line);						// strip off leading and trailing whitespace
			if ($lastkey == '') {						// only if we are NOT in a multi-line block ...
				if (substr($line,0,2) == '//') continue;		// ignore comments
				if ($line == '') continue;				// ignore blank lines
			}

			$token = substr($line,0,2);					// get current token (==)
			$value = substr($line,2);					// get remainder of the line

			if ($lastkey != '') {						// ALREADY INSIDE MULTI LINE KEY
				if ($token != '==') {					// The key has hot ended yet
					$this->lang_table[ $lastkey ] .= $line . "\n";
				} else { 						// the key ENDED
					$lastkey = trim($value);			// might be empty
				}
			} else {							// SINGLE LINE KEY
				if ($token != '==') {
					list($key,$def) = explode('=', $line, 2);
					$key = trim($key);
					$def = trim($def);
					$this->lang_table[$key] = $def != '' ? $def : $key;
				} else {
					$lastkey = trim($value) != '' ? trim($value) : $key;
				}
			}
		}
//      print "<pre>"; print_r($this->lang_table); print "</pre>";			// DEBUG
		return true;
	} else {
		// don't die, instead, nothing will be translated...
//		die ("Could not load language file: $filename<br>");
	}
	return false;
}

function get_translation($key) {
	// load language files that are waiting to be loaded first ...
	if (isset($this->pending_table[ $this->current_locale ])) {
		foreach ($this->pending_table[ $this->current_locale ] as $file) {
			$this->load_lang($file, 0);			// 0 = do not delay the loading
		}
		unset($this->pending_table[ $this->current_locale ]);
	}
	$result = (array_key_exists($key, $this->lang_table)) ? $this->lang_table[$key] : $key;
	return $result;
}

// shortcut for get_translation. So you can use $ps_lang->trans('...') in the PHP files
function trans($key) {
	return $this->get_translation($key);
}

// returns a list of all languages physically found on the HD for the theme
function get_lang_list($path=NULL) {
	if ($path===NULL) $path = $this->lang_dir;
	$ps_langs = array();
	$dh = @opendir($path);
	if ($dh) {
		while (($file = readdir($dh)) !== FALSE) {
			if (!@is_dir(catfile($path,$file)) or $file{0} == '.') continue;
			$ps_langs[] = $file;
		}
	}
	sort($ps_langs);
	return $ps_langs;
}

function get_theme_list($path=NULL) {
	if ($path===NULL) $path = dirname($this->template_dir);
	$themes = array();
	$dh = @opendir($path);
	if ($dh) {
		while (($file = readdir($dh)) !== FALSE) {
			if (!@is_dir(catfile($path,$file)) or $file{0} == '.') continue;
			if (!@file_exists(catfile($path,$file,'theme.cfg'))) continue;
			$themes[] = $file;
		}
	}
	sort($themes);
	return $themes;
}

// returns a list of all stylesheets physically found on the HD for the theme
function get_css_list($path=NULL, $suffix='css') {
	if ($path===NULL) $path = $this->template_dir;
	$css = array();
	$dh = @opendir($path);
	if ($dh) {
		while (($file = readdir($dh)) !== FALSE) {
			if (!@is_file(catfile($path,$file)) or $file{0} == '.') continue;
			if (substr($file, -strlen($suffix)) != $suffix) continue;
			$css[] = $file;
		}
	}
	sort($css);
	return $css;
}

// returns the locale mappings set in the locales.php, and merges known languages found in the directory.
function get_locale_map() {
	include(dirname(__FILE__) . "/locales.php");		// include the external locale map
	if (!is_array($LOCALEMAP)) $LOCALEMAP = array();
	if (!$this->lang_list) $this->lang_list = $this->get_lang_list();
	foreach ($this->lang_list as $ps_lang) {			// merge languages found in the directory to the map array
		$LOCALEMAP[$ps_lang] = $ps_lang;
	}
	return $LOCALEMAP;
}

// short-cut method to return a mod specific filename for the theme. 
// returns either: block_file_GAME_MOD.html, block_file.html, or ''
function get_block_file($prefix) {
	global $ps;
	$f = sprintf($prefix."_%s_%s.html", strtolower($ps->conf['main']['gametype']), strtolower($ps->conf['main']['modtype']));
#	print "1 '$f'<br>";
	if (!file_exists(catfile(THEME_DIR,$f))) {	
		$f = $prefix . '.html';
#		print "2 '$f'<br>";
		if (!file_exists(catfile(THEME_DIR,$f))) $f = '';
	}
	return $f;
}

// returns a valid language to use based on the users browser settings
function getBrowserLanguage() {
	$ps_langs = explode(';', $_SERVER["HTTP_ACCEPT_LANGUAGE"]);
	foreach ($ps_langs as $value_and_quality) {
		$values = explode(',', $value_and_quality);
		foreach ($values as $value) {
			if ($this->is_locale($value)) return $value;
		}
	}
	return FALSE;
}

function fetch($_smarty_tpl_file, $_smarty_cache_id = null, $_smarty_compile_id = null, $_smarty_display = false) {
	// We need to set the cache id and the compile id so a new script will be compiled for each language. 
#	if (!defined("PSYCHONUKE")) {
		$_smarty_compile_id = $this->theme . '-' . $this->current_locale . '-' . $_smarty_compile_id; 
#	} else {
#		$_smarty_compile_id = 'pnuke-' . $this->theme . '-' . $this->current_locale . '-' . $_smarty_compile_id;  
#	}
	$_smarty_cache_id = $_smarty_compile_id;

	// Now call parent method
	return parent::fetch( $_smarty_tpl_file, $_smarty_cache_id, $_smarty_compile_id, $_smarty_display );
}

// Parses the template filename and appends it to the current buffer for output
function parse($filename) {
	if (strpos($filename, '.html') === FALSE) $filename .= ".html";
	$this->themebuffer .= $this->fetch($filename);
}

// outputs the page to the user. Adds the timer, if $showtimer is true
function showpage($showtimer=1) {
	global $TIMER;
	if ($TIMER and $showtimer) {
		return str_replace('<!--PAGELOADTIME-->', $TIMER->timediff(), $this->themebuffer);
	}
	return $this->themebuffer;
}

} // end of class "Theme"


// ---------------------------------------------------------------------------------------------------------------
// the functions below are not class functions. They are support functions that are called from within the parent
// Smarty class to add some extra features to the theme.

function smarty_prefilter_mlang($tpl_source, &$smarty) {
	// Now replace the matched language strings with the entry in the file
	return preg_replace_callback('/<#(.+?)#>/', "_compile_lang", $tpl_source);
}

function _compile_lang($key) {
	return $GLOBALS['smarty']->get_translation($key[1]);
}

/**
 * ps_output_filter
 * Called by smarty's output filter routines when loading a compiled theme.
 * This will add the current session ID to all relative links in the output.
 * But only if there was no session ID specified in the $_COOKIE array already
 *
 */
function ps_output_filter($output, &$smarty) {
	// cookie was used for SID, so we don't need to do anything
	if (session_method() == 'cookie' or session_is_bot()) continue;
	$sidname = session_sidname(); 
	$sid = session_sid();
	$search = array();
	$replace = array();

	$tags = array(
		'a' => 'href', 
		'input' => 'src', 
		'form' => 'action', 
		'frame' => 'src', 
		'area' => 'href',
		'iframe' => 'src'
	);

	foreach ($tags as $tag => $attr) {
		if (!preg_match_all("'<" . $tag . "[^>]+>'si", $output, $matchlist, PREG_PATTERN_ORDER)) continue;
		foreach ($matchlist as $matches) {
			foreach ($matches as $match) {
				if (preg_match("/" . $attr . "\s*=\s*(([\"'])(.+?)\\2)/i", $match, $innermatch)) {
#					$match = <a href="player.php?id=7350" class="example">
#					$innermatch = Array(
#						[0] => href="player.php?id=7350"
#						[1] => "player.php?id=7350"
#						[2] => "
#						[3] => player.php?id=7350
#					)

					$url = $innermatch[3];
					$quote = $innermatch[2];
					$oldattr = $innermatch[0];
					$newattr = "$attr=$quote$url";

					$query = parse_url($url);
					if (is_array($query)) {
						parse_str($query['query'], $urlargs);
						if (array_key_exists($sidname, $urlargs)) continue; 	// do not duplicate SID
					}

					if (strpos($url, '://') !== FALSE) continue;			// ignore absolute URLS
					if (strpos($url, 'javascript:') !== FALSE) continue;		// ignore javascript links

					$newattr .= (strpos($url, '?') === FALSE) ? '?' : '&';	// append proper query separator
					$newattr .= "$sidname=$sid$quote";

					$search[] = $oldattr;
					$replace[] = $newattr;
				}
			}
		}
	}
	if (count($search)) $output = str_replace($search, $replace, $output);
	return $output;
}


?>
