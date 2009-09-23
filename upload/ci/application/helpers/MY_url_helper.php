<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 *	Extending the core URL helper with more functions
 *
 */

/**
 *	Return's an URL based on the current page being viewed w/o any extra
 *	URI segments
 *
 *	@param 		string	Optional URL to append to the end.
 *	@example
 *		before: /index.php/mypage/view/param1/param2
 *		after:  /index.php/mypage/view/
 *
 *		before: /mypage/index/param1/
 *		after:  /mypage/
 */
if (!function_exists('page_url')) {
	function page_url($extra = null) {
		$ci =& get_instance();
		$url = $ci->router->class . '/';
		if ($ci->router->method != 'index') {
			$url .= $ci->router->method . '/';
		}
		if (isset($extra)) {
			$url .= $extra;
		}
		$url = rel_site_url($url);
		return $url;
	}
}

/**
 * Returns a root absolute URL for a Psychostats page given.
 */
if (!function_exists('ps_site_url')) {
	function ps_site_url($page, $path = '') {
		$url = ps_page_name($page);
		// add the path to the base fragment and return a full URL.
		return rel_site_url("$url/$path");
	}
}

if (!function_exists('ps_page_name')) {
	function ps_page_name($page) {
		static $names = array();
		if (!array_key_exists($page, $names)) {
			$config =& get_config();
			if (array_key_exists($page . '_url', $config)) {
				$names[$page] = $config[$page . '_url'];
			} else {
				$names[$page] = $page;
			}
		}
		return $names[$page];
	}
}

/**
 * Returns a relative URL based on the site_url configured. This is esentially
 * the same as site_url() within a protocol or domain on the URL. Most URL's
 * (if not all) should be created using this.
 */
if (!function_exists('rel_site_url')) {
	function rel_site_url($path) {
		static $url = null;
		if ($url === null) {
			$url = base_url();
			// strip off everything up to the first slash, ignoring
			// the double // on the prefix.
			$url = substr($url, strpos($url, '/', 9));
		}
		return $url . $path;
	}
}

if (!function_exists('redirect_previous')) {
	/**
	 * Redirects the user to the previous URL (if set). If no previous URL
	 * is set then it redirects to the main index.
	 */
	function redirect_previous($ref = null) {
		$ci =& get_instance();
		if ($ref === null) {
			$ref = $ci->input->get_post('ref');
		}
		
		if ($ref) {
			redirect($ref);
		} else {
			redirect('');
		}
	}
}

?>