<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {boximg} function plugin
 *
 * Type:     function<br>
 * Name:     boximg<br>
 * Purpose:  PS3 method to display a [+] or [-] image for an opened or closed stat box
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_boximg($args, &$smarty)
{
	global $ps_user_opts, $data;
	$args += array(
		'id'	=> '',
		'alt'	=> '',
		'xhtml'	=> 1,
	);

	// a box is open by default if the key doesn't exist.
	$opened = array_key_exists($args['id'], $ps_user_opts) ? $ps_user_opts[ $args['id'] ] : 1;

	return sprintf("<img id=\"%s\" src=\"%sexp_%s.gif\" alt=\"%s\" %s>", 
		"box_" . $args['id'] . "_img",
		$data['imagesurl'],
		$opened ? 'minus' : 'plus',
		htmlentities($args['alt']),
		$args['xhtml'] ? "/" : ""
	);
}

?>
