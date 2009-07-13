<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 4.3.2 or newer
 *
 * @package		CodeIgniter
 * @author		Jason Morriss
 * @copyright		Copyright (c) 2008, Jason Morriss.
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://www.psychostats.com/
 * @since		Version 1.0
 * @filesource
 */

/**
 * Get URL Parameters - Returns all $_GET paramaters from the URL.
 * 			uri_protocol must be set to PATH_INFO in config.php.
 * 			really? I think only enable_query_strings has to be
 * 			enabled.
 *
 *	Avoid any routing issues by using the following in routes.php.
 *	This will allow the function to always start at the proper index for
 *	parameters regardless if the 'method' is part of the URI or not.
 * 	$route['([a-z]+)/:any'] = '$1/index';
 *
 * @access	public
 * @param	boolean If true filters all params through xss_clean. If
 * 			global_xss_filtering is enabled filtering is forced.
 * @param 	array	Optional defaults for keys that don't exist.
 * @return	array	Array of all parameters found.
 */
if (!function_exists('get_url_params')) {
	function get_url_params($defaults = array(), $skip = 0) {
		$ci =& get_instance();
		$segments = $ci->uri->segment_array();
		// note: array_shift resets the indexes as a side effect.
		// which is why the indexes below look out of order.

		// remove the class from the segments array
		if ($segments[1] == $ci->router->class) {
			array_shift($segments);
		}
		// remove the method from the segments array 
		if ($segments and $segments[0] == $ci->router->method) {
			array_shift($segments);
		}

		if ($skip > 0 and count($segments > $skip)) {
			$segments = array_slice($segments, $skip);
		}

		// collect key/value pairs from the URI segments
		$get = array();
		for ($i=0, $j=count($segments); $i<$j; $i++) {
			$key = $segments[$i];
			if ($i+1 < $j) {
				$val = $segments[++$i];
			} else {
				$val = false;
			}
			$get[$key] = $val;
		}
		
		if (is_array($defaults) and count($defaults)) {
			foreach ($defaults as $key => $val) {
				if (!isset($get[$key])) {
					$get[$key] = $val;
				}
			}
		}
		
		// process post/get paramaters
		$keys = array_unique(array_merge(array_keys($_POST), array_keys($_GET)));
		foreach ($keys as $key) {
			$get[$key] = $ci->input->get_post($key, true);
		}
		
		return $get;
	}
}

/**
 * Build an URL query string based on the array and defaults given. Current
 * parameters that match the defaults are not used to help minimize the URL.
 *
 * @access	public
 * @param	array	$params 	Parameters to build query string with.
 * @param 	array	$defaults 	Optional defaults.
 * @param 	mixed	$exclude	Exclude parameters list.
 * @param	string	$sep	 	Seperator of parameters. '&','&amp;' or '/'
 * @param	string	$eq		Equals sign. '=' or '/'
 * @return	array	Array of all parameters found.
 */
if (!function_exists('build_query_string')) {
	function build_query_string($params, $defaults = array(), $exclude = array(), $sep = '/', $eq = '/') {
		if (!is_array($defaults)) {
			$defaults = array();
		}
		if (isset($exclude) and !is_array($exclude)) {
			$exclude = array( $exclude );
		} elseif (!isset($exclude)) {
			$exclude = array();
		}
		$str = '';
		foreach ($params as $key => $val) {
			if (!in_array($key, $exclude)
			    and !empty($val)
			    and (!isset($defaults[$key]) or $defaults[$key] != $val)
			    ) {
				if ($str != '') {
					$str .= $sep;
				}
				$str .= $key . $eq . $val; 
			}
		}
		return $str;
	}
}

?>