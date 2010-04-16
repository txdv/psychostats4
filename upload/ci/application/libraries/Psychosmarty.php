<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require "smarty/Smarty.class.php";

/**
 * Psychostats "Smarty" subclass for themed output.
 * 
 * $Id$
 * 
 */
class Psychosmarty extends Smarty
{
	var $CI;
	var $language 		= 'en_US';
	var $language_open	= '<#';
	var $language_close	= '#>';
	var $language_regex	= '/(?:<!--)?%s(.+?)%s(?:-->(.+?)<!---->)?/ms';
	var $enable_compression	= false;	// true = output is GZIP'ed
	var $compile_safe	= true; 	// true = compile_dir is optional
	var $extra_compile_id	= '';

	function Psychosmarty()
	{
		parent::__construct();

		$this->CI =& get_instance();
		$config =& get_config();
		
		// greatly slows down page rendering!
		//$this->force_compile = true;
		
		$this->error_reporting = E_ALL & ~E_STRICT & ~E_NOTICE & ~E_WARNING;
		
		// absolute path prevents "template not found" errors
		$this->template_dir = (!empty($config['theme_dir'])
			? $config['theme_dir']
			: BASEPATH . 'application/themes');
		
		$this->theme_url = (!empty($config['theme_url'])
			? $config['theme_url']
			: 'application/themes/');

		$this->compile_dir = (!empty($config['compile_dir'])
			? $config['compile_dir']
			: BASEPATH . 'cache');
		$this->cache_dir = $this->compile_dir;

		$this->charset = (!empty($config['charset'])
			? $config['charset']
			: 'UTF-8');

		$this->set_theme((!empty($config['default_theme']))
			? $config['default_theme']
			: 'default');

		// enable GZIP compression for client output (auto-detects if
		// the client supports it.)
		$this->enable_compression = (bool)$config['enable_compression'];

		// allow open ended { ... } blocks to be treated as literal
		// blocks (for css/js mainly) w/o having to use {literal} tags.
		$this->auto_literal = true;

		// Setup the prefilter so we can parse language strings
		$this->loadFilter('pre', 'translate_language');

		//$this->loadFilter('variable', 'htmlspecialchars');
		//$this->enableVariableFilter();
		//$this->disableVariableFilter();
		//$this->enableCaching();
		
		// we always use the url helper
		// this is autoloaded.
		//$this->CI->load->helper('url');

		// Set some useful template vars
		$this->assign(array(
			'theme_url'	=> $this->theme_url,
			'theme_dir'	=> $this->template_dir,
			'charset'	=> $this->charset,
			'language'	=> $this->language,
		));
		// it's possible for these variables to change at any point
		// during page rendering so we assign refs instead of static.
		$this->assignByRef('theme', $this->theme);
		$this->assignByRef('force_compile', $this->force_compile);

		// it's important to note that using $GET within a template
		// can be insecure so be sure to escape any output! This is
		// here as a helper and isn't really meant to be used that much.
		$this->assignByRef('GET', $_REQUEST);
	}
	
	/**
	 * Returns the page layout code needed to render the page properly.
	 * The codes are based on the Yahoo Grids CSS.
	 * @param string $layout String identifying the layout to use.
	 * @see http://developer.yahoo.com/yui/grids/
	 */
	function page_layout($layout) {
		switch ($layout) {
			case '160left': 	return 'yui-t1';
			case '180left': 	return 'yui-t2';
			case '300left':		return 'yui-t3';
			case '180right': 	return 'yui-t4';
			case '240right': 	return 'yui-t5';
			case '300right':	return 'yui-t6';
			default:
			case 'full':		return 'yui-t7';
		}
	}
	
	/**
	 * Checks to see if the compile_dir exists and is writable by the 
	 * webserver user.
	 * @param string $path Alternate path to verify. Uses $this->compile_dir by default.
	 * @return boolean Returns true/false if the compile_dir is valid.
	 */
	function verify_compile_dir($path = null) {
		if ($path === null) {
			$path = $this->compile_dir;
		}
		// this isn't recommended because it'll cause a file to be
		// created on every page request but its the only way to make
		// sure its truly writable on WINDOWS machines.
		//if (!is_really_writable($path)) {
		//	return false;
		//}
		if (!is_writable($path)) {
			return false;
		}
		return true;
	}
	
	/**
	 * Attempts to create the compile_dir directory (or $path)
	 * @param string $path Alternate path to create (uses compile_dir by default).
	 * @return boolean Returns true if the path was created or already exists.
	 */
	function create_compile_dir($path = null) {
		if ($path === null) {
			$path = $this->compile_dir;
		}
		if (!file_exists($path)) {
			// recursive (php5 only)
			if (!@mkdir($path, 0777, true)) {
				return false;
			}
		} elseif (!$this->verify_compile_dir($path)) {
			// path exists but we can't write to it, try to chmod
			// it so we can... 
			if (!@chmod($path, 0777)) {
				return false;
			}
		}
		return true;
	}
	
	/**
	 * Render a block of data and optionally using the Psychostats_Method object.
	 * @param object $method Method to optionally call on the blocks.
	 * @param array $blocks Array of blocks to render.
	 * @param string $filename Template filename to use for html ('block-nav' by default)
	 */
	function render_blocks($method, &$blocks, $args = array(), $filename = 'block-nav') {
		if ($method) {
			// $method should be a Psychostats_Method object
			array_unshift($args, &$blocks);
			call_user_func_array(array($method, 'execute'), $args);
		}
		
		// render the blocks
		$out = '';
		foreach ($blocks as $b) {
			$out .= $this->view(
				$filename,
				$b,
				null,
				true
			);
		}
		return $out;
	}
	
	/**
	 * Loads the template from the selected theme.
	 * @param string $filename Filename of template to load.
	 * @param array $params Variables that will be passed to the template.
	 * @param string $theme Optionally specify a theme to load the template from.
	 * @param boolean $return Returns the output as a string if true.
	 */
	function view($filename, $params = array(), $theme = null, $return = false)
	{
		if (strpos($filename, '.') === false) {
			$filename .= '.html';
		}

		if (is_array($params)) {
			$this->assign($params);
		}

		if (empty($theme)) {
			$theme = $this->theme;
		}
		
		// Get relative path to the template file.		
		$file = $this->theme_filename($filename, $theme);

		// check if the template file exists within the theme
		//if (!$this->is_file($filename, $theme)) {
		//	//show_error("Smarty: [$file] Template was not found!");
		//	$this->CI->load->view('smarty_error', array(
		//		'error_str' => "Smarty: [$file] Template was not found!"
		//	));
		//}
		
		$output = '';
		try {
			//$output = parent::fetch($file, null, $theme . '-' . $this->language);
			$output = $this->fetch($file, null, $theme . '-' . $this->language);
		} catch (Exception $e) {
			//show_error($e->getMessage());
			$output = $this->CI->load->view('smarty_error', array(
				'error_str' => $e->getMessage()
			), $return);
		}

		
		if ($return) {
			// don't compress the output if we want the raw string
			return $output;
		} else {
			if ($this->enable_compression) {
				echo $this->ob_gzhandler($output, PHP_OUTPUT_HANDLER_END);
				//ob_start(array($this, 'ob_gzhandler'));
				//echo $output;
				//ob_end_flush();
			} else {
				echo $output;
			}
			return true;
		}
	}

	/**
	 * fetches a rendered Smarty template. Overrides parent to provide a
	 * "safe" fetch mechanism if the compile_dir is not writable then the
	 * template is returned normally but simply not compiled to disk.
	 * A special header is sent "X-smarty-string: 1" for debugging purposes.
	 */
	function fetch($template, $cache_id = null, $compile_id = null, $parent = null) {
		if ($this->extra_compile_id) {
			$compile_id = $this->extra_compile_id . '_' . (string)$compile_id;
		}

		$output = '';
		try {
			$output = parent::fetch($template, $cache_id, $compile_id, $parent);
			// temporary work-around until parent::fetch throws a
			// proper exception when it fails to load a template.
			if ($output == '') {
				throw new Exception('compile_dir is not writable!');
			}
		} catch (Exception $e) {
			if (stripos($e->getMessage(), 'not writable') !== false ||
			    stripos($e->getMessage(), 'not exist') !== false) {
				if ($this->compile_safe) {
					// fetch the template using a STRING resource
					// so the template is NOT compiled
					header('X-smarty-string: 1');
					$source = 'string:' . file_get_contents($this->template_dir . DIRECTORY_SEPARATOR . $template);
					return parent::fetch($source, $cache_id, $compile_id, $parent);
				}
			}
			throw $e; // re-throw
		}
		return $output;
	}

	/**
	 * Returns true if the file exists within the theme.
	 * @param string $filename Filename to check for within theme.
	 */
	function is_file($filename, $theme = null)
	{
		if (empty($theme)) {
			$theme = $this->theme;
		}
		return is_file($this->template_dir . DIRECTORY_SEPARATOR . $theme . DIRECTORY_SEPARATOR . $filename);
	}

	/**
	 * Returns the relative filename within the specified theme.
	 * @param string $filename Filename of template.
	 * @param string $theme Alternate theme to retreive filename from.
	 */
	function theme_filename($filename, $theme = null)
	{
		if (empty($theme)) {
			$theme = $this->theme;
		}
		return $theme . DIRECTORY_SEPARATOR . $filename;
	}
	
	
	/**
	 * Returns true/false if the theme specified exists within template_dir.
	 * @param string $theme Name of theme to check.
	 */
	function theme_exists($theme, $template_dir = null)
	{
		if (empty($theme)) return false;
		if (empty($template_dir)) {
			$template_dir = $this->template_dir;
		}
		return is_dir($template_dir . DIRECTORY_SEPARATOR . $theme);
	}
	
	/**
	 * Set's the current theme to use for templates.
	 */
	function set_theme($theme)
	{
		$this->theme = $theme;
	}

	/**
	 * Translates the string.
	 * @param mixed $var1,$var2,... 1 or more sprintf values to use in the string.
	 */
	function trans($str)
	{
		// ignore first parameter $str
		$args = array_slice(func_get_args(), 1);
		if ($args) {
			// if the first argument is an array then use it directly.
			if (is_array($args[0])) {
				// ignore args[0] if its an empty array
				if ($args[0]) {
					return vsprintf($str, $args[0]);
				} else {
					return $str;
				}
			} else {
				return vsprintf($str, $args);
			}
		} else {
			return $str;
		}
	}
	
	/**
	 * Changes the open and closing tags for filtering language phrases in
	 * the template.
	 * @param string $open The opening tag.
	 * @param string $close Optional closing tag.
	 * @return array original values
	 */
	function language_tags($open, $close = null) {
		$orig = array($this->language_open, $this->language_close);
		if (is_array($open)) {
			list($open, $close) = $open;
		}
		if ($open) $this->language_open = $open;
		if ($close) $this->language_close = $close;
		return $orig;
	}

	/**
	 * Output buffer handler to support GZIP/DEFLATE compression of output.
	 * enable using ob_start(array($this, 'ob_gzhandler')) or call directly:
	 * $this->ob_gzhandler($buffer, PHP_OUTPUT_HANDLER_END);
	 * Note the use of the flag PHP_OUTPUT_HANDLER_END, that is required
	 * if you call the function directly.
	 */
	function ob_gzhandler($buffer, $flags) {
		// don't do anything if the buffer isn't being closed or if
		// headers were already sent.
		if (($flags & PHP_OUTPUT_HANDLER_END != PHP_OUTPUT_HANDLER_END)
		    || headers_sent()
		    || empty($buffer)) {
			return false;
		}

		$zipped = '';
		$original_length = strlen($buffer);
		$encoding = false;

		// build an array of accepted encodings
		$accept = (array)explode(',', str_replace(' ', '', strtolower($_SERVER['HTTP_ACCEPT_ENCODING'])));
		if (in_array('gzip', $accept)) {
			$zipped = gzencode($buffer);
			$encoding = 'gzip';
		} elseif (in_array('deflate', $accept)) {
			$zipped = gzcompress($buffer);
			$encoding = 'deflate';
		} else {
			$zipped =& $buffer;
		}
		$length = strlen($zipped);
		
		// don't send compressed output if the zipped length is
		// greater than the original (only occurs on small output)
		if ($length > $original_length) {
			$zipped = false;
			$length = strlen($buffer);
		}
		
		// provide content-length to allow HTTP persistent connections.
		// PHP's built in ob_gzhandler does not send this header
		header("Content-Length: $length", true);
		
		if ($zipped) {
			header("Vary: Accept-Encoding", true); 	// handle proxies
			header("Content-Encoding: $encoding", true);
			// add an informational value showing how much
			// compression we actually achieved (debugging)
			header(sprintf("X-Compression: %d/%d (%.0f:1) (%.02f%%)",
				$length,
				$original_length,
				$original_length / $length,
				abs($length / $original_length * 100 - 100)
			), true);
			return $zipped;
		} else {
			return $buffer;
		}
	}

} // END class psychosmarty

// Some global smarty functions are defined here, so we don't need to worry
// about these being forgotten in the plugins directory. These functions are
// used on almost all pages.

/**
 * Returns the elapsed time since page rendering began. {elapsed_time}
 */
function smarty_function_elapsed_time($params, $smarty) {
        $ci =& get_instance();
        return $ci->benchmark->elapsed_time('total_execution_time_start');
}

/**
 * Generates the overall header menu for the theme. {ps_header_menu}
 */
function smarty_function_ps_header_menu($params, $smarty, $template) {
	$config =& get_config();

	if (!user_is_admin()) {
		unset($config['header_menu']['admin']);
	}
	reset($config['header_menu']);

	$out = '';
	$i = 0;
	while (list($url, $label) = each($config['header_menu'])) {
		// If the url does not start with a slash or protocol then
		// generate a clean relative url.
		if (substr($url,0,1)!='/' and !preg_match('|^[a-z0-9]+://|i', $url)) {
			$url = rel_site_url($url);
		}
		$out .= sprintf("<li%s><a href='%s'>%s</a></li>\n",
			$i++ ? '' : ' class=\'first-child\'',
			$url,
			trans($label)
		);
	}
	
	return "<ul>\n" . $out . "</ul>\n";
}

/**
 * Create URL for theme assets that can be static or dynamic. Dynamic assets
 * are parsed by the smarty engine while static assets are not.
 * 
 * Examples:
 * <link href='{asset file='css/base.css'}' rel='stylesheet' type='text/css' />
 * <link href='{asset file='css/base.css' static=true}' rel='stylesheet' type='text/css' />
 * <link href='{asset static='css/base.css'}' rel='stylesheet' type='text/css' />
 * 
 * @param array $params parameters
 *          - file (string) relative path to file name.
 *          - static (boolean) if true the file is not compiled via smarty.
 * @param object $smarty Smarty object
 * @param object $template template object
 * @return string 
 */
//function smarty_function_asset($params, $smarty)
//{
//	if (empty($params['file']) and empty($params['static'])) {
//		throw new Exception("asset: missing 'file' parameter");
//	}
//
//	$file = $params['file'] ? $params['file'] : $params['static'];
//	$static = empty($params['static']) ? false : true;
//	$theme = empty($params['theme']) ? $smarty->theme : $params['theme'];
//
//	if ($static) {
//		return $smarty->theme_url . $theme . '/' . $file;
//	} else {
//		// link to the theme_asset controller for the file
//		return rel_site_url("ta/$theme/$file");
//	}
//}

// The {asset} tag is a 'compiler' function so its statically added to the
// compiled template which reduces overhead when the template is viewed.
// Once an ASSET url is set there's no reason for it to be dynamic.
function smarty_compiler_asset($params, $compiler)
{
	$ci =& get_instance();
	
	if (empty($params['file']) and empty($params['static'])) {
		throw new Exception("asset: missing 'file' parameter");
	}

	$file = $params['file'] ? $params['file'] : $params['static'];
	$static = empty($params['static']) ? false : true;
	$theme = empty($params['theme']) ? $ci->smarty->theme : $params['theme'];

	// remove quotes around string since this is a compiler function the
	// full string as seen in the template file will be given to us.
	if (in_array(substr($file,0,1), array("'",'"'))) {
		$file = substr($file, 1, -1);
	}

	if ($static) {
		return $ci->smarty->theme_url . $theme . '/' . $file;
	} else {
		// link to the theme_asset controller for the file
		return rel_site_url("ta/$theme/$file");
	}
} 

/**
 * Smarty PREFILTER to translate phrases in a theme before it's compiled.
 * Once a template is compiled this filter is NOT called. In order for
 * changes to your translatations to take effect you must recompile the
 * templates (delete the cached copies of the theme).
 * 
 * @example
 * Add to your Smarty constructor:
 * 	$this->load_filter('pre', 'translate_language');
 *
 * 	Your smarty sub-class must have the following variables and methods:
 * 	
 * 	$language_open	= '<#';
 * 	$language_close = '#>';
 * 	$language_regex = '/(?:<!--)?%s(.+?)%s(?:-->(.+?)<!---->)?/ms';
 *
 * 	function trans($phrase);
 * 		Returns the translated phrase
 */
function smarty_prefilter_translate_language($source, &$smarty) {
	$regex = sprintf($smarty->language_regex,
			 $smarty->language_open,
			 $smarty->language_close);
	return preg_replace_callback($regex,
				     'smarty_prefilter_translate_language_callback',
				     $source);
}
function smarty_prefilter_translate_language_callback($key) {
	$ci =& get_instance();
	$smarty =& $ci->smarty;

	if ($key[2]) {	// <!--<#KEYWORD#>-->english phrase here<!---->
		$text = $smarty->trans($key[1]);
		// If the translated text equals the key, then there is no
		// translation and we should use the original phrase as-is.
		return $text == $key[1] ? $key[2] : $text;
	} else {	// <#english phrase here#>
		return $smarty->trans($key[1]);
	}
}

?>