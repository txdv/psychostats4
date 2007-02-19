<?php
/**
 * Smarty plugin	-- Stormtrooper at psychostats dot com
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty byte abbrieviator modifier plugin
 *
 * Type:     modifier<br>
 * Name:     abbrnum<br>
 * Purpose:  convert integer into a string representing the number with trailing 'bytes, 'MB', 'GB', 'TB', etc...
 * @param string
 * @return string
 */
function smarty_modifier_abbrnum($string, $tail=2, $bytestr='')
{
    $str = abbrnum($string, $tail);
    if ($bytestr != '') {
      $str = preg_replace('/ bytes/', $bytestr, $str);
    }
    return $str;
}

?>
