<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {mapimg} function plugin
 *
 * Type:     function<br>
 * Name:     mapimg<br>
 * Purpose:  returns the <img> tag for the map name, 
		or just a text string of the map name/desc if the image file doesn't exist
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_mapimg($args, &$smarty)
{
	global $ps;
	$args += array(
		'var'		=> '',
		'map'		=> array(),	// $map array (or map name string)
		'alt'		=> NULL,
		'height'	=> NULL,
		'width'		=> NULL,
		'path'		=> NULL,		// add $path to the end of basedir? (eg: 'large')
		'noimg'		=> NULL,

		'align'		=> '',
		'valign'	=> '',
		'style'		=> '',
		'id'		=> '',

		'pq'		=> NULL,
	);
	$gametype = $args['pq'] ? $args['pq']->gametype() : $ps->conf['main']['gametype'];
	$modtype = $args['pq'] ? $args['pq']->modtype() : $ps->conf['main']['modtype'];
	$basedir = catfile($ps->conf['theme']['rootmapsdir'], $gametype, $modtype);
	$baseurl = catfile($ps->conf['theme']['rootmapsurl'], $gametype, $modtype);

	$m = $args['map'];
	if (!is_array($m)) $m = array( 'uniqueid' => !empty($m) ? $m : 'unknown' );
	$name = !empty($m['uniqueid']) ? $m['uniqueid'] : 'unknown';
	$alt = ($args['alt'] !== NULL) ? $args['alt'] : $m['name'];
	$label = !empty($alt) ? $alt : $name;
	$ext = explode(',', $ps->conf['theme']['images']['search_ext']);

	$file = "";
	foreach (array( $name, 'noimage' ) as $n) {
		$i = 2;			// only check 2 levels of directories
		$path = $basedir;
		$urlpath = $baseurl;
		while ($i-- > 0) {
			foreach ($ext as $e) {
				if ($e{0} == '.') $e = substr($e,1);		// remove '.' from extension
				$file = catfile($path,$args['path'],$n) . '.' . $e;
				$url  = catfile($urlpath,$args['path'],$n) . '.' . $e;
//				syslog(LOG_INFO, "PHP: $file: " . (int)file_exists($file));
				if (file_exists($file)) break;
				$file = "";
			}
			if ($file) break;
			$path = dirname($path);
			$urlpath = dirname($urlpath);
		}
		if ($file) break;
	}
#	print "f: $file<BR>\n";
#	print "u: $url<BR>\n";

	// if $file is !empty then it exists
	if ($file) {
		$attrs = " ";
		if (is_numeric($args['width'])) $attrs .= "width='" . $args['width'] . "' ";
		if (is_numeric($args['height'])) $attrs .= "height='" . $args['height'] . "' ";
		if (!empty($args['align'])) $attrs .= "align='" . $args['align'] . "' ";
		if (!empty($args['valign'])) $attrs .= "valign='" . $args['valign'] . "' ";
		if (!empty($args['style'])) $attrs .= "style='" . $args['style'] . "' ";
		if (!empty($args['id'])) $attrs .= "id='" . $args['id'] . "' ";
		$attrs = substr($attrs, 0, -1);		// remove trailing space
		$img = "<img src='$url' title='$label' alt='$alt' border='0' $attrs />";
	} else {
		$img = $args['noimg'] !== NULL ? $args['noimg'] : $label;
	}

/*
  $img = '*unknown*';
  if (!empty($args['name'])) {
    $gametype = $args['pq'] ? $args['pq']->gametype() : $ps->conf['main']['gametype'];
    $modtype = $args['pq'] ? $args['pq']->modtype() : $ps->conf['main']['modtype'];
    $file = catfile($ps->conf['theme']['rootmapsdir'], $gametype, $modtype, $args['path'], $args['name']);
    $url = $ps->conf['theme']['rootmapsurl'] . "$gametype/$modtype/" . 
	($args['path'] ? $args['path'] : '') . "/" . $args['name'];
    $label = !empty($args['desc']) ? $args['desc'] : $args['name'];
    $label = preg_replace('/\.\w+$/', '', $label);						// remove trailing extension
    if (!@file_exists($file)) {
      $args['name'] = 'noimage.jpg';
      $file = catfile($ps->conf['theme']['rootmapsdir'], $gametype, $modtype, $args['path'], $args['name']);
      $url = $ps->conf['theme']['rootmapsurl'] . "$gametype/$modtype/" . 
	($args['path'] ? $args['path'] : '') . "/" . $args['name'];
    }

    if (@file_exists($file)) {
      $attrs = " ";
      if (is_numeric($args['width'])) $attrs .= "width='" . $args['width'] . "' ";
      if (is_numeric($args['height'])) $attrs .= "height='" . $args['height'] . "' ";
      if (!empty($args['align'])) $attrs .= "align='" . $args['align'] . "' ";
      if (!empty($args['valign'])) $attrs .= "valign='" . $args['valign'] . "' ";
      if (!empty($args['style'])) $attrs .= "style='" . $args['style'] . "' ";
      if (!empty($args['id'])) $attrs .= "id='" . $args['id'] . "' ";
      $attrs = substr($attrs, 0, -1);		// remove trailing space
      $img = "<img src='$url' title='$label' alt='$label' border='0' $attrs />";
    } else {
      if ($args['noimg']) $args['noimg'] = preg_replace('/\.\w+$/', '', $args['noimg']);	// remove trailing extension
      $img = $args['noimg'] !== NULL ? $args['noimg'] : $label;
    }
  }
*/
  if (!$args['var']) return $img;
  $smarty->assign($args['var'], $img);
}

?>
