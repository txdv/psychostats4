<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

if (!function_exists('user_logged_in')) {
	/**
	 * Returns true/false if the current session is logged in.
	 * @return boolean True if user is currently logged in
	 */
	function user_logged_in() {
		$ci =& get_instance();
		if (isset($ci->ps_user) and $ci->ps_user->logged_in()) {
			return true;
		}
		return false;
	}
}

if (!function_exists('user_is_admin')) {
	/**
	 * Returns true/false if the user is an admin (and is logged in).
	 * @return boolean True if user is an admin
	 */
	function user_is_admin() {
		$ci =& get_instance();
		if (isset($ci->ps_user) and
		    user_logged_in() and
		    $ci->ps_user->is_admin()) {
			return true;
		}
		return false;
	}
}

if (!function_exists('trans')) {
	/**
	 * Translates a string phrase from english to something else.
	 * @param string $str String to translate.
	 * @param mixed [$arg2, $arg3, ...] Extra sprintf() values for the translated string.
	 */
	function trans($str) {
		static $ci = null;
		if (!$ci) {
			$ci =& get_instance();
		}
		if (isset($ci->smarty)) {
			$args = func_get_args();
			return $ci->smarty->trans($str, array_slice($args, 1));
			//return call_user_func_array(array(&$ci->smarty, 'trans'), $args);
		}
		return $str;
	}
}

if (!function_exists('coalesce')) {
	/**
	 * Returns the first non-empty value.
	 * 
	 * @param mixed,mixed[,mixed,...]  2 or more parmaters to check
	 * @return mixed
	 */
	function coalesce() {
		$args = func_get_args();
		foreach ($args as $arg) {
		    if (!empty($arg)) {
			return $arg;
		    }
		}
		return $args ? $args[0] : null;
	}
}

if (!function_exists('query_to_tokens')) {
	/**
	 * Tokenizes a string phrase for search queries and accounts for
	 * double quoted strings properly (not 100% correct) (Multibyte safe).
	 * 
	 * @param string $string  Query string to tokenize
	 * @return array  An array of query tokens (phrases)
	 */
	function query_to_tokens($string) {
		if (!is_string($string)) {
			return false;
		}
	
		$x = trim($string);
		// short circuit if the string is empty
		if (empty($x)) {
			return array();
		}
	       
		// tokenize string into individual characters
		$chars = mb_str_split($x);
		$mode = 'normal';
		$token = '';
		$tokens = array();
		for ($i=0, $j = count($chars); $i < $j; $i++) {
			switch ($mode) {
				case 'normal':
					if ($chars[$i] == '"') {
						if ($token != '') {
							$tokens[] = $token;
						}
						$token = '';
						$mode = 'quoting';
					} else if (in_array($chars[$i], array(' ', "\t", "\n"))) {
						if ($token != '') {
							$tokens[] = $token;
						}
						$token = '';
					} else {
						$token .= $chars[$i];
					}
					break;
	       
				case 'quoting':
					if ($chars[$i] == '"') {
						if ($token != '') {
							$tokens[] = $token;
						}
						$token = '';
						$mode = 'normal';
					} else {
						$token .= $chars[$i];
					}
					break;
			}
		}
		if ($token != '') {
			$tokens[] = $token;
		}
	
		return $tokens;
	}   
}

if (!function_exists('mb_str_split')) {
	/**
	 * Multibyte safe str_split function. Splits a string into an array with
	 * 1 character per element (note: 1 char does not always mean 1 byte).
	 * 
	 * @param string   $str     string to split.
	 * @param integer  $length  character length of each array index. 
	 * @return array            Array of characters
	 */
	function mb_str_split($str, $length = 1) {
		// fall back to old str_split if mb_ functions are not available.
		if (!function_exists('mb_substr')) {
			return str_split($str, $length);
		}
	
		if ($length < 1) return FALSE;
	
		$result = array();
	
		for ($i = 0; $i < mb_strlen($str); $i += $length) {
			$result[] = mb_substr($str, $i, $length);
		}
	
		return $result;
	}
}

if (!function_exists('compact_time')) {
	function compact_time($seconds, $format="hh:mm:ss") {
		$d = $h = $m = $s = "00";
		$old = $seconds;
		$str = $format;
		if ((strpos($str, 'dd') !== FALSE) && ($seconds / (60*60*24)) >= 1) {
			$d = sprintf("%d", $seconds / (60*60*24));
			$seconds -= $d * (60*60*24);
		}
		if ((strpos($str, 'hh') !== FALSE) && ($seconds / (60*60)) >= 1) {
			$h = sprintf("%d", $seconds / (60*60));
			$seconds -= $h * (60*60);
		}
		if ((strpos($str, 'mm') !== FALSE) && ($seconds / 60) >= 1) {
			$m = sprintf("%d", $seconds / 60);
			$seconds -= $m * (60);
		}
		if ((strpos($str, 'ss') !== FALSE) && ($seconds % 60) >= 1) {
			$s = sprintf("%d", $seconds % 60);
		}
		$str = str_replace('dd', sprintf('%02d',$d), $str);
		$str = str_replace('hh', sprintf('%02d',$h), $str);
		$str = str_replace('mm', sprintf('%02d',$m), $str);
		$str = str_replace('ss', sprintf('%02d',$s), $str);
		return $str;
	}
}

if (!function_exists('pct_bar')) {
	function pct_bar($args = array()) {
		if (!is_array($args)) {
			$args = array( 'pct' => $args );
		}
		$args += array(
			'pct'		=> 0,
			'colors'	=> array(),	// a list of colors to use (names or hex)
			//'color1'	=> 'CC0000', 	// deprecated
			//'color2'	=> '00CC00', 	// deprecated
			'textcolor'	=> true, 	// if true an automatic contrast is used. If a string, its used literally
			'width'		=> null,
			'class'		=> 'pct-bar',
			'styles'	=> '',
			'title'		=> null,
		);
		static $colormap = array();
		
		// if no width was given or its invalid, default to 100
		$w = $args['width'];
		if (empty($w) or !is_numeric($w) or $w < 1) {
			$w = 100;
		}

		// Initialize color map indexes.
		// Using $color1 and $color2 is deprecated. Use $colors array
		// instead.
		$map = array();
		if ($args['colors']) {
			$map = $args['colors'];
		} else {
			$config =& get_config();
			$map = $config['pct_bar'];
			if (!is_array($map)) {
				$map = explode(',', $map);
			}
			unset($config);
		}
		//$map = $args['colors']
		//	? $args['colors']
		//	: array(0 => $args['color1'],		// low
		//		(int)$w/2-1 => 'CCCC00',	// mid
		//		$w-1 => $args['color2']);	// high

		// make sure the map has an index that matches the width so
		// the proper number of color gradients are created.
		if (($max = max(array_keys($map))) < $w-1) {
			$map[$w-1] = $map[$max];
		}
		
		// generate a key so we can lookup the static colormap
		$key = md5(serialize($map));
		
		// create a colormap for the specified gradient
		if (!isset($colormap[$key])) {
			$ci =& get_instance();
			$ci->load->library('color');
			$c = $ci->color->create($map);
			$c->SetRange(0, $w);	// 0 .. $width + 1
			$colormap[$key] = $c;
			// debugging; output the color gradient
			//$i=0;
			//foreach ($colormap[$key] as $col) {
			//	printf("<div style='width: 150px; color: white; background-color: %s'>%s (%d)</div>", $col, $col, $i++);
			//}
		}
	
		$color = 0;
		$int = intval($args['pct']);
		if (isset($colormap[$key][$int])) {
			$color = $colormap[$key][$int];
		} else {
			$color = $colormap[$key][0];
		}

		$textcolor = $args['textcolor'];
		if ($textcolor === true) {
			$textcolor = $colormap[$key]->GetContrast($color);
		}

		$styles = !empty($args['styles']) ? $args['styles'] : '';
		// if the original width was set then force the pctbar to it
		if (!empty($args['width'])) {
			$styles .= ' width: ' . $args['width'] . 'px;';
		}
		// add wrapper around styles string
		if (!empty($styles)) $styles = " style='$styles'";

		$title = !empty($args['title']) ? $args['title'] : (int)($args['pct']) . '%';

		$out = sprintf("<span %s%s>%s</span><span %s title='%s'%s><span style='width: %s; background-color: %s'></span></span>",
			!empty($args['class']) ? "class='" . $args['class'] . "-text'" : "",
			$textcolor ? " style='color: $textcolor'" : '',
			$title,
			!empty($args['class']) ? "class='" . $args['class'] . "'" : "",
			$title,
			$styles,
			intval($args['pct']) . '%',
			$color
		);
		return $out;
	}
}

if (!function_exists('dual_bar')) {
	function dual_bar($args = array()) {
		if (!is_array($args)) {
			$args = array( 'pct1' => $args );
		}
		$args += array(
			'pct1'		=> 0,
			'pct2'		=> 0,
			'color1'	=> '0000CC', // blue
			'color2'	=> 'CC0000', // red
			'title1'	=> null,
			'title2'	=> null,
			'width'		=> null,
			'class'		=> 'dual-bar',
			'styles'	=> '',
		);
		if (!empty($args['width']) and (!is_numeric($args['width']) or $args['width'] < 1)) $args['width'] = 100;
		$w = $args['width'] ? $args['width'] : 100;
		//$width = $args['pct'] / 100 * $w; 				// scaled width

		if (!$args['pct2']) {
			$args['pct2'] = $args['pct1'] ? 100 - $args['pct1'] : 100;
		}

		$styles = !empty($args['styles']) ? $args['styles'] : '';
		$styles = " width: " . ($args['width'] ? $args['width'] : '100%');
		//if (!empty($args['width'])) {
		//	$styles = " width: " . $args['width'] . "px;";
		//}
		if (!empty($styles)) $styles = " style='$styles'";
	
		// using a table for the dual pct bar works better and avoids
		// wrapping issues with the second bar (due to the left+right
		// percentages and the center bar > 100%)
		$out  = sprintf("<table class='%s'%s><tbody><tr>", $args['class'], $styles);
		if ($args['pct1']) {
			$out .= sprintf("<td class='left-bar' style='width: %0.02f%%; background-color: #%s' title='%s'></td>",
				$args['pct1'],
				$args['color1'],
				!empty($args['title1']) ? $args['title1'] : $args['pct1'] . '%'
			);
		}
		if (intval($args['pct1']) and intval($args['pct2'])) {
			// only render the center bar if we have both a left
			// and right bar.
			$out .= "<td class='center-bar'></td>";
		}
		if ($args['pct2']) {
			$out .= sprintf("<td class='left-bar' style='width: %0.02f%%; background-color: #%s' title='%s'></td>",
				$args['pct2'],
				$args['color2'],
				!empty($args['title2']) ? $args['title2'] : $args['pct2'] . '%'
			);
		}
		$out .= "</tr></tbody></table>";

		//$out = sprintf(
		//	"<span %s%s>" .
		//	"<span class='left' style='width: %s; background-color: #%s' title='%s'></span>" .
		//	"<span class='center'%s></span>" . 
		//	"<span class='right' style='width: %s; background-color: #%s' title='%s'></span>" .
		//	"</span>",
		//	$args['class'] ? "class='" . $args['class'] . "'" : "",
		//	$styles,
		//	($args['pct1']) . '%',
		//	$args['color1'],
		//	$title1, 
		//	$args['pct2'] ? "" : " style='display: none;'",
		//	($args['pct2']) . '%',
		//	$args['color2'],
		//	$title2
		//	
		//);
		return $out;
	}
}


if (!function_exists('rank_change')) {
	function rank_change($args = array()) {
		if (!is_array($args)) $args['plr'] = array( 'plr' => $args );
		$args += array(
			'plr'		=> NULL,
			'rank'		=> 0,
			'rank_prev'	=> 0,
			'imgfmt'	=> 'rank_%s.png',
			'difffmt'	=> '%d',
			'attr'		=> '',
			'acronym'	=> true,
			'textonly'	=> false,
		);
	
		$output = "";
		$rank = $rank_prev = 0;
		if (is_array($args['plr'])) {
			$rank = $args['plr']['rank'];
			$rank_prev = $args['plr']['rank_prev'];
		} else {
			$rank = $args['rank'];
			$rank_prev = $args['rank_prev'];
		}
	
		$alt = trans('Rank has not changed');
		$dir = 'same';
		$diff = sprintf($args['difffmt'], $rank_prev - $rank);	# note: LESS is better. Opposite of 'skill'.
	
		if ($rank_prev == 0) {
			# no change
		} elseif ($diff > 0) {
			$dir = 'up';
			$up = trans('up');
			$alt = trans('Rank has gone %s by %d spots from %d', $up, $diff, $rank_prev);
		} elseif ($diff < 0) {
			$dir = 'down';
			$down = trans('down');
			$alt = trans('Rank has gone %s by %d spots from %d', $down, abs($diff), $rank_prev);
		}
	
		if ($args['textonly']) {
			$output = sprintf("<span class='rankchange-$dir'>%s%s</span>",
				$diff > 0 ? '+' : '',
				$rank_prev == 0 ? '' : $diff
			);
		} else {
			$ci =& get_instance();
			$img = $ci->smarty->theme_url .
				$ci->smarty->theme . 
				'/img/icons/' .
				sprintf($args['imgfmt'], $dir);
			
			$output = sprintf("<img src='%s' alt='' title='%s' %s/>", $img, $alt, $args['attr']);
	#		if ($args['acronym']) {
	#			$output = "<acronym title='$alt'>$output</acronym>";
	#		}
			$output = "<span class='rankchange-$dir'>$output</span>";
		}
		return $output;
	}
}

if (!function_exists('skill_change')) {
	function skill_change($args = array()) {
		if (!is_array($args)) $args['plr'] = array( 'plr' => $args );
		$args += array(
			'plr'		=> NULL,
			'skill'		=> 0,
			'skill_prev'	=> 0,
			'imgfmt'	=> "skill_%s.png",
			'difffmt'	=> "%.02f",
			'attr'		=> "",
			'acronym'	=> true,
			'textonly'	=> false,
		);
	
		$output = "";
		$skill = $skill_prev = 0;
		if (is_array($args['plr'])) {
			$skill = $args['plr']['skill'];
			$skill_prev = $args['plr']['skill_prev'];
		} else {
			$skill = $args['skill'];
			$skill_prev = $args['skill_prev'];
		}
	
		$alt = trans('Skill has not changed');
		$dir = "same";
		$diff = sprintf($args['difffmt'], $skill - $skill_prev);
	
		if ($skill_prev == 0) {
			# no change
		} elseif ($diff > 0) {
			$dir = "up";
			$up = trans('up');
			$alt = trans('Skill has gone %s by %0.01f points from %d', $up, $diff, $skill_prev);
		} elseif ($diff < 0) {
			$dir = "down";
			$down = trans('down');
			$alt = trans('Skill has gone %s by %0.01f points from %d', $down, abs($diff), $skill_prev);
		}
	
		if ($args['textonly']) {
			$output = sprintf("<span class='skillchange-$dir'>%s%s</span>",
				$diff > 0 ? '+' : '',
				$skill_prev == 0 ? '' : $diff
			);
		} else {
			$ci =& get_instance();
			$img = $ci->smarty->theme_url .
				$ci->smarty->theme . 
				'/img/icons/' .
				sprintf($args['imgfmt'], $dir);
	
			$output = sprintf("<img src='%s' alt='' title='%s' %s/>", $img, $alt, $args['attr']);
	#		if ($args['acronym']) {
	#			$output = "<acronym title='$alt'>$output</acronym>";
	#		}
			$output = "<span class='skillchange-$dir'>$output</span>";
		}
		return $output;
	}
}

// substitute for json_encode if it's not defined already (PHP5.2.1)
// http://php.net/json_encode
if (!function_exists('json_encode')) {
	function json_encode($a=false) {
		if (is_null($a)) return 'null';
		if ($a === false) return 'false';
		if ($a === true) return 'true';
		if (is_scalar($a)) {
			if (is_float($a)) {
				// Always use "." for floats.
				return floatval(str_replace(",", ".", strval($a)));
			}
		
			if (is_string($a)) {
				static $jsonReplaces = array(
					array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'),
					array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"')
				);
				return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
			}
			
			return $a;
		}

		$isList = true;
		for ($i = 0, reset($a); $i < count($a); $i++, next($a)) {
			if (key($a) !== $i) {
				$isList = false;
				break;
			}
		}
		
		$result = array();
		if ($isList) {
			foreach ($a as $v) {
				$result[] = json_encode($v);
			}
			return '[' . join(',', $result) . ']';
		} else {
			foreach ($a as $k => $v) {
				$result[] = json_encode($k) . ':' . json_encode($v);
			}
			return '{' . join(',', $result) . '}';
		}
	}
}


// Shortcut function used in callbacks when you want !empty() values.
if (!function_exists('not_empty')) {
	function not_empty($x) {
		return !empty($x);
	}
}

// abbreviates the number given into the closet KB, MB, GB, TB range.
if (!function_exists('abbrnum')) {
	// most of the integers we use are 1000 based, usually this func would
	// be useful when dealing with bytes (1024 base)
	function abbrnum($num, $precision=0, $base=1000, $abbr_strs=null) {
		static $strs; // = array('',' KB',' MB',' GB',' TB');
		if ($strs === null) {
			$strs = array(
				'',
				' ' . trans('K'),
				' ' . trans('M'),
				' ' . trans('B'),
			);
		}
		if ($abbr_strs === null) {
			$abbr_strs =& $strs;
		}
		if (!is_numeric($precision)) $precision = 2;
		if (!$num) return '0' . $abbr_strs[0];
	
		$i = 0;
		while (($num >= $base) and ($i < count($abbr_strs)-1)) {
			$num /= $base;
			$i++;
		}
		return sprintf('%.' . $precision . 'f', $num) . $abbr_strs[$i];
	}
}

// Returns a full URL of the image specified. If the image can not be found
// false is returned. Will recursively search the directory leafs given.
// @prototype img_dir(type, name, recursive(bool), leaf[, leaf, ...])
// @prototype img_dir(type, name, leaf[, leaf, ...])
if (!function_exists('img_url')) {
	function img_url($type, $name, $recursive = false) {
		static $exts = array( 'png', 'jpg', 'gif' );
		$args = func_get_args();
		$leafs = array_slice($args, 2);
		if (is_bool($recursive)) {
			// remove the first leaf since its actually a boolean flag
			array_shift($leafs);
		} else {
			// if no flag was given assume recursive is true
			$recursive = true;
		}

		$config =& get_config();
		$root = $config['img_dir'] .
			DIRECTORY_SEPARATOR .
			$config['img_' . $type . '_name'] .
			DIRECTORY_SEPARATOR;
		while (count($leafs)) {
			$path = $root . implode(DIRECTORY_SEPARATOR, $leafs);

			foreach ($exts as $ext) {
				$file = $name . '.' . $ext;
				$fullfile = $path . DIRECTORY_SEPARATOR . $file;
				if (file_exists($fullfile)) {
					return $config['img_url'] .
						$config['img_' . $type . '_name'] .
						'/' .
						implode('/', $leafs) .
						"/$file";
				}
			}
			
			if (!$recursive) {
				break;
			}
			array_pop($leafs);
		}
		return false;
	}
}

if (!function_exists('uuid')) {
	// generates a new UUID / GUID (version 4; random)
	function uuid($dashes = true) {
		return sprintf($dashes ? '%04x%04x-%04x-%04x-%04x-%04x%04x%04x' : '%04x%04x%04x%04x%04x%04x%04x%04x',
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
	}
}

//if (!function_exists('url')) {
//	// builds an URL 
//	function url($arg = array()) {
//		if (!is_array($arg)) $arg = array( '_base' => $arg );
//		$arg += array(					// argument defaults
//			'_base'		=> NULL,		// base URL; if NULL $PHP_SELF is used
//			'_anchor'	=> '',			// optional anchor
//			'_encode'	=> 1,			// should parameters be url encoded?
//			'_encodefunc'	=> 'rawurlencode',	// how to encode params
//			'_amp'		=> '&amp;',		// param separator
//			'_raw'		=> '',			// raw URL appended to final result (is not encoded)
//			'_ref'		=> NULL,		// if true/numeric referrer is autoset, if a string it is used instead
//			// any other key => value pair is treated as a parameter in the URL
//		);
//		$base = ($arg['_base'] === NULL) ? htmlentities($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') : $arg['_base'];
//		$enc = $arg['_encode'] ? 1 : 0;
//		$encodefunc = ($arg['_encodefunc'] && function_exists($arg['_encodefunc'])) ? $arg['_encodefunc'] : 'rawurlencode';
//		$i = (strpos($base, '?') === FALSE) ? 0 : 1;
//	
//		foreach ($arg as $key => $value) {
//			if ($key{0} == '_') continue;		// ignore any param starting with '_'
//			$base .= ($i++) ? $arg['_amp'] : '?';
//			$base .= "$key=";			// do not encode keys
//			$base .= $enc ? $encodefunc($value) : $value;
//		}
//	
//		if ($arg['_ref']) {
//			$base .= ($i++) ? $arg['_amp'] : '?';
//			if ($arg['_ref'] and $arg['_ref'] == 1) {
//				$base .= 'ref=' . $encodefunc($_SERVER['PHP_SELF'] .
//					($_SERVER['QUERY_STRING'] != null ? '?' . $_SERVER['QUERY_STRING'] : '')
//				);
//			} elseif (!empty($arg['_ref'])) {
//				$base .= 'ref=' . $encodefunc($arg['_ref']);
//			}
//		}
//	
//		if ($arg['_raw']) $base .= ($i ? $arg['_amp'] : '?') . $arg['_raw'];
//		if ($arg['_anchor']) $base .= '#' . $arg['_anchor'];
//	
//		return $base;
//	}
//}

?>