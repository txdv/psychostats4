<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty date modifier plugin
 *
 * Type:     modifier<br>
 * Name:     date<br>
 * Purpose:  returns a formatted date using date()
 * @param integer
 * @return string
 */
function smarty_modifier_date($time, $format='') {
	global $ps;
	if (empty($format)) $format = $ps->conf['theme']['format']['date'];
	if (empty($format)) $format = "Y-m-d";
	$ofs = $ps->conf['theme']['format']['time_offset'];
	if (!empty($ofs)) {
		$neg = false;
		if ($ofs{0} == '-' or $ofs{0} == '+') {
			$neg = (bool)($ofs{0} == '-');
			$ofs = substr($ofs,1);
		}

		list($h,$m) = explode(':', $ofs);
		$h = (int)$h;
		$m = (int)$m;
		$ofs = 60*60*$h + 60*$m;
		if ($neg) $ofs *= -1;
//		print " ($ofs) ";
		if ($ofs) $time += $ofs;
	}
	return date($format, $time);
}

/* vim: set expandtab: */

?>
