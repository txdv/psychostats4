<?php
/***

	functions.php
	$Id$

	General utility functions for PsychoStats.

***/

if (!defined("PSYCHOSTATS_PAGE")) die("Unauthorized access to " . basename(__FILE__));

if (defined("FILE_PS_FUNCTIONS_PHP")) return 1; 
define("FILE_PS_FUNCTIONS_PHP", 1); 


define("ACL_NONE", -1);
define("ACL_DENIED", -1);
define("ACL_USER", 1);
define("ACL_CLANADMIN", 5);
define("ACL_ADMIN", 99);

// wrapper for xml_response. returns a single 'code' and 'message' xml response
function xml_result($code, $message, $header = true, $extra = array()) {
	$data = array_merge(array( 'code' => $code, 'message' => $message ), $extra);
	if ($header) header("Content-Type: text/xml");
	return xml_response($data);
}
// converts the array key=>value pairs into an XML (SML) response string.
// this function is recursive and will work with sub arrays to create nested <nodes>.
// key names are not checked for validity. 
function xml_response($data = array(), $root = 'response', $indent = 0) {
	$tab = str_repeat("\t", $indent);
	$do_root = !empty($root);
	$xml = $do_root ? "$tab<$root>\n" : "";
	foreach ($data as $key => $value) {
		if (is_array($value)) {
			$xml .= xml_response($value, $key, $indent + 1);
		} else {
			if (is_numeric($key)) $key = 'index';
			$xml .= "$tab\t<$key>"; // one extra tab is added to nest under its root node
			if (strpos($value,'<') || strpos($value,'>') || strpos($value,'&')) {
				$xml .= "<![CDATA[$value]]>"; 
			} else {
				$xml .= $value;
			}
			$xml .= "</$key>\n";
		}
	}
	if ($do_root) $xml .= "$tab</$root>\n";
	return $xml;
}

// --------------------------------------------------------------------------------------------------------------------
// Interpolates special $tokens in the string.
// $tokens is a hash array containing variables and can be nested 1 level deep (ie $tok1 or $tok2.value)
// if $fill is true than any tokens in the string that do not have a matching variable in $tokens is not removed.
function simple_interpolate($str, $tokens, $fill = false) {
	$return = "";
	$ofs = 0;
	$idx = 0;
	$i = 0;
	while (preg_match('/\$([a-z][a-z\d_]+)(?:\.([a-z][a-z\d_]+))?/', $str, $m, PREG_OFFSET_CAPTURE, $ofs)) {
		if ($i++ > 1000)  {
			die("ENDLESS LOOP in simple_interpolate (line " . __LINE__ . ") with string '$str'");
		}
		$var1	= strtolower($m[1][0]);
		$var2 	= $m[2][0] ? strtolower($m[2][0]) : '';
		$idx	= $m[0][1];	// get position of where match begins
		if (array_key_exists($var1, $tokens)) {
			if (!empty($var2)) {
				if (array_key_exists($var2, $tokens[$var1])) {
					$rep = $tokens[$var1][$var2];
				} else {
					$rep = $fill ? "$var1.$var2" : '';
				}
			} else {
				$rep = $tokens[$var1];
			}
		} else {
			$rep = $fill ? $var1 : '';
		}

		// We replace each token 1 by 1 even if $token1 matches more than once.
		// this will prevent possible $tokens inside replacement strings from being interpolated.
		$varstr = $var2 ? "$var1.$var2" : $var1;
		$str = substr_replace($str, $rep, $idx, strlen($varstr)+1);
		$ofs = $idx + strlen($rep);
	}
	return $str;
}
// --------------------------------------------------------------------------------------------------------------------
function pct_bar($args = array()) {
	global $cms;
	require_once(dirname(__FILE__) . "/class_Color.php");
	$args += array(
		'pct'		=> 0,
		'color1'	=> 'cc0000',
		'color2'	=> '00cc00',
		'degrees'	=> 1,
		'width'		=> null,
		'class'		=> 'pct-bar',
		'styles'	=> '',
		'title'		=> null,
	);
	static $colors = array();
	if (!empty($args['width']) and (!is_numeric($args['width']) or $args['width'] < 1)) $args['width'] = 100;
	$w = $args['width'] ? $args['width'] : 100;
//	$width = $args['pct'] / 100 * $w; 				// scaled width
	$key = $args['color1'] . ':' . $args['color2'];
	if (!$colors[$key]) {
		$c = new Image_Color();
		$c->setColors($args['color1'], $args['color2']);
		$colors[$key] = $c->getRange(100, $args['degrees']);	// 100 colors, no matter the width
/**
		foreach ($colors[$key] as $col) {
			printf("<div style='color: white; background-color: %s'>%s</div>", $col, $col);
		}
/**/
	}

	$styles = !empty($args['styles']) ? $args['styles'] : '';
	if (!empty($args['width'])) {
		$styles = " width: " . $args['width'] . "px;";
	}
	if (!empty($styles)) $styles = " style='$styles'";

	$out = sprintf("<span %s title='%s'%s><span style='width: %s; background-color: #%s'></span></span>",
		!empty($args['class']) ? "class='" . $args['class'] . "'" : "",
		!empty($args['title']) ? $args['title'] : (int)($args['pct']) . '%',
		$styles,
		(int)($args['pct']) . '%',
		$colors[$key][intval($args['pct']) - 1]
	);
	return $out;
}
// --------------------------------------------------------------------------------------------------------------------
// Returns HTML for a dual percentage bar between 2 percentages. Pure html+css.
function dual_bar($args = array()) {
	global $cms;
	$args += array(
		'pct1'		=> 0,
		'pct2'		=> 0,
		'color1'	=> 'cc0000',
		'color2'	=> '00cc00',
		'title1'	=> null,
		'title2'	=> null,
		'width'		=> null,
		'class'		=> 'dual-bar',
		'styles'	=> '',
	);
	if (!empty($args['width']) and (!is_numeric($args['width']) or $args['width'] < 1)) $args['width'] = 100;
	$w = $args['width'] ? $args['width'] : 100;
//	$width = $args['pct'] / 100 * $w; 				// scaled width

	if (!$args['pct2']) {
		$args['pct2'] = $args['pct1'] ? 100 - $args['pct1'] : 100;
	}

	$styles  = (int)$args['pct2'] ? "background-color: #" . $args['color2'] . "; " : '';
	$styles .= !empty($args['styles']) ? $args['styles'] : '';
	if (!empty($args['width'])) {
		$styles .= " width: " . $args['width'] . "px;";
	}
	if (!empty($styles)) $styles = " style='$styles'";
	// add the 'title' to the end of the styles string for the title of the 2nd (right) bar
	$styles .= " title='" . ($args['title2'] ? $args['title2'] : $args['pct2'].'%') . "'";

	$out = sprintf("<span %s%s>" . 
			"<span class='left'  title='%s' style='width: %s; background-color: #%s'></span>" . 
			"<span class='center'%s></span>" . 
#######			"<span class='right' title='%s' style='width: %s; background-color: #%s'></span>" . 
			"</span>",
		!empty($args['class']) ? "class='" . $args['class'] . "'" : "",
		$styles, 

		!empty($args['title1']) ? $args['title1'] : (int)($args['pct1']) . '%',
		(int)($args['pct1']) . '%',
		$args['color1'],

		(int)($args['pct2']) ? '' : " style='display: none'"

// instead of trying to float a 2nd span for the other percentage, just set the background of the overall div
#		!empty($args['title2']) ? $args['title2'] : (int)($args['pct2']) . '%',
#		(int)($args['pct2']) . '%',
#		$args['color2']
	);
	return $out;
}
// --------------------------------------------------------------------------------------------------------------------
function rank_change($args = array()) {
	global $cms, $ps;
	if (!is_array($args)) $args['plr'] = array( 'plr' => $args );
	$args += array(
		'plr'		=> NULL,
		'rank'		=> 0,
		'prevrank'	=> 0,
		'imgfmt'	=> "rank_%s.png",
		'difffmt'	=> "%d",
		'attr'		=> "",
		'acronym'	=> true,
		'textonly'	=> false,
	);

	$output = "";
	$rank = $prevrank = 0;
	if (is_array($args['plr'])) {
		$rank = $args['plr']['rank'];
		$prevrank = $args['plr']['prevrank'];
	} else {
		$rank = $args['rank'];
		$prevrank = $args['prevrank'];
	}

	$alt = $cms->trans("no change");
	$dir = "same";
	$diff = sprintf($args['difffmt'], $prevrank - $rank);	# note: LESS is better. Opposite of 'skill'.

	if ($prevrank == 0) {
		# no change
	} elseif ($diff > 0) {
		$dir = "up";
		$alt = $cms->trans("Diff") . ": +$diff";
	} elseif ($diff < 0) {
		$dir = "down";
		$alt = $cms->trans("Diff") . ": $diff";
	}

	if ($args['textonly']) {
		$output = sprintf("<span class='rankchange-$dir'>%s%s</span>",
			$diff > 0 ? '+' : '',
			$prevrank == 0 ? '' : $diff
		);
	} else {
		$img = $cms->theme->url() . '/img/icons/' . sprintf($args['imgfmt'], $dir);
		$parent = $cms->theme->is_child();
		if (!file_exists($img) and $parent) {
			$img = $cms->theme->url($parent) . '/img/icons/' . sprintf($args['imgfmt'], $dir);
		}

		$output = sprintf("<img src='%s' alt='%s' title='%s' %s/>", $img, $alt, $alt, $args['attr']);
#		if ($args['acronym']) {
#			$output = "<acronym title='$alt'>$output</acronym>";
#		}
		$output = "<span class='rankchange-$dir'>$output</span>";
	}
	return $output;
}
// --------------------------------------------------------------------------------------------------------------------
function skill_change($args = array()) {
	global $cms, $ps;
	if (!is_array($args)) $args['plr'] = array( 'plr' => $args );
	$args += array(
		'plr'		=> NULL,
		'skill'		=> 0,
		'prevskill'	=> 0,
		'imgfmt'	=> "skill_%s.png",
		'difffmt'	=> "%.02f",
		'attr'		=> "",
		'acronym'	=> true,
		'textonly'	=> false,
	);

	$output = "";
	$skill = $prevskill = 0;
	if (is_array($args['plr'])) {
		$skill = $args['plr']['skill'];
		$prevskill = $args['plr']['prevskill'];
	} else {
		$skill = $args['skill'];
		$prevskill = $args['prevskill'];
	}

	$alt = $cms->trans("no change");
	$dir = "same";
	$diff = sprintf($args['difffmt'], $skill - $prevskill);

	if ($prevskill == 0) {
		# no change
	} elseif ($diff > 0) {
		$dir = "up";
		$alt = $cms->trans("Diff") . ": +$diff";
	} elseif ($diff < 0) {
		$dir = "down";
		$alt = $cms->trans("Diff") . ": $diff";
	}

	if ($args['textonly']) {
		$output = sprintf("<span class='skillchange-$dir'>%s%s</span>",
			$diff > 0 ? '+' : '',
			$prevskill == 0 ? '' : $diff
		);
	} else {
		$img = $cms->theme->url() . '/img/icons/' . sprintf($args['imgfmt'], $dir);
		$parent = $cms->theme->is_child();
		if (!file_exists($img) and $parent) {
			$img = $cms->theme->url($parent) . '/img/icons/' . sprintf($args['imgfmt'], $dir);
		}

		$output = sprintf("<img src='%s' alt='%s' title='%s' %s/>", $img, $alt, $alt, $args['attr']);
#		if ($args['acronym']) {
#			$output = "<acronym title='$alt'>$output</acronym>";
#		}
		$output = "<span class='skillchange-$dir'>$output</span>";
	}
	return $output;
}
// --------------------------------------------------------------------------------------------------------------------
// safer rename function (win/linux compatable)
function rename_file($oldfile,$newfile) {
	// first, try to rename since it's atomic (and faster)
	if (!rename($oldfile,$newfile)) {
		if (copy($oldfile,$newfile)) {		// try to copy file instead
			return unlink($oldfile);	// .. but be sure to remove old file
		}
		return false;
	}
	return true;
}
// --------------------------------------------------------------------------------------------------------------------
// builds an URL 
function url($arg = array()) {
	if (!is_array($arg)) $arg = array( '_base' => $arg );
	$arg += array(					// argument defaults
		'_base'		=> NULL,		// base URL; if NULL $PHP_SELF is used
		'_anchor'	=> '',			// optional anchor
		'_encode'	=> 1,			// should parameters be url encoded?
		'_encodefunc'	=> 'rawurlencode',	// how to encode params
		'_amp'		=> '&amp;',		// param separator
		'_raw'		=> '',			// raw URL appended to final result (is not encoded)
		'_ref'		=> NULL,		// if true/numeric referrer is autoset, if a string it is used instead
		// any other key => value pair is treated as a parameter in the URL
	);
	$base = ($arg['_base'] === NULL) ? ps_escape_html($_SERVER['PHP_SELF']) : $arg['_base'];
	$enc = $arg['_encode'] ? 1 : 0;
	$encodefunc = ($arg['_encodefunc'] && function_exists($arg['_encodefunc'])) ? $arg['_encodefunc'] : 'rawurlencode';
	$i = (strpos($base, '?') === FALSE) ? 0 : 1;

	foreach ($arg as $key => $value) {
		if ($key{0} == '_') continue;		// ignore any param starting with '_'
		$base .= ($i++) ? $arg['_amp'] : '?';
		$base .= "$key=";			// do not encode keys
		$base .= $enc ? $encodefunc($value) : $value;
	}

	if ($arg['_ref']) {
		$base .= ($i++) ? $arg['_amp'] : '?';
		if ($arg['_ref'] and $arg['_ref'] == 1) {
			$base .= 'ref=' . $encodefunc($_SERVER['PHP_SELF'] .
				($_SERVER['QUERY_STRING'] != null ? '?' . $_SERVER['QUERY_STRING'] : '')
			);
		} elseif (!empty($arg['_ref'])) {
			$base .= 'ref=' . $encodefunc($arg['_ref']);
		}
	}

	if ($arg['_raw']) $base .= ($i ? $arg['_amp'] : '?') . $arg['_raw'];
	if ($arg['_anchor']) $base .= '#' . $arg['_anchor'];

	return $base;
}

// --------------------------------------------------------------------------------------------------------------------
function remote_addr($alt='') {
	$ip = $alt;
	if ($_SERVER['HTTP_X_FORWARDED_FOR'])  {
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	} elseif ($_SERVER['REMOTE_ADDR']) {
		$ip = $_SERVER['REMOTE_ADDR'];
	}
	return $ip;
}
// --------------------------------------------------------------------------------------------------------------------
function abort($file, $title, $msg, $redirect='') {
	global $smarty, $data;
	$smarty->assign($data);
	$smarty->assign(array(
		'errortitle'	=> $title,
		'errormsg'	=> $msg,
		'redirect'	=> $redirect,
	));
	$smarty->parse($file);
	ps_showpage($smarty->showpage());
	include('includes/footer.php');
	exit();
}
// --------------------------------------------------------------------------------------------------------------------
// returns a list of all icons available
function load_icons($dir=NULL, $url=NULL) {
	global $ps;
	$list = array();
	if ($dir == NULL) $dir = catfile($ps->conf['theme']['rootimagesdir'], 'icons');
	if ($url == NULL) $url = catfile($ps->conf['theme']['rootimagesurl'], 'icons/');

	if ($dh = @opendir($dir)) {
		while (($file = @readdir($dh)) !== false) {
			if ($file{0} == '.') continue;			// skip dot files
			$fullfile = catfile($dir,$file);
			if (is_dir($fullfile)) continue;		// skip directories
			if (is_link($fullfile)) continue;		// skip symlinks
			$info = getimagesize($fullfile);
			$size = @filesize($fullfile);
			$list[$file] = array(
				'filename'	=> $file,
				'url'		=> catfile($url, $file),
				'desc'		=> sprintf("%s - %dx%d - %s", $file, $info[0], $info[1], abbrnum($size)),
				'size'		=> $size,
				'width'		=> $info[0],
				'height'	=> $info[1],
				'attr'		=> $info[3],
			);
		}
		@closedir($dh);
	}
	ksort($list);
	return $list;
}

// load the basic clan information (no stats). do not confuse this function with 
// get_clan() from the main PS object
function load_clan($id) {
	global $ps;
	$id = $ps->db->escape($id);
	$cmd = "SELECT clan.*,cp.* FROM $ps->t_clan clan, $ps->t_clan_profile cp " . 
		"WHERE clan.clanid='$id' AND clan.clantag=cp.clantag";
	$clan = array();
	$clan = $ps->db->fetch_row(1, $cmd);
	return $clan;
}
function load_clan_members($id) {
	global $ps;
	$id = $ps->db->escape($id);
	# the 'allowrank IN (0,1)' is added so that the query will use the matching index (allorank,clanid)
	$cmd = "SELECT p.plrid,p.uniqueid,pp.name FROM $ps->t_plr p LEFT JOIN $ps->t_plr_profile pp USING(uniqueid) " .
		"WHERE p.allowrank IN (0,1) AND p.clanid='$id' ORDER BY pp.name";
	$list = $ps->db->fetch_rows(1, $cmd);
	return $list;
}
// --------------------------------------------------------------------------------------------------------------------
function load_countries() {
	global $ps;
	$list = array();
	$list = $ps->db->fetch_rows(1, "SELECT cc,cn FROM $ps->t_geoip_cc ORDER BY cc");
	return $list;
}
// --------------------------------------------------------------------------------------------------------------------
// redirects user to a page using $_REQUEST['ref'] if available, or the $alt URL provided (must be ABSOLUTE URL)
function previouspage($alt=NULL) {
	if ($alt==NULL) $alt = 'index.php';
	if ($_REQUEST['ref']) {
//		$ref = (get_magic_quotes_gpc()) ? stripslashes($_REQUEST['ref']) : $_REQUEST['ref'];
		$ref = $_REQUEST['ref'];
		gotopage($ref);				// jump to previous page, if specified
	} else {
		gotopage($alt);
	}
}
// --------------------------------------------------------------------------------------------------------------------
// Always specify an ABSOLUTE URL. Never send a relative URL, as the redirection will not work correctly.
function gotopage($url) {
	global $cms;
//	while (@ob_end_clean()) /* nop */; 		// erase all pending output buffers
	$cms->session->close();
	// if the SID was set from a command line we need to make sure the redirect contains the SID
	if ($cms->session->sid_method() == 'get' and $cms->session->sid()) {
		$query = parse_url($url);
		if (is_array($query)) {
			parse_str($query['query'], $args);
			if (!array_key_exists($cms->session->sid_name(), $args)) {
				$url .= (strpos($url, '?') !== FALSE ? '&' : '?') . $cms->session->sid_name() . "=" . $cms->session->sid();
			}
		}
	}
	if (!headers_sent()) { 				// in case output buffering (OB) isn't supported
		header("Location: " . ps_url_wrapper($url)); 
	} else { 					// Last ditch effort. Try a meta refresh to redirect to new page
		$url = ps_escape_html($url);
		print "<meta http-equiv=\"refresh\" content=\"0;url=$url\">\n"; 
		print "<a href='$url'>Redirect Failed. Please click here to proceed</a>";
	} 
	exit();
}
// --------------------------------------------------------------------------------------------------------------------
// converts all HTML entities from all elements in the array.
function htmlentities_all(&$ary, $trimtags=0) {
	if (!is_array($ary)) {
		$ary = ps_escape_html($ary);
		return;
	}
	reset($ary);
	while (list($key,$val) = each($ary)) {
		$ary[$key] = ps_escape_html($ary[$key]);
	}
}
// --------------------------------------------------------------------------------------------------------------------
// removes any keys in the $set that are the same in the $mainset. useful for removing keys that do not need to be
// changed in a database update
function trimset(&$set, &$mainset) {
	foreach ($set as $key => $value) {
		if (array_key_exists($key, $mainset) and $set[$key] == $mainset[$key]) {
			unset($set[$key]);
		}
	}
}
// --------------------------------------------------------------------------------------------------------------------
// Trims white space from the RIGHT of all strings in the array 
function rtrim_all(&$ary, $trimtags=0) {
	if (!is_array($ary)) {
		$ary = rtrim($ary);
		return;
	}
	reset($ary);
	while (list($key,$val) = each($ary)) {
		if ($trimtags) $ary[$key] = strip_tags($val);
		$ary[$key] = rtrim($ary[$key]);
	}
}
// --------------------------------------------------------------------------------------------------------------------
// Trims white space and HTML/PHP tags from all elements in the array.
function trim_all(&$ary, $trimtags=0) {
	if (!is_array($ary)) {
		if ($trimtags) $ary = strip_tags($ary);
		$ary = trim($ary);
		return;
	}
	reset($ary);
	while (list($key,$val) = each($ary)) {
		if (is_array($ary[$key])) {
			trim_all($ary[$key]);
		} else {
			if ($trimtags) $ary[$key] = strip_tags($val);
			$ary[$key] = trim($ary[$key]);
		}
	}
}
// --------------------------------------------------------------------------------------------------------------------
// removes slashes from all elements in the array.
function stripslashes_all(&$ary) {
	if (!is_array($ary)) {
		$ary = stripslashes($ary);
		return;
	}
	reset($ary);
	while (list($key,$val) = each($ary)) {
		if (is_array($ary[$key])) {
			stripslashes_all($ary[$key]);
		} else {
			$ary[$key] = stripslashes($ary[$key]);
		}
	}
}
// --------------------------------------------------------------------------------------------------------------------
// strips slashes and all tags from the variables in the array
function tidy_all(&$ary, $trimtags=0) {
	trim_all($ary, $trimtags);
	stripslashes_all($ary);
}
// --------------------------------------------------------------------------------------------------------------------
// Globalizes REQUEST variables specified, so we never have to worry about register_globals not being on
function globalize($ary=array()) {
	$items = is_array($ary) ? $ary : array( $ary );
	foreach ($items as $v) {
		$GLOBALS[$v] = isset($_REQUEST[$v]) ? $_REQUEST[$v] : '';
	}
}
// --------------------------------------------------------------------------------------------------------------------
function pagination($args = array()) {
	$args += array(
		'baseurl'		=> '',
		'total'			=> 0,
		'perpage'		=> 100,
		'start'			=> 0,
		'startvar'		=> 'start',
		'pergroup'		=> 3,
		'force_prev_next'	=> false,
		'urltail'		=> '',
		'prefix'		=> '',
		'next'			=> 'Next',
		'prev'			=> 'Previous',
		'separator'		=> ', ',
		'middle_separator'	=> ' ... ',
	);
	$total = ceil($args['total'] / $args['perpage']);		// calculate total pages needed for dataset
	$current = floor($args['start'] / $args['perpage']) + 1;	// what page we're currently on
	if ($total <= 1) return "";					// There's no pages to output, so we output nothing
	if ($args['pergroup'] < 3) $args['pergroup'] = 3;		// pergroup can not be lower than 3
	if ($args['pergroup'] % 2 == 0) $args['pergroup']++;		// pergroup is EVEN, so we add 1 to make it ODD
	$maxlinks = $args['pergroup'] * 3 + 1;
	$halfrange = floor($args['pergroup'] / 2);
	$minrange = $current - $halfrange;				// gives us our current min/max ranges based on $current page
	$maxrange = $current + $halfrange;
	$output = "";

	if ($total > $maxlinks) {
		// create first group of links ...
		$list = array();
		for ($i=1; $i <= $args['pergroup']; $i++) {
			if ($i == $current) {
				$list[] = "<span class='pager-current'>$i</span>";
			} else {
				$list[] = sprintf("<a href='%s' class='pager-goto'>%d</a>", 
					ps_url_wrapper(array('_base' => $args['baseurl'], $args['startvar'] => ($i-1)*$args['perpage'], '_anchor' => $args['urltail'])), 
					$i
				);
			}
		}
		$output .= implode($args['separator'], $list);

		// create middle group of links ...
		if ($maxrange > $args['pergroup']) {
			$output .= ($minrange > $args['pergroup']+1) ? $args['middle_separator'] : $args['separator'];
			$min = ($minrange > $args['pergroup']+1) ? $minrange : $args['pergroup'] + 1;
			$max = ($maxrange < $total - $args['pergroup']) ? $maxrange : $total - $args['pergroup'];

			$list = array();
			for ($i=$min; $i <= $max; $i++) {
				if ($i == $current) {
					$list[] = "<span class='pager-current'>$i</span>";
				} else {
					$list[] = sprintf("<a href='%s' class='pager-goto'>%d</a>", 
						ps_url_wrapper(array('_base' => $args['baseurl'], $args['startvar'] => ($i-1)*$args['perpage'], '_anchor' => $args['urltail'])), 
						$i
					);
				}
			}
			$output .= implode($args['separator'], $list);
			$output .= ($maxrange < $total - $args['pergroup']) ? $args['middle_separator'] : $args['separator'];
		} else {
			$output .= $args['middle_separator'];
		}

		// create last group of links ...
		$list = array();
		for ($i=$total-$args['pergroup']+1; $i <= $total; $i++) {
			if ($i == $current) {
				$list[] = "<span class='pager-current'>$i</span>";
			} else {
				$list[] = sprintf("<a href='%s' class='pager-goto'>%d</a>", 
					ps_url_wrapper(array('_base' => $args['baseurl'], $args['startvar'] => ($i-1)*$args['perpage'], '_anchor' => $args['urltail'])), 
					$i
				);
			}
		}
		$output .= implode($args['separator'], $list);

	} else {
		$list = array();
		for ($i=1; $i <= $total; $i++) {
			if ($i == $current) {
				$list[] = "<span class='pager-current'>$i</span>";
			} else {
				$list[] = sprintf("<a href='%s' class='pager-goto'>%d</a>", 
					ps_url_wrapper(array('_base' => $args['baseurl'], $args['startvar'] => ($i-1)*$args['perpage'], '_anchor' => $args['urltail'])), 
					$i
				);
			}
		}
		$output .= implode($args['separator'], $list);
	}

	// create 'Prev/Next' links
	if (($args['force_prev_next'] and $total) or $current > 1) {
		if ($current > 1) {
			$output = sprintf("<a href='%s' class='pager-prev'>%s</a> ", 
				ps_url_wrapper(array('_base' => $args['baseurl'], $args['startvar'] => ($current-2)*$args['perpage'], '_anchor' => $args['urltail'])), 
				$args['prev']
			) . $output;
		} else {
			$output = "<span class='pager-prev'>" . $args['prev'] . "</span> " . $output;
		}
	}
	if (($args['force_prev_next'] and $total) or $current < $total) {
		if ($current < $total) {
			$output .= sprintf(" <a href='%s' class='pager-next'>%s</a> ", 
				ps_url_wrapper(array('_base' => $args['baseurl'], $args['startvar'] => $current*$args['perpage'], '_anchor' => $args['urltail'])), 
				$args['next']
			);
		} else {
			$output .= " <span class='pager-next'>" . $args['next'] . "</span>";
		}
	}

	if ($args['prefix'] != '' and !empty($output)) {
		$output = $args['prefix'] . $output;
	}

	return "<span class='pager'>$output</span>";
}
// ----------------------------------------------------------------------------------------------------------------------------
// PS2.0 :: PHP version of the sub loadConfig() (doesn't have all features of original function, but is good enough here)
function loadConfig($args=array()) {
  if (!is_array($args)) {
    $f = $args;
    $args = array();
    $args['filename'] = $f;
  }
  if (!isset($args['filename'])) return 0;
  if (!isset($args['oldconf'])) $args['oldconf'] = array();
  if (!isset($args['fatal'])) $args['fatal'] = 1;
  if (!isset($args['commentstr'])) $args['commentstr'] = '#';
  if (!isset($args['idx'])) $args['idx'] = 0;
  if (!isset($args['section'])) $args['global'] = '';
  if (!isset($args['ignorequotes'])) $args['ignorequotes'] = 0;
  if (!isset($args['preservecase'])) $args['preservecase'] = 0;
  if (!isset($args['sectionname'])) $args['sectionname'] = 'SECTION';
  if (!isset($args['fileblock'])) $args['fileblock'] = '';	# what block to read from file ('' or false = read everything)
  $args['section'] = strtolower($args['section']);

  $newconf = $oldconf;
  $blockend = array('{' => '}', '[' => ']');
  $confptr = &$newconf;
  $file = @fopen($args['filename'], 'r', 1);
  if (!$file) return 0;
  $fileblock = ''; 

  while ($line = fgets($file, 4096)) {
    $line = trim($line);                                        			# remove front/ending whitespace
    if (preg_match("/^". preg_quote($args['commentstr']) ."/", $line)) continue;	# skip comments
    if ($line == '') continue;								# skip blank lines
    if (!preg_match('/^\*+|\[?\s*\S+\s*(\*+|>|\]|=|:|\{|\[)/', $line, $m)) continue;	# skip invalid lines, and match $var=$val

    if ($args['fileblock']) {				# if we only want a block, check for it here
      if (preg_match('/^\*+\s*([^\*]+?)\s*\*+/',$line, $m)) {
        $fileblock = $m[1];
      }
    }
    if ($args['fileblock'] and ($args['fileblock'] != $fileblock)) continue;		# wrong fileblock, ignore it

    if (preg_match('/^\[\s*(.+)\s*\]/', $line, $m)) {					# [SECTION] header
      $args['section'] = strtolower($m[1]);
      // create section if needed and create reference to new hash section, taking care of 'global'
      if ($args['section'] != 'global') {
        // keep order of sections as read from file
        if (!$newconf[$args['section']]) { 
          $newconf[$args['section']] = array();
          $newconf[$args['section']]['IDX'] = ++$args['idx'];
        }
        $confptr = &$newconf[$args['section']];
      } else {
        $confptr = &$newconf;
      }
      $confptr[ $args['sectionname'] ] = $m[1];				# preserve the section header case

    } elseif (preg_match('/^\s*(\S+?)\s*=\s*(.*)/', $line, $m)) {			# VAR = VALUE
      list($x, $var, $val) = $m;
      $var = trim($var);
      $var = $args['preservecase'] ? $var : strtolower($var);				# lowercase variable
      $val = preg_replace("/" . preg_quote($args['commentstr']) . ".*/",'',$val);	# ignore comments
      $val = trim($val);

      if ($var == '$comments') {                                  		# change the comment char(s)
        $args['commentstr'] = $val;
        continue;
      }
      
      if (!$args['ignorequotes'] and preg_match('/^"(.*)"$/', $val, $m)) {		# remove the quotes, if present
        $val = $m[1];
      }

      if (preg_match('/^([\w\d]+)\.([\w\d]+)/', $var, $m)) {				# dot notation to specify a different SECTION
        if (strtolower($m[1]) != 'global') {						# IGNORE 'global' sections
          _assignvar($newconf[ $m[1] ], $m[2], $val, $args['noarrays']);		# NOTE: use %newconf and not $confptr !
        } else {
          _assignvar($newconf, $m[2], $val, $args['noarrays']);
        }
      } else {									# normal variable
        _assignvar($confptr, $var, $val, $args['noarrays']);
      }

    } elseif (preg_match('/^\s*(\S+?)\s*>+\s*([\.\w\d]+)/', $line, $m)) {	# VAR >> EOL
      list($x, $var, $val) = $m;
      $token = $val;
      $val = '';
      while ($line = fgets($file, 4096)) {
        $line = trim($line);
        if ($line == $token) break;
        $val .= $line . "\n";
      }
      $val = trim($val);

      if (preg_match('/^([\w\d]+)\.([\w\d]+)/', $var, $m)) {				# dot notation to specify a different SECTION
        if (strtolower($m[1]) != 'global') {						# IGNORE 'global' sections
          _assignvar($newconf[ $m[1] ], $m[2], $val, $args['noarrays']);		# NOTE: use %newconf and not $confptr !
        } else {
          _assignvar($newconf, $m[2], $val, $args['noarrays']);
        }
      } else {									# normal variable
        _assignvar($confptr, $var, $val, $args['noarrays']);
      }

    } elseif (preg_match('/^\s*(\S+?)\s*([{\[])\s*(.*)/', $line, $m)) {		# -- VAR {[ VALUE (multi-line) ]} --
      list($x, $var, $begin, $val) = $m;
      $end = $blockend[$begin];
      $var = $args['preservecase'] ? $var : strtolower($var);
      $confptr[$var] = '';
      
      $begintotal = 1;
      if (preg_match('/^(.*)(\Q$end\E\s*)/', $val, $m)) {		# var { $1 } ($2 = $end; line doesn't have to exist)
        if (isset($m[1])) $confptr[$var] = $m[1];
        if (isset($m[2])) {
          $val = $end;
          $begintotal = 0;
        } else {
          $confptr[$var] .= "\n";
        }
      }
      while ((($val != $end) or ($begintotal > 0)) and !feof($file)) { 
        $val = fgetc($file);
        if ($val == $end) $begintotal--;
        if ($val == $begin) $begintotal++;
        if (($val != $end) or ($begintotal > 0)) $confptr[$var] .= $val;
      }
      $confptr[$var] = trim($confptr[$var]);

      if ($begin.$end == '{}') {			# PERL CODE block { ... } needs to be run
        $code = $confptr[$var];
	# for obvious reasons, we ignore this step
      }

    } elseif (preg_match('/^\s*(\S+?)\s*:\s*(.*)/', $line, $m)) {		# INCLUDE: filename
      if ((strtolower($m[1]) != 'include') or empty($m[2])) continue;
      $inc = $m[2];
      $newargs = $args;
      $newargs['filename'] = $inc;
      $incconf = loadconfig2($newargs);
      $newconf = array_merge($newconf, $incconf);
    }

  } // end of while !eof $file ...
  return $newconf;
}
# ---------
# internal function for loadconfig(). Assigns a value to the 'var'. Automatically converts var into an array if required
function _assignvar(&$conf, $var, $val, $noary) {
  if (!$noary and isset($conf[$var])) {
    if (!is_array($conf[$var])) {
      $old = $conf[$var];
      $conf[$var] = array( $old );                         # convert scalar into an array with its original value
    }
    $conf[$var][] = $val;				# add new value to the array
  } else {
    $conf[$var] = $val;                               # single value, so we keep it as a scalar
  }
  return 1;
}
// --------------------------------------------------------------------------------------------------------------------
// Loads the config for the theme and caches it. Cached file is loaded if config hasn't changed
function loadCachedConfig($args=array(), $cache_dir='', $file_id='') {
  if (!is_array($args)) {       
    $f = $args;
    $args = array();
    $args['filename'] = $f;
  }
  if (!isset($args['filename'])) return 0;

  $origtime = @filemtime($args['filename']);
  $cachefile = $cache_dir . DIRECTORY_SEPARATOR . $file_id . "^$origtime^" .  basename($args['filename']) . ".php";
  $unlinkfile = $file_id . "\\^\\d+\\^" .  preg_quote(basename($args['filename']) . ".php");
 
  if (file_exists($cachefile)) {
    $code = implode('', file($cachefile));
    $conf = unserialize($code);
    return $conf;

  } else {					// Save loaded config to cache file
    $d = @opendir(dirname($cachefile));		// first delete any older cache files for the config
    if ($d) {
      while (($f = @readdir($d)) !== FALSE) {
        if (preg_match("/^$unlinkfile\$/", $f)) @unlink($cache_dir . DIRECTORY_SEPARATOR . $f);
      }
      @closedir($d);
    }
    $conf = loadConfig($args);
    $code = serialize($conf);
    $f = @fopen($cachefile, "w");
    if ($f) {
      @fwrite($f, $code);
      @fclose($f);
    } else {
      // report error here ... but we don't want to do that right now. If it doesn't save the cached file, owell!
    }
  }

  return $conf;
}
// --------------------------------------------------------------------------------------------------------------------
function commify($num) {
	return number_format($num);
}
// ------------------------------------------------------------------------------------------------------------------- 
//
function abbrnum($num, $tail=2, $size = null, $base = 1024) {
	if ($size === null) {
		$size = array(' bytes',' KB',' MB',' GB',' TB');
	}
	if (!is_numeric($tail)) $tail = 2;
	if (!$num) return '0' . $size[0];

	$i = 0;
	while (($num >= $base) and ($i < count($size))) {
		$num /= $base;
		$i++;
	}

	return sprintf("%." . $tail . "f",$num) . $size[$i];
}

// shortcut for callback functions
function abbrnum0($string, $tail = 0) {
	if (intval($string) < 1000) {
		return $string;
	} else {
		return abbrnum($string, $tail, array('', 'K', 'M', 'B'), 1000);
	}
}
// --------------------------------------------------------------------------------------------------------------------
function compacttime($seconds, $format="hh:mm:ss") {
  $d = $h = $m = $s = "00";
  if (!isset($seconds)) $seconds = 0;
  $old = $seconds;
  $str = $format;
  if ( (strpos($str, 'dd') !== FALSE) && ($seconds / (60*60*24)) >= 1) 	{ $d = sprintf("%d", $seconds / (60*60*24)); $seconds -= $d * (60*60*24); }
  if ( (strpos($str, 'hh') !== FALSE) && ($seconds / (60*60)) >= 1) 	{ $h = sprintf("%d", $seconds / (60*60)); $seconds -= $h * (60*60); }
  if ( (strpos($str, 'mm') !== FALSE) && ($seconds / 60) >= 1) 		{ $m = sprintf("%d", $seconds / 60); $seconds -= $m * (60); }
  if ( (strpos($str, 'ss') !== FALSE) && ($seconds % 60) >= 1) 		{ $s = sprintf("%d", $seconds % 60); }
  $str = str_replace('dd', sprintf('%02d',$d), $str);
  $str = str_replace('hh', sprintf('%02d',$h), $str);
  $str = str_replace('mm', sprintf('%02d',$m), $str);
  $str = str_replace('ss', sprintf('%02d',$s), $str);
  return $str;
}
// --------------------------------------------------------------------------------------------------------------------
// Concatenate file path parts together always using / as the directory separator.
// since '/' is always used this can be used on URL's as well.
function catfile() {
  $args = func_get_args();
  $args = str_replace(array('\\\\','\\'), '/', $args);
  $path = array_shift($args);
  foreach ($args as $part) {
    if (substr($path, -1, 1) == '/') $path = substr($path, 0, -1);
    if ($part != '' and $part{0} != '/') $part = '/' . $part;
    $path .= $part;
  }
  // remove the trailing slash if it's present
  if (substr($path, -1, 1) == '/') $path = substr($path, 0, -1);
  return $path;
}
// --------------------------------------------------------------------------------------------------------------------
// returns a CSV line of text with the elements of the $data array
function csv($data,$del=',',$enc='"') {
	$csv = '';
	foreach ($data as $element) {
		$element = str_replace($enc, "$enc$enc", $element);
  		if ($csv != '') $csv .= $del;
		$csv .= $enc . $element . $enc;
	}
	return "$csv\n";
}

//if (!function_exists('sys_get_temp_dir')) {	// PHP4 doesn't have this function (added in 5.2.1)
	// if a temp directory can not be found a blank string is returned instead.
	function get_temp_dir() {
		// Search environment and system variables for path
		if (!empty($_ENV['TMP'])) {
			return realpath($_ENV['TMP']);
		} elseif (!empty($_ENV['TMPDIR'])) {
			return realpath($_ENV['TMPDIR']);
		} elseif (!empty($_ENV['TEMP'])) {
			return realpath($_ENV['TEMP']);
		} elseif (!empty($_SERVER['TEMP'])) {
			return realpath($_SERVER['TEMP']);
		} elseif (!empty($_SERVER['TMP'])) {
			return realpath($_SERVER['TMP']);
		} else { 
			// Make a temp file using the built in routines and discover where it was written to.
			// creating a file is slow (relatively), so its best to cache the return of this function just in case.
			$temp_file = tempnam(md5(uniqid(rand(), true)), '');
			if ($temp_file) {
				$temp_dir = realpath(dirname($temp_file));
				unlink($temp_file);
				return $temp_dir;
			} else {
				return '';
			}
		}
	}
//}
// --------------------------------------------------------------------------------------------------------------------
function ymd2time($date, $char='-') {
	list($y,$m,$d) = split($char, $date);
	return mktime(0,0,0,$m,$d,$y);
}
function time2ymd($time, $char='-') {
	return date(implode($char, array('Y','m','d')), $time);
}
// --------------------------------------------------------------------------------------------------------------------
function array_map_recursive($function, $data) {
	if (is_array($data)) {
		foreach ($data as $i => $item) {
			$data[$i] = is_array($item)
				? array_map_recursive($function, $item)
				: $function($item);
		}
	}
	return $data;
}
// --------------------------------------------------------------------------------------------------------------------
// Inserts $arr2 after the $key (string). if $before is true then it's inserted before the $key specified.
// I do not know why PHP doesn't have this built in already. It can be very useful. (array_splice works on numeric indexes only)
function array_insert($arr1, $key, $arr2, $before = false) {
	$index = array_search($key, array_keys($arr1));
	if ($index === false){
		$index = count($arr1); // insert at end of array if $key not found
	} else {
		if (!$before) $index++;
	}
	$end = array_splice($arr1, $index);
	return array_merge($arr1, $arr2, $end);
}
// --------------------------------------------------------------------------------------------------------------------
// joins a single key value from an array into a string using the glue
function key_join($glue, $pieces, $key) {
	if (!is_array($pieces)) return '';
	$str = '';
	foreach ($pieces as $p) {
		$str .= $p[$key] . $glue;
	}
	return substr($str, 0, -strlen($glue));
}
// --------------------------------------------------------------------------------------------------------------------
// very simple recursive array2xml routine
function array2xml($data, $key_prefix = 'key_', $depth = 0) {
	if (!is_array($data)) return '';
	$xml = (!$depth) ? "<?xml version=\"1.0\" ?>\n<data>\n" : "";
	foreach ($data as $key => $val) {
		$pad = str_repeat("\t", $depth+1);
		if (is_numeric(substr($key,0,1))) $key = "$key_prefix$key";	// is first char numeric?
		$key = str_replace(':', '_', $key);
		if (is_array($val)) {
			$xml .= "$pad<$key>\n";
			$xml .= array2xml($val, $key_prefix, $depth+1);
			$xml .= "$pad</$key>\n";
		} else {
			$xml .= "$pad<$key>";
			$xml .= htmlspecialchars($val, ENT_QUOTES);
			$xml .= "</$key>\n";
		}
	}
	if (!$depth) $xml .= "</data>\n";
	return $xml;
}

// --------------------------------------------------------------------------------------------------------------------
// dumps all output buffers, sends a content-type, prints the xml
function print_xml($data, $clear_ob = true, $send_ct = true, $do_exit = true) {
	if ($clear_ob) while (@ob_end_clean());
	if ($send_ct) @header("Content-Type: text/xml; charset=utf-8");
#	print XML_serialize($data);
	print array2xml($data);
	if ($do_exit) exit();
}
// --------------------------------------------------------------------------------------------------------------------
// returns a gradient array between 2 numbers
function gradient($low, $high, $totalsteps) {
	$steps = $totalsteps - 1;
	$dist = $high - $low;
	$inc = $dist / $steps;
	$value = $low;
	$ary = array();
	$ary[] = $low;
	for ($i=1; $i < $steps; $i++) {
		$value += $inc;
		$ary[] = $value;
	}
	$ary[] = $high;
	return $ary;
}
// --------------------------------------------------------------------------------------------------------------------
// returns the RGB gradient between 2 RGB pairs
function rgbGradient($low, $high, $totalsteps) {
	$r1 = $low >> 16;
	$g1 = ($low & 0x00FF00) >> 8;
	$b1 = $low & 0x0000FF;
	$r2 = $high >> 16;
	$g2 = ($high & 0x00FF00) >> 8;
	$b2 = $high & 0x0000FF;
	$r = gradient($r1, $r2, $totalsteps);
	$g = gradient($g1, $g2, $totalsteps);
	$b = gradient($b1, $b2, $totalsteps);
	$ary = array();
	for ($i=0; $i < count($r); $i++) {
		$ary[] = ($r[$i] << 16) | ($g[$i] << 8) | $b[$i];
	}
	return $ary;
}

// returns a list of $key values from the array of arrays
function array_values_by_key(&$ary, $key) {
	$list = array();
	if (is_array($ary) and count($ary)) {
		foreach ($ary as $a) {
			$list[] = $a[$key];
		}
	}
	return $list;
}

?>
