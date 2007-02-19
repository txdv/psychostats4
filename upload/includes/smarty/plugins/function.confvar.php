<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {confvar} function plugin
 *
 * Type:     function<br>
 * Name:     confvar<br>
 * Purpose:  PS3 method to display the name of the variable
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_confvar($args, &$smarty)
{
	$args += array(
		'var'	=> '',
	);

	$parts = explode(VAR_SEPARATOR, $args['var']);
	if ($parts[0] == '') array_shift($parts);
	array_pop($parts);
	$var = implode('.', $parts);
	return $var;
}

?>
