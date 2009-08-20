<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require "smarty/Smarty.class.php";

class Psychosmarty extends Smarty
{
	var $CI;
	var $language 		= 'en_US';
	var $language_open	= '<#';
	var $language_close	= '#>';
	var $language_regex	= '/(?:<!--)?%s(.+?)%s(?:-->(.+?)<!---->)?/ms';

	function Psychosmarty()
	{
		parent::__construct();
		$this->CI =& get_instance();

		$config =& get_config();
		
		// greatly slows down page rendering!
		//$this->force_compile = true;
		
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


		// Setup the prefilter so we can parse language strings
		$this->load_filter('pre', 'translate_language');

		// Prefilter for {asset} tags... experimenting to see if this
		// will make certain compilations faster...
		//$this->load_filter('pre', 'translate_assets');

		//$this->load_filter('variable', 'htmlspecialchars');
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
		$this->assign_by_ref('theme', $this->theme);
		$this->assign_by_ref('force_compile', $this->force_compile);
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
	 * Returns true/false if the compile_dir exists and is writable
	 * by the webserver user.
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
		} elseif (!is_writable($path)) {
			// path exists but we can't write to it, try to chmod
			// it so we can... 
			if (!@chmod($path, 0777)) {
				return false;
			}
		}
		return true;
	}
	
	/**
	 * Render a block of data and optionally using the PsychoStats_Method object.
	 * @param object $method Method to optionally call on the blocks.
	 * @param array $blocks Array of blocks to render.
	 * @param string $filename Template filename to use for html ('block-nav' by default)
	 */
	function render_blocks($method, &$blocks, $args = array(), $filename = 'block-nav') {
		if ($method) {
			array_unshift($args, &$blocks);
			call_user_func_array(array($method, 'execute'), $args);
		}
		
		// render the blocks
		$out = '';
		foreach ($blocks as $b) {
			$out .= $this->view(
				$filename,
				&$b,
				null,
				true
			);
		}
		return $out;
	}
	
	/**
	 * Loads the template from the currently selected theme.
	 * @param string $filename Filename of template to load.
	 * @param array $params Variables that will be passed to the template.
	 * @param boolean $theme Optionally specify a theme to load the template from.
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
			$output = parent::fetch($file, null, null, $theme . '-' . $this->language);
		} catch (Exception $e) {
			//show_error($e->getMessage());
			$output = $this->CI->load->view('smarty_error', array(
				'error_str' => $e->getMessage()
			), $return);
		}
		
		if ($return) {
			return $output;
		} else {
			echo $output;
			return true;
		}
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
		if (empty($template_dir)) {
			$template_dir = $this->template_dir;
		}
		return is_dir($template_dir . DIRECTORY_SEPARATOR . $theme);
	}
	
	function set_theme($theme)
	{
		$this->theme = $theme;
	}

	function trans($str)
	{
		$args = func_get_args();
		array_shift($args);
		if (count($args)) {
			return vsprintf($str, $args);
		} else {
			return $str;
		}
	}
	
	// Changes the open and closing tags for filtering language phrases in
	// the template.
	// @return array original values
	function language_tags($open, $close = null) {
		$orig = array($this->language_open, $this->language_close);
		if (is_array($open)) {
			list($open, $close) = $open;
		}
		if ($open) $this->language_open = $open;
		if ($close) $this->language_close = $close;
		return $orig;
	}
} // END class smarty_library

// Some global smarty functions are defined here, so we don't need to worry
// about these being forgotten in the plugins directory. These functions are
// used on almost all pages.


function smarty_function_elapsed_time($params, $smarty, $template) {
        $ci =& get_instance();
        return $ci->benchmark->elapsed_time('total_execution_time_start');
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
function smarty_function_asset($params, $smarty, $template)
{
	if (empty($params['file']) and empty($params['static'])) {
		throw new Exception("asset: missing 'file' parameter");
	}
	
	$file = $params['file'] ? $params['file'] : $params['static'];
	$static = empty($params['static']) ? false : true;
	$theme = empty($params['theme']) ? $smarty->theme : $params['theme'];

	if ($static) {
		return $smarty->theme_url . $theme . '/' . $file;
	} else {
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
	$smarty =& Smarty::instance();
	if ($key[2]) {	// <!--<#KEYWORD#>-->english phrase here<!---->
		$text = $smarty->trans($key[1]);
		// If the translated text equals the key, then there is no
		// translation and we should use the original phrase as-is.
		return $text == $key[1] ? $key[2] : $text;
	} else {	// <#english phrase here#>
		return $smarty->trans($key[1]);
	}
}

/**
 * Prefilter's all static {asset} tags into a plain string since there's no
 * need to keep these as variables within the compiled template file.
 */
function smarty_prefilter_translate_assets($source, &$smarty) {
	$regex = '/\{asset\s+([^\s}]+)\s*\}/';
	return preg_replace_callback($regex,
				     'smarty_prefilter_translate_assets_callback',
				     $source);
}
function smarty_prefilter_translate_assets_callback($key) {
	$smarty =& Smarty::instance();
	$str = str_replace("\n", " ", $key[1]);
	list($type, $path) = explode('=', $str, 2);
	if ($type == 'static') {
		$path = substr($path, 1, -1); // remove quotes
		return $smarty->theme_url . $smarty->theme . '/' . $path;
	} else {
		return $key[0];
	}
}

?>