<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {pager} function plugin
 *
 * Type:     function<br>
 * Name:     pagination<br>
 * Purpose:  Pagination link output
 * @version  1.0
 * @param array
 * @param Smarty
 * @return string output from {@link Smarty::_generate_debug_output()}
 */
function smarty_function_pagination($args, &$smarty)
{
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
  );
  $output = pager(
	$args['baseurl'], 
	$args['total'], 
	$args['perpage'], 
	$args['start'], 
	$args['pergroup'], 
	$args['urltail'],
	$args['force_prev_next']
  );

  if ($args['startvar'] != '' and $args['startvar'] != 'start') $output = str_replace('start=', $args['startvar'] . '=', $output);
  if ($args['prefix'] != '' and !empty($output)) $output = $args['prefix'] . $output;

  if (!$args['var']) return $output;
  $smarty->assign($args['var'], $output);
}

?>
