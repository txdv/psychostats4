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

if (!function_exists('trans')) {
	/**
	 * Translates a string phrase from english to something else.
	 * @param string $str String to translate.
	 * @param mixed [$arg2, $arg3, ...] Extra sprintf() values for the translated string.
	 */
	function trans($str) {
		$ci =& get_instance();
		if (isset($ci->smarty)) {
			$args = func_get_args();
			return call_user_func_array(array(&$ci->smarty, 'trans'), $args);
		}
		return $str;
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
			'color1'	=> 'CC0000', // red
			'color2'	=> '00CC00', // green
			'degrees'	=> 2,
			'width'		=> null,
			'class'		=> 'pct-bar',
			'styles'	=> '',
			'title'		=> null,
		);
		static $colors = array();
		if (!empty($args['width']) and (!is_numeric($args['width']) or $args['width'] < 1)) $args['width'] = 100;
		$w = $args['width'] ? $args['width'] : 100;
		//$width = $args['pct'] / 100 * $w; 				// scaled width
		$key = $args['color1'] . '-' . $args['color2'];
		if (!isset($colors[$key])) {
			$ci =& get_instance();
			$ci->load->library('color');
			$ci->color->setColors($args['color1'], $args['color2']);
			// 100 colors, no matter the width
			$colors[$key] = $ci->color->getRange(100, $args['degrees']);
			$colors[$key][0] = $args['color1'];
			//$i=0;
			//foreach ($colors[$key] as $col) {
			//	printf("<div style='color: white; background-color: %s'>%s (%d)</div>", $col, $col, $i++);
			//}
		}
	
		$styles = !empty($args['styles']) ? $args['styles'] : '';
		if (!empty($args['width'])) {
			$styles = " width: " . $args['width'] . "px;";
		}
		if (!empty($styles)) $styles = " style='$styles'";
	
		$int = intval($args['pct']);
		if ($int > 0 and $int < 100) {
			$color = $colors[$key][$int];
		} else {
			$color = ($int == 0) ? $args['color1'] : $args['color2'];
		}
		$title = !empty($args['title']) ? $args['title'] : (int)($args['pct']) . '%';
		$out = sprintf("<span %s>%s</span><span %s title='%s'%s><span style='width: %s; background-color: #%s'></span></span>",
			!empty($args['class']) ? "class='" . $args['class'] . "-text'" : "",
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
	
		$alt = trans("Skill has not changed");
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