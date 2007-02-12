<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {confvarid} function plugin
 *
 * Type:     function<br>
 * Name:     confvarid<br>
 * Purpose:  PS3 method to display a id for the special config variable given
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_confvarid($args, &$smarty)
{
	$args += array(
		'var'	 => '',
		'assign' => '',
	);

	$id = array_pop(explode(VAR_SEPARATOR, $args['var']));
	if (!$args['assign']) return $id;
	$smarty->assign($args['assign'], $id);
}

?>
