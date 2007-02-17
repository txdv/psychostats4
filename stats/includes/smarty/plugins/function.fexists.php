<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {fexists} function plugin
 *
 * Type:     function<br>
 * Name:     fexists<br>
 * Purpose:  returns true if the file given exists within the current include_path
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_fexists($args, &$smarty)
{
  $args += array(
	'var'	=> '',
	'file'	=> '',
  );
  $found = FALSE;

  $found = (@file_exists($args['file']));		// try it w/o any path

  if (!empty($args['file']) && !$found) {
    $paths = explode(WIN32 ? ';' : ':', ini_get('include_path'));
    foreach ($paths as $path) {
// print $path . DIRECTORY_SEPARATOR . $args[file] . "<br>";
      if (@file_exists($path . DIRECTORY_SEPARATOR . $args['file'])) {
        $found = TRUE;
        break;
      }
    }
  }
  if (!$args['var']) return $found;
  $smarty->assign($args['var'], $found);
}

?>
