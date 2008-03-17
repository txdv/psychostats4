<?php 
/***
	PsychoTheme class
	$Id$

	Theme class that handles all HTML or text output (images are done elsewhere).
	Smarty is the underlining code to produce the output. The PsychoTheme class
	adds some new functionality like multiple language support. Also, parent/child
	themes are supported. A child theme can be based on a parent and only have the
	changed files within the child theme directory.

	A theme is made up of template files. Almost anything can be done within a template.
	For security reasons PHP tags are not allowed inside a template.

	The layout of most themes generally follow this hierarchy:
		overall_header
		page_header
		page_content
		page_footer
		overall_footer
	Plugins can hook into the different pieces of themes to apply changes that are transparent
	no matter what theme is being used.

***/

if (defined("CLASS_THEME_PHP")) return 1;
define("CLASS_THEME_PHP", 1);

if (!defined("SMARTY_DIR")) define("SMARTY_DIR", dirname(__FILE__) . "/smarty/");
require_once(SMARTY_DIR . 'Smarty.class.php');

class PsychoTheme extends Smarty {
var $buffer 		= '';
var $theme_url		= null;
var $theme 		= '';
var $language 		= 'en_US';
var $template_dir	= null;
var $css_links		= array();
var $js_sources		= array();
var $meta_tags		= array();
var $loaded_themes	= array();
var $parent_themes	= array();
var $fetch_compile	= true;
var $page_title		= '';

//function __construct($cms, $args = array()) { $this->PsychoTheme($cms, $args); }
function PsychoTheme(&$cms, $args = array()) {
	$this->Smarty();
	$this->cms =& $cms;

	// if args is not an array assume its the name of a theme to use
	if (!is_array($args)) $args = array( 'theme' => $args ? $args : 'default' );
	$args += array(
//		'theme'		=> '',
		'in_db' 	=> true,
		'language'	=> 'en_US',
		'fetch_compile'	=> true,
		'template_dir'	=> null,
		'theme_url'	=> null,
		'compile_dir'	=> '.',
		'compile_id'	=> ''
	);
	$this->template_dir($args['template_dir']);
	$this->language($args['language']);
	$this->theme_url = $args['theme_url'];
	$this->fetch_compile = ($args['fetch_compile']);
//	if ($args['theme']) $this->theme($args['theme'], $args['in_db']);

	// Force themes to be compiled if debugging is enabled
	if (defined("PS_THEME_DEV") and PS_THEME_DEV == true) {
		$this->force_compile = PS_THEME_DEV;
	}

	// initialize some Smarty variables
	$this->error_reporting 	= E_ALL & ~E_NOTICE;
	$this->compile_id	= $args['compile_id'];
	$this->use_sub_dirs 	= false;
	$this->caching 		= false;
//	$this->cache_dir 	= $args['compile_dir'];
	$this->compile_dir 	= $args['compile_dir'];
//	$this->default_template_handler_func = array(&$this, 'no_template_found');

	// This output filter helps session support be more accurate for users w/o cookies
	if ($this->cms->session && $this->cms->session->sid_method() != 'cookie' and empty($this->cms->cookie)) {
		$this->register_outputfilter(array(&$this, 'output_filter'));
	}

	// pre-filter to automatically translate language strings
	$this->register_prefilter(array(&$this, "prefilter_language"));

	// default the theme_url to our local themes directory
	if ($this->theme_url === null) {
		// if $base is '/' then don't use it, otherwise the theme_url will start with "//"
		// and that will cause odd behavior as the client tries to do a network lookup for it
		$base = dirname($_SERVER['SCRIPT_NAME']);
		$this->theme_url = ($base != '/' ? $base : '') . '/themes';
	}

	// Define some common globals for all templates
	$this->assign_by_ref('title', $this->page_title);
	$this->assign_by_ref('theme_name', $this->theme);
	$this->assign_by_ref('language', $this->language);
	$this->assign(array(
		'PHP_SELF'		=> ps_escape_html($PHP_SELF),
		'SELF'			=> ps_escape_html($PHP_SELF),
	));

	// allow theme access to a couple methods of our objects
	$this->register_object('theme', $this, array( 'css_links', 'js_sources', 'meta_tags', 'url' ), false);
	$this->register_object('db', $this->cms->db, array( 'totalqueries' ), false);
}  // end of constructor

// assigns a list of request variable names to the theme by referernce so the theme can use them 
// and the script can continue to change them before the final output.
// if $globalize is true the variables will also be injected into the global name space as references.
function assign_request_vars($list, $globalize = false) {
	if (is_array($list)) {
		foreach ($list as $var) {
			$this->assign_by_ref($var, $this->cms->input[$var]);
			if ($globalize) {
				$GLOBALS[$var] = &$this->cms->input[$var];
			}
		}
	}
}

// allows pages to insert extra CSS links within the overall_header.
// $url is a relative URL to the css file (within the current theme_url).
// $media is the media type to use (optional).
function add_css($url, $media='screen,projection,print') {
	$this->css_links[$url] = array( 'url' => $url, 'media' => $media );
}

function add_js($url) {
	$this->js_sources[$url] = array( 'url' => $url );
}

function add_meta($values) {
	if (is_array($values)) {
		$this->meta_tags[] = $values;
	}
}

function add_refresh($url, $seconds = 3) {
	$this->add_meta(array( 'http-equiv' => 'Refresh', 'content' => $seconds . ';URL=' . $url ));
}

// SMARTY: template routine to print out the META tags in the overall_header
function meta_tags() {
	if (!is_array($this->meta_tags)) return '';
	$out = '';
	foreach ($this->meta_tags as $meta) {
		$out .= "\t<meta ";
		foreach ($meta as $key => $val) {
			$out .= "$key='" . ps_escape_html($val) . "' ";
		}
		$out .= "/>\n";
	}
	return $out;
}

// SMARTY: template routine to print out the CSS links in the overall_header
function css_links($theme = null) {
	if (!is_array($this->css_links)) return '';
	if (empty($theme)) $theme = $this->theme();
	$out = '';
	foreach ($this->css_links as $css) {
		$out .= sprintf("\t<link rel='stylesheet' type='text/css' media='%s' href='%s' />\n", 
			$css['media'], $this->url($theme) . '/' . $css['url']
		);
	}
	return $out;
}

// SMARTY: template routine to print out the JS sources in the overall_header
function js_sources($theme = null) {
	if (!is_array($this->js_sources)) return '';
	if (empty($theme)) $theme = $this->theme();
	$out = '';
	foreach ($this->js_sources as $js) {
		$out .= sprintf("\t<script src='%s%s' type='text/javascript'></script>\n", 
			substr($js['url'], 0, 4) == 'http' ? '' : $this->url($theme) . '/',
			$js['url']
		);
	}
	return $out;
}

// returns the absolute URL for the current theme. mainly used within smarty templates
function url($theme = null) {
	if (empty($theme)) $theme = $this->theme();
	return $this->theme_url ? $this->theme_url . '/' . $theme : $theme;
}

// this is called by Smarty if a template file was not found.
// this allows us to output some actual useful information so the user can attempt to fix the error.
// if nothing is returned then smarty will simplay display its default warning message.
// *** NOT USED ***
/*
function no_template_found($resource_type, $resource_name, &$template_source, &$template_timestamp, &$smarty) {
	if ($resource_type == 'file') {
		if (!is_readable($resource_name)) {
			// create the template file, return contents.
			$template_source = "Template '$resource_name' not found! Do something about it!<br/><br/>\n\n";
			$template_timestamp = time();
#			$smarty->_write_file($resource_name,$template_source);
			return true;
		}
	} else {
		// not a file
		return false;
	}
//	return "Template '$tpl' not found! Do something about it!";
}
*/

// Add a new directory to search for templates in.
// New directories are added to the FRONT of the array. 
// So that each new directory added will be search first.
function template_dir($dir = null) {
	if ($dir === null) {
		return (array)$this->template_dir[0];
	} elseif (empty($this->template_dir)) {
		$this->template_dir = $dir;
	} elseif (is_array($this->template_dir)) {
		if (!in_array($dir, $this->template_dir)) {
			array_unshift($this->template_dir, $dir);
		}
	} else { // template_dir is a string 
		$this->template_dir = array_unique(array($dir, $this->template_dir));
	}
}

// remove a template directory from the search list.
// if only 1 directory is defined it can not be removed.
function remove_template_dir($dir) {
	if (is_array($this->template_dir)) {
		$newlist = array();
		for ($i=0; $i < count($this->template_dir); $i++) {
			if ($this->template_dir[$i] != $dir) {
				$newlist[] = $this->template_dir[$i];
			}
		}
		if (!count($newlist)) {			// convert it back to a string
			$this->template_dir = $this->template_dir[0];
		} elseif (count($newlist) == 1) {
			$this->template_dir = $newlist[0];
		} else {
			$this->template_dir = $newlist;
		}
	}
}

// get/set the current theme
function theme($new = null, $in_db = true) {
	if (empty($new)) {
		return $this->theme;
	} elseif ($this->is_theme($new)) {
		$loaded = false;
		// load the theme from the database
		if (!$this->loaded_themes[$new] and $in_db) {
			$loaded = true;
			$t = $this->cms->db->fetch_row(1, sprintf("SELECT * FROM %s WHERE name=%s and enabled <> 0", 
				$this->cms->db->table('themes'),
				$this->cms->db->escape($new, true)
			));
			if (!$t) {
				trigger_error("<b>PsychoTheme:</b> Attempt to set \$theme to an invalid name '<b>$new</b>'. " . 
					"Theme not installed or enabled, please check your theme configuration.", 
					E_USER_WARNING
				);
				return $this->theme;
			}
			$this->loaded_themes[$new] = $t;
			if ($t['parent'] and !$this->loaded_themes[$t['parent']]) { 
				// load the parent theme ...
				// the parent theme doesn't have to be enabled
				$p = $this->cms->db->fetch_row(1, sprintf("SELECT * FROM %s WHERE name=%s", 
					$this->cms->db->table('themes'),
					$this->cms->db->escape($t['parent'], true)
				));
				if ($p) {
					$this->loaded_themes[$t['parent']] = $p;
					$this->parent_themes[$new] = $t['parent'];
//					$this->child_themes[$t['parent']] = $new;
				}
			}	
		}

		// if we're not loading a theme from the DB then fudge a loaded record ...
		if (!$this->loaded_themes[$new] and !$in_db) {
			$loaded = true;
			$this->loaded_themes[$new] = array(
				'name' => $new,
				'parent' => null,
				'enabled' => 1, 
				'title' => $new,
				'description' => ''
			);
		}

		// load the language for the theme
		if ($loaded) {
			$class = "PsychoLanguage_" . $new . "_" . $this->language();
			$file = catfile($this->language_dir($new), $this->language() . '.php');
			// if the language file doesn't exist in the current theme and there is a parent, check it instead.
			if (!file_exists($file) and isset($this->parent_themes[$new])) {
				$class = "PsychoLanguage_" . $this->parent_themes[$new] . "_" . $this->language();
				$file = catfile($this->language_dir($this->parent_themes[$new]), $this->language() . '.php');
			}
			ob_start();
			$ok = (include_once $file);
			$err = ob_get_clean();
			if ($ok and !$err) {
				$this->lang = new $class();
			} else {
				if (defined("PS_THEME_DEV") and PS_THEME_DEV == true) {
					trigger_error("Error loading language class $class. <strong>Using default instead.</strong> See the errors and/or warnings below for more information", E_USER_WARNING);
					print $err;
				}
				$this->lang = new PsychoLanguage();
			}
		}

		$old = $this->theme;
		$this->theme = $new;
		return $old;
	} else {
		trigger_error("<b>PsychoTheme:</b> Attempt to set \$theme to an invalid name '<b>$new</b>'. " . 
			"Theme not found in \$template_dir(<b>" . implode(', ', (array)$this->template_dir) . "</b>). " .
			"Is your 'template_dir' set to the proper directory? Edit your config in the ACP. ",
			E_USER_WARNING
		);
		return $this->theme;
	}
}

function is_child($theme = null) {
	if (!isset($theme)) $theme = $this->theme();
	return isset($this->parent_themes[$theme]) ? $this->parent_themes[$theme] : false;
}

// returns the full path to the theme(=true) if the theme name specified is a valid directory within our template_dir
function is_theme($theme) {
	if (empty($theme)) return false;
	foreach ((array)$this->template_dir as $path) {
		if (is_dir($path . DIRECTORY_SEPARATOR . $theme)) {
			return $path . DIRECTORY_SEPARATOR . $theme;
		}
	}
	return false;
}

// get/set the current language
function language($new = null) {
	if ($new === null) {
		return $this->language;
	} else { //if ($this->is_language($new)) {
		$old = $this->language;
		$this->language = $new;
		return $old;
	}
}

// returns the path to the language dir of the theme
function language_dir($theme = null) {
	if (empty($theme)) $theme = $this->theme();
	return catfile($this->template_dir, $theme, 'language');
}

// Translate a string phrase, or return the original string if no translation is available.
function trans($str, $args = array()) {
	return $this->lang->gettext($str, $args);
}

// Returns true if the specified language is actually available in the current theme.
// This does not check languages from parent themes if the current theme is a child of another.
function is_language($language, $force = false) {
	static $list = array();
	if (!$this->loaded_themes) {
		return false;
	}
	foreach (array_keys($this->loaded_themes) as $theme) {
		if (!isset($list[$theme])) {
			$list[$theme] = $this->get_language_list($theme);
		}
		if (in_array($language, $list[$theme])) {
			return true;
		}
	}
	return false;
}

// returns a list of all languages found in the language directory of the theme.
function get_language_list($theme = null) {
	$theme_list = (array)($theme ? $theme : array_keys($this->loaded_themes));
	$langs = array();
	foreach ($theme_list as $t) {
		$path = $this->language_dir($t);
		$dh = @opendir($path);
		if ($dh) {
			while (($file = readdir($dh)) !== false) {
				if (!is_file(catfile($path,$file)) or substr($file,0,1) == '.') continue;
				if (substr($file, -3) != 'php') continue;
				$langs[] = basename($file, '.php');
			}
		}
	}
	sort($langs);
	return array_unique($langs);
}

// NOT USED; AND WILL NOT WORK; NEEDS TO BE RECODED.
function get_theme_list() {
	return array();
}

// override Smarty function so {include} continues to work with our directories
/**/
function _smarty_include($params) {
	$params['smarty_include_tpl_file'] = catfile($this->theme, $params['smarty_include_tpl_file']); 		///
	if ($this->debugging) {
		$_params = array();
		require_once(SMARTY_CORE_DIR . 'core.get_microtime.php');
		$debug_start_time = smarty_core_get_microtime($_params, $this);
		$this->_smarty_debug_info[] = array('type'      => 'template',
						'filename'  => $params['smarty_include_tpl_file'],
						'depth'     => ++$this->_inclusion_depth);
		$included_tpls_idx = count($this->_smarty_debug_info) - 1;
	}

	$this->_tpl_vars = array_merge($this->_tpl_vars, $params['smarty_include_vars']);

	// config vars are treated as local, so push a copy of the
	// current ones onto the front of the stack
	array_unshift($this->_config, $this->_config[0]);

	$_smarty_compile_path = $this->_get_compile_path($params['smarty_include_tpl_file']);

	if ($this->_is_compiled($params['smarty_include_tpl_file'], $_smarty_compile_path)
		|| $this->_compile_resource($params['smarty_include_tpl_file'], $_smarty_compile_path))
	{
		if ($this->fetch_compile) {										///
			include($_smarty_compile_path);
		} else {												///
			ob_start();
			$this->_eval('?>' . $this->_last_compiled);
			$_contents = ob_get_contents();
			ob_end_clean();
			print $_contents;
		}
	}

	// pop the local vars off the front of the stack
	array_shift($this->_config);

	$this->_inclusion_depth--;

	if ($this->debugging) {
		// capture time for debugging info
		$_params = array();
		require_once(SMARTY_CORE_DIR . 'core.get_microtime.php');
		$this->_smarty_debug_info[$included_tpls_idx]['exec_time'] = smarty_core_get_microtime($_params, $this) - $debug_start_time;
	}

	if ($this->caching) {
		$this->_cache_info['template'][$params['smarty_include_tpl_file']] = true;
	}
}

function _is_compiled($resource_name, $compile_path) {
	if ($this->fetch_compile) {
		return parent::_is_compiled($resource_name, $compile_path);
	} 
	return false;
}

function _compile_resource($resource_name, $compile_path) {
	$_params = array('resource_name' => $resource_name);
	if (!$this->_fetch_resource_info($_params)) {
		return false;
	}

	$_source_content = $_params['source_content'];
	$_cache_include = substr($compile_path, 0, -4).'.inc';

	if ($this->_compile_source($resource_name, $_source_content, $_compiled_content, $_cache_include)) {
		// if a _cache_serial was set, we also have to write an include-file:
		if ($this->_cache_include_info) {
			require_once(SMARTY_CORE_DIR . 'core.write_compiled_include.php');
			smarty_core_write_compiled_include(array_merge($this->_cache_include_info, array('compiled_content'=>$_compiled_content, 'resource_name'=>$resource_name)),  $this);
		}

		if ($this->fetch_compile) {
			$_params = array('compile_path'=>$compile_path, 'compiled_content' => $_compiled_content);
			require_once(SMARTY_CORE_DIR . 'core.write_compiled_resource.php');
			smarty_core_write_compiled_resource($_params, $this);
			$this->_last_compiled = '';
		} else {
			$this->_last_compiled = $_compiled_content;
		}

		return true;
	} else {
		return false;
	}
}

// returns the relative template filename if the template file is found within a loaded theme 
function template_found($tpl_file, $update_theme = true, $get_source = false) {
	if (strpos($tpl_file, '.html') === false and strpos($tpl_file, '.xml') === false) $tpl_file .= ".html";
	$params = array('quiet' => true, 'get_source' => $get_source);
	foreach ($this->loaded_themes as $name => $theme) {
#		print "checking $name\n";
		$params['resource_name'] = $name . '/' . $tpl_file;
		if ($this->_fetch_resource_info($params)) {
#			print "template_found($tpl_file); name=$name\n"; print_r($params);
			if ($update_theme) $this->theme = $name;
			return $params;
		}
	}
#	print "no template_found($tpl_file)\n";
	return false;
}

function fetch($tpl_file, $cache_id = null, $compile_id = null, $display = false) {
#	print "fetch($tpl_file)\n";
	$res = $this->template_found($tpl_file);
	$compile_id = $this->language() . '-' . $this->compile_id; 
	if ($res) {
		$tpl_file = $res['resource_name'];
		$compile_id = $this->theme . '-' . $compile_id;
	}
	return parent::fetch($tpl_file, $cache_id, $compile_id, $display);
}

// fetch a template without saving to disk. This is not usually recommended due to performance issues.
function fetch_eval($tpl_file) {
	$_params = $this->template_found($tpl_file, true, true);
	if (!$_params) {
		return '';
	}

        $_source_content = $_params['source_content'];

	$_var_compiled = '';
	$this->_compile_source('eval-template', $_source_content, $_var_compiled);
	ob_start();
	$this->_eval('?>' . $_var_compiled);
	$_contents = ob_get_contents();
	ob_end_clean();
	return $_contents;
}

// Parses the template filename and appends it to the current buffer for output
// returns the output from the parsed template.
function parse($filename, $append_buffer = true) {
	if (strpos($filename, '.html') === false and strpos($filename, '.xml') === false) $filename .= ".html";
	$orig = $this->theme();
#	print "parse($filename) orig=$orig\n";
	$out = $this->fetch_compile ? $this->fetch($filename) : $this->fetch_eval($filename);
	$this->theme($orig);
	if ($append_buffer) $this->buffer .= $out;
	return $out;
}

// outputs the page to the user. Adds the timer, if $showtimer is true
function showpage($output = null, $showtimer = true) {
	global $TIMER;
	if ($output === null) $output = $this->buffer;
	if ($TIMER and $showtimer) {
		$output = str_replace('<!--PAGE_BENCHMARK-->', $TIMER->timediff(), $output);
	}
	print $output;
}

function prefilter_language($tpl_source, &$smarty) {
	// Now replace the matched language strings with the entry in the file
	return preg_replace_callback('/(?:<!--)?<#(.+?)#>(?:-->(.+?)<!---->)?/ms', array(&$this, "_compile_lang"), $tpl_source);
}

function _compile_lang($key) {
	if ($key[2]) {	// <!--<#KEYWORD#>-->english phrase here<!---->
		$text = $this->trans($key[1]);
		// if the translated text equals the key, then there is no translation 
		// and we should use the english phrase as-is.
		return $text == $key[1] ? $key[2] : $text;
	} else {	// <#english phrase here#>
		return $this->trans($key[1]);
	}
}

/**
 * output_filter
 * Called by smarty's output filter routines when loading a compiled theme.
 * This will add the current session ID to all relative links in the output.
 * But only if there was no session ID specified in the $_COOKIE array already
 *
 */
function output_filter($output, &$smarty) {
	// cookie was used for SID, so we don't need to do anything
	// or if the user client was detected as a bot the sid is not appended to urls
	if ($this->cms->session->sid_method() == 'cookie' or $this->cms->session->is_bot()) return $output;
	$sidname = $this->cms->session->sid_name(); 
	$sid = $this->cms->session->sid();
	$search = array();
	$replace = array();
	$amp = '&amp;';

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
				if (preg_match("/" . $attr . "\s*=\s*(([\"'])(.*?)\\2)/i", $match, $innermatch)) {
#					$match = <a href="player.php?id=7350" class="example">
#					$innermatch = Array(
#						[0] => href="player.php?id=7350"
#						[1] => "player.php?id=7350"
#						[2] => "
#						[3] => player.php?id=7350
#					)

					$url = $innermatch[3];

					if ($url == '') continue;	// don't append SID if the url is blank
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

					$newattr .= (strpos($url, '?') === FALSE) ? '?' : $amp;	// append proper query separator
					$newattr .= "$sidname=$sid$quote";

					$search[] = $oldattr;
					$replace[] = $newattr;
				}
			}
		}
	}
	if (count($search)) $output = str_replace($search, $replace, $output);

	$this->cms->filter('session_output_filter', $output);
	return $output;
}


} // end of PsychoTheme

?>
