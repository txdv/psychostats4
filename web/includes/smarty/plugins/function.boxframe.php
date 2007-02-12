<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {boxframe} function plugin
 *
 * Type:     function<br>
 * Name:     boxframe<br>
 * Purpose:  PS3 method to set the style display for an opened or closed stat box
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_boxframe($args, &$smarty)
{
	global $ps_user_opts;
	$args += array(
		'id'	=> '',
		'styles'=> '',
	);

	// a box is open by default if the key doesn't exist.
	$opened = array_key_exists($args['id'], $ps_user_opts) ? $ps_user_opts[ $args['id'] ] : 1;

	return sprintf("style=\"display: %s%s\"",
		$opened ? 'block' : 'none',
		$args['styles'] != '' ? "; " . $args['styles'] : ""
	);

	return $label;
}

?>
