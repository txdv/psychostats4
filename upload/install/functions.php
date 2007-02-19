<?php
	/*
		functions.php

		General utility functions
	*/

if (defined("FILE_FUNCTIONS_PHP")) return 1; 
define("FILE_FUNCTIONS_PHP", 1); 

if (!defined("VALID_PAGE")) die("Access Denied!");

define("ACL_NONE", -1);
define("ACL_USER", 1);
define("ACL_CLANADMIN", 5);
define("ACL_ADMIN", 99);

// --------------------------------------------------------------------------------------------------------------------
function url($arg = array()) {
	if (!is_array($arg)) $arg = array( '_base' => $arg );
	$arg += array(					// argument defaults
		'_base'		=> NULL,		// base URL; if NULL $PHP_SELF is used
		'_anchor'	=> '',			// optional anchor
		'_encode'	=> 1,			// should parameters be url encoded?
		'_encodefunc'	=> 'rawurlencode',	// who to encode params
		'_amp'		=> '&amp;',		// param separator
		'_raw'		=> '',			// raw URL appended to final result (is not encoded)
		// any other key => value pair is treated as a parameter in the URL
	);
	$base = ($arg['_base'] === NULL) ? $_SERVER['PHP_SELF'] : $arg['_base'];
	$enc = $arg['_encode'] ? 1 : 0;
	$encodefunc = ($arg['_encodefunc'] && function_exists($arg['_encodefunc'])) ? $arg['_encodefunc'] : 'rawurlencode';
	$i = (strpos($base, '?') === FALSE) ? 0 : 1;
	foreach ($arg as $key => $value) {
		if ($key{0} == '_') continue;		// ignore any param starting with '_'
		$base .= ($i++) ? $arg['_amp'] : '?';
		$base .= "$key=";			// do not encode keys
		$base .= $enc ? $encodefunc($value) : $value;
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
	$smarty->showpage();
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
	$cmd = "SELECT clan.*,cp.* FROM $ps->t_clan clan, $ps->t_clan_profile cp WHERE clan.clanid='$id' AND clan.clantag=cp.clantag";
	$clan = array();
	$clan = $ps->db->fetch_row(1, $cmd);
	return $clan;
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
//    $ref = (get_magic_quotes_gpc()) ? stripslashes($_REQUEST['ref']) : $_REQUEST['ref'];
    $ref = $_REQUEST['ref'];
    gotopage($ref);				// jump to previous page, if specified
  } else {
    gotopage($alt);
  }
}
// --------------------------------------------------------------------------------------------------------------------
// Always specify an ABSOLUTE URL. Never send a relative URL, as the redirection will not work correctly.
function gotopage($url) {
//	while (@ob_end_clean()) /* nop */; 		// erase all pending output buffers
//	session_close();				// user_handler_* function
	if (!headers_sent()) { 				// in case output buffering (OB) isn't supported
		header("Location: " . ps_url_wrapper($url)); 
	} else { 					// Last ditch effort. Try a meta refresh to redirect to new page
		print "<meta http-equiv=\"refresh\" content=\"0;url=$url\">\n"; 
	} 
	exit();
}
// --------------------------------------------------------------------------------------------------------------------
// converts all HTML entities from all elements in the array.
function htmlentities_all(&$ary, $trimtags=0) {
  if (!is_array($ary)) {
    $ary = htmlentities($ary);
    return;
  }
  reset($ary);
  while (list($key,$val) = each($ary)) {
    $ary[$key] = htmlentities($ary[$key]);
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
// strips non-valid html tags from the string given.
// will also remove certain keywords like 'onmouseover', 'onclick', etc...
function ps_strip_tags($html) {
  global $conf;
  $allowed = '<' . str_replace(',', '><', preg_replace('/\\s+/m', ',', $conf['allowedhtmltags'])) . '>';
  while($html != strip_tags($html, $allowed)) {
    $html = strip_tags($html, $allowed);
  }
  return preg_replace('/<(.*?)>/ie', "'<' . ps_strip_attribs('\\1') . '>'", $html);
}
function ps_strip_attribs($html) {
  $attribs = 'javascript|on(?:dbl)?click|onmouse(?:\w+)|onkey(?:\w+)';
  return stripslashes(preg_replace("/($attribs)(?!_disabled)/i", '\\1_disabled', $html));
// for some reason my assertion above for '_disabled' is not working and it ends up being appended each time through the loop.
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
function pager($url, $numitems, $perpage, $start, $pergroup=3, $urltail='', $force_prev_next=0, $next='Next', $prev='Previous', $class='') {
  $total = ceil($numitems / $perpage);			// calculate total pages needed for dataset
  $current = floor($start / $perpage) + 1;		// what page we're currently on
  if ($total == 1) return "";				// There's no pages to output, so we output nothing
  if ($pergroup < 3) $pergroup = 3;			// pergroup can not be lower than 3
  if ($pergroup % 2 == 0) $pergroup++;			// pergroup is EVEN, so we add 1 to make it ODD
  $maxlinks = $pergroup * 3 + 1;
  $halfrange = floor($pergroup/2);
  $minrange = $current - $halfrange;			// gives us our current min/max ranges based on $current page
  $maxrange = $current + $halfrange;
  if ($class) $class=" class='$class'";
//  if (defined("PSYCHONUKE")) psychonuke_url($url);

  $output = "";

  if ($total > $maxlinks) {

    // create first group of links ...
    $list = array();
    for ($i=1; $i <= $pergroup; $i++) {
      if ($i == $current) {
        $list[] = sprintf("<b>%s</b>", $i);
      } else {
//        $list[] = sprintf("<a href='%s'$class>%d</a>", "$url&start=" . (($i-1)*$perpage) . $urltail, $i);
          $list[] = sprintf("<a href='%s'$class>%d</a>", url(array('_base' => $url, 'start' => ($i-1)*$perpage, '_anchor' => $urltail)), $i);
      }
    }
    $output .= implode(', ', $list);

    // create middle group of links ...
    if ($maxrange > $pergroup) {
      $output .= ($minrange > $pergroup+1) ? ' ... ' : ', ';
      $min = ($minrange > $pergroup+1) ? $minrange : $pergroup + $half + 1;
      $max = ($maxrange < $total - $pergroup) ? $maxrange : $total - $pergroup;

      $list = array();
      for ($i=$min; $i <= $max; $i++) {
        if ($i == $current) {
          $list[] = sprintf("<b>%s</b>", $i);
        } else {
//        $list[] = sprintf("<a href='%s'$class>%d</a>", "$url&start=" . (($i-1)*$perpage) . $urltail, $i);
          $list[] = sprintf("<a href='%s'$class>%d</a>", url(array('_base' => $url, 'start' => ($i-1)*$perpage, '_anchor' => $urltail)), $i);
        }
      }
      $output .= implode(', ', $list);
      $output .= ($maxrange < $total - $pergroup) ? ' ... ' : ', ';
    } else {
      $output .= ' ... ';
    }

    // create last group of links ...
    $list = array();
    for ($i=$total-$pergroup+1; $i <= $total; $i++) {
      if ($i == $current) {
        $list[] = sprintf("<b>%s</b>", $i);
      } else {
//      $list[] = sprintf("<a href='%s'$class>%d</a>", "$url&start=" . (($i-1)*$perpage) . $urltail, $i);
        $list[] = sprintf("<a href='%s'$class>%d</a>", url(array('_base' => $url, 'start' => ($i-1)*$perpage, '_anchor' => $urltail)), $i);
      }
    }
    $output .= implode(', ', $list);

  } else {
    $list = array();
    for ($i=1; $i <= $total; $i++) {
      if ($i == $current) {
        $list[] = sprintf("<b>%s</b>", $i);
      } else {
//      $list[] = sprintf("<a href='%s'$class>%d</a>", "$url&start=" . (($i-1)*$perpage) . $urltail, $i);
        $list[] = sprintf("<a href='%s'$class>%d</a>", url(array('_base' => $url, 'start' => ($i-1)*$perpage, '_anchor' => $urltail)), $i);
      }
    }
    $output .= implode(', ', $list);
  }

  // create 'Prev/Next' links
  if ($force_prev_next or $current > 1) {
    if ($current > 1) {
//      $output = sprintf("<a href='%s'$class>$prev</a> ", "$url&start=" . ($current-2)*$perpage . $urltail) . $output;
      $output = sprintf("<a href='%s'$class>$prev</a> ", url(array('_base' => $url, 'start' => ($current-2)*$perpage, '_anchor' => $urltail))) . $output;
    } else {
      $output = "$prev $output";
    }
  }
  if ($force_prev_next or $current < $total) {
    if ($current < $total) {
//      $output .= sprintf(" <a href='%s'$class>$next</a> ", "$url&start=" . ($current*$perpage) . $urltail);
      $output .= sprintf(" <a href='%s'$class>$next</a> ", url(array('_base' => $url, 'start' => $current*$perpage, '_anchor' => $urltail)));
    } else {
      $output .= " $next";
    }
  }

  return $output;
}
// ----------------------------------------------------------------------------------------------------------------------------
// Interface function for pager() ... simply allows for an array of paramaters to be passed.
// Adds a couple extra paramaters as well (startvar and prefix)
function pagination($args=array()) {
  $args += array(
	'baseurl'		=> '',
	'total'			=> 0,
	'perpage'		=> 100,
	'start'			=> 0,
	'startvar'		=> '',
	'pergroup'		=> 3,
	'force_prev_next'	=> 0,
	'urltail'		=> '',
	'prefix'		=> '',
	'next'			=> 'Next',
	'prev'			=> 'Previous',
	'class'			=> '',
  );
  $output = pager(
	$args['baseurl'], 
	$args['total'], 
	$args['perpage'], 
	$args['start'], 
	$args['pergroup'], 
	$args['urltail'],
	$args['force_prev_next'],
	$args['next'],
	$args['prev'],
	$args['class']
  );

  if ($args['startvar'] != '' and $args['startvar'] != 'start') $output = str_replace('start=', $args['startvar'] . '=', $output);
  if ($args['prefix'] != '' and !empty($output)) $output = $args['prefix'] . $output;

  return $output;
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
function abbrnum($num, $tail=2) {
  $size = array(' bytes',' KB',' MB',' GB',' TB');
  if (!is_numeric($tail)) $tail = 2;
  $i = 0;

  if (!$num) return '0' . $size[0];
  while (($num >= 1024) and ($i < 2)) {        
    $num /= 1024;      
    $i++;
  }
  return sprintf("%." . $tail . "f",$num) . $size[$i];
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
// Concatinate file path parts together always using / as the directory separator
function catfile() {
  $args = func_get_args();
  $args = str_replace(array('\\\\','\\'), '/', $args);
//  $path = str_replace(array('\\\\','\\'), '/', array_shift($args));
  $path = array_shift($args);
  foreach ($args as $part) {
    if (substr($path, -1, 1) == '/') $path = substr($path, 0, -1);
    if ($part{0} != '/') $part = '/' . $part;
    $path .= $part;
  }
  // remove the trailing slash if it's present
  if (substr($path, -1, 1) == '/') $path = substr($path, 0, -1);
  return $path;
}
// --------------------------------------------------------------------------------------------------------------------
// Concatinate file path parts together using the proper DIRECTORY_SEPARATOR
function old_catfile() {
  $args = func_get_args();
  $path = array_shift($args);
  foreach ($args as $part) {
    if (substr($path, -1, 1) == DIRECTORY_SEPARATOR) $path = substr($path, 0, -1);
    if ($part{0} != DIRECTORY_SEPARATOR) $part = DIRECTORY_SEPARATOR . $part;
    $path .= $part;
  }
  // remove the trailing slash if it's present
  if (substr($path, -1, 1) == DIRECTORY_SEPARATOR) $path = substr($path, 0, -1);
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
// --------------------------------------------------------------------------------------------------------------------
// returns the temp directory for the system or the path to the current script directory if unknown
function tmppath($suffix='') {
	$path = '';
	if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
		if (!empty($_SERVER['TEMP'])) {
			$path = $_SERVER['TEMP'];
		} elseif (!empty($_SERVER['TMP'])) {
			$path = $_SERVER['TMP'];
		} elseif (!empty($_ENV['TEMP'])) {
			$path = $_ENV['TEMP'];
		} elseif (!empty($_ENV['TMP'])) {
			$path = $_ENV['TMP'];
		} else {
			$path = dirname($_SERVER["SCRIPT_FILENAME"]);
		}
	} else {
		$path = "/tmp";
	}
	return catfile($path, $suffix);
}
// --------------------------------------------------------------------------------------------------------------------
function ymd2time($date, $char='-') {
	list($y,$m,$d) = split($char, $date);
	return mktime(0,0,0,$m,$d,$y);
}
function time2ymd($time, $char='-') {
	return date(implode($char, array('Y','m','d')), $time);
}
?>
