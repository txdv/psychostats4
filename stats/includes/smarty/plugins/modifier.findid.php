<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty findid modifier plugin
 *
 * Type:     modifier<br>
 * Name:     findid<br>
 * Purpose:  Finds the array element that matches the id given and returns only that element
 * @param array
 * @param string
 * @return integer
 */
function smarty_modifier_findid($ary, $id, $key='weaponid')
{
  if (!is_array($ary)) return 0;
  $match = array();
  foreach ($ary as $i) {
    if ($i[$key] == $id) {
      $match = $i;
      break;
    }
  }
  return $match;
}

/* vim: set expandtab: */

?>
