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
 * Name:     confvarerrs<br>
 * Purpose:  PS3 method to display errors for the variable names
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_confvarerrs($args, &$smarty)
{
	$args += array(
		'err'	=> '',
	);

	$list = array();
	foreach ($args['err'] as $key => $err) {
		$parts = explode(VAR_SEPARATOR, $key);
		if ($parts[0] == '') array_shift($parts);
		array_pop($parts);
		$list[] = implode('.', $parts);
	}

	return implode(', ', $list);
}

?>
