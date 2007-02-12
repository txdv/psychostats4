<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {confvarlabel} function plugin
 *
 * Type:     function<br>
 * Name:     confvarlabel<br>
 * Purpose:  PS3 method to display a label for the special config variable given
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_confvarlabel($args, &$smarty)
{
	global $conf_idxs, $ps_user_opts;
	$args += array(
		'var'	=> '',
		'edit'	=> 1,
	);

	$parts = explode(VAR_SEPARATOR, $args['var']);
	$label = $parts[1];
	if (confvarmulti($args['var'])) $label .= " " . $conf_idxs[$args['var']];

	if ($ps_user_opts['advconfig'] and $args['edit']) {
		$id = array_pop($parts);
		$label = sprintf("<a href='admin.php?c=config_new&id=%d'>%s</a>",
			$id,
			$label
		);
	}

	return $label;
}

?>
