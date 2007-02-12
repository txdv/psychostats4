<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {roleimg} function plugin
 *
 * Type:     function<br>
 * Name:     roleimg<br>
 * Purpose:  returns the <img> tag for the role name, 
		or just a text string of the role name/desc if the image file doesn't exist
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_roleimg($args, &$smarty) {
	global $ps;
	$args += array(
		'var'		=> '',
		'role'	=> array(),	// $role array (or role name string)
		'alt'		=> NULL,
		'height'	=> NULL,
		'width'		=> NULL,
		'path'		=> 0,		// add $path to the end of basedir? (eg: 'large')
		'noimg'		=> NULL,

		'align'		=> '',
		'valign'	=> '',
		'style'		=> '',
		'class'		=> '',
		'id'		=> '',
		'extra'		=> '',
	);
	$basedir = catfile($ps->conf['theme']['rootrolesdir'], $ps->conf['main']['gametype'], $ps->conf['main']['modtype']);
	$baseurl = catfile($ps->conf['theme']['rootrolesurl'], $ps->conf['main']['gametype'], $ps->conf['main']['modtype']);
	if ($args['path']) {
		$basedir = catfile($basedir, $args['path']);
		$baseurl = catfile($baseurl, $args['path']);
	}
	$w = $args['role'];
	if (!is_array($w)) $w = array( 'uniqueid' => !empty($w) ? $w : 'unknown' );
	$name = !empty($w['uniqueid']) ? $w['uniqueid'] : 'unknown';
	$alt = ($args['alt'] !== NULL) ? $args['alt'] : $w['name'];
	$label = !empty($alt) ? $alt : $name;
	$ext = explode(',', $ps->conf['theme']['images']['search_ext']);

	$file = "";
	foreach ($ext as $e) {
		$e = trim($e);
		if ($e{0} == '.') $e = substr($e,1);		// just in case someone puts a dot on the extension
		$file = catfile($basedir,$name) . '.' . $e;
		$url  = catfile($baseurl,$name) . '.' . $e;
		if (@file_exists($file)) break;
		$file = "";
	}

	// if $file is !empty then it exists
	if ($file) {
		$attrs = " ";
		if (is_numeric($args['width'])) $attrs .= "width='" . $args['width'] . "' ";
		if (is_numeric($args['height'])) $attrs .= "height='" . $args['height'] . "' ";
		if (!empty($args['align'])) $attrs .= "align='" . $args['align'] . "' ";
		if (!empty($args['valign'])) $attrs .= "valign='" . $args['valign'] . "' ";
		if (!empty($args['style'])) $attrs .= "style='" . $args['style'] . "' ";
		if (!empty($args['class'])) $attrs .= "class='" . $args['class'] . "' ";
		if (!empty($args['id'])) $attrs .= "id='" . $args['id'] . "' ";
		if (!empty($args['extra'])) $attrs .= $args['extra'];
		$attrs = substr($attrs, 0, -1);		// remove trailing space
		$img = "<img src='$url' title='$label' alt='$alt' border='0' $attrs />";
	} else {
		$img = $args['noimg'] !== NULL ? $args['noimg'] : $label;
	}

	if (!$args['var']) return $img;
	$smarty->assign($args['var'], $img);
}

?>
