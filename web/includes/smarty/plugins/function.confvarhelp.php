<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {confvarhelp} function plugin
 *
 * Type:     function<br>
 * Name:     confvarhelp<br>
 * Purpose:  PS3 method to display the statusText help for the special config variable given
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_confvarhelp($args, &$smarty)
{
	global $conf_layout;
	$args += array(
		'var'		=> '',
		'quote'		=> 1,
	);

	$parts = explode(VAR_SEPARATOR, $args['var']);
	array_pop($parts);
	$var = implode(VAR_SEPARATOR, $parts);

	$help = $conf_layout[$var]['comment'];
	if ($args['quote']) {
		$help = preg_replace(
			array(
				"/(?<!\\\\)'/",
				"/\x0D?\x0A/"
			), 
			array(
				"\\'",
				"\\n"
			), 
			$help
		);
		$help = htmlentities($help);
	}
	return $help;
}

?>
