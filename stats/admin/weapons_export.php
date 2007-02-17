<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if ($register_admin_controls) {
	///////////////////////////////////////////////
	return 0;
}

$weaponlist = array();
$weaponlist = $ps_db->fetch_rows(1,"SELECT w.* FROM $ps->t_weapon w ORDER BY w.uniqueid");
$csv = '';

// get the first weapon in the list so we can determine what keys are available
$w = $weaponlist[0];
unset($w['uniqueid'], $w['weaponid']);		// remove unwanted keys
$keys = array_keys($w);				// get a list of the keys (no values)
array_unshift($keys, 'uniqueid');		// make sure uniqueid is always the first key

$csv = csv($keys);				// 1st row is always the key order
foreach ($weaponlist as $w) {
	$set = array();
	foreach ($keys as $k) {			// we want to make sure our key order is the same
		$set[] = $w[$k];		// and we only use keys from the original $keys list
	}
	$csv .= csv($set);
}

// remove all pending output buffers first 
while (@ob_end_clean());
header("Pragma: no-cache");
header("Content-Type: text/csv");
header("Content-Length: " . strlen($csv));
header("Content-Disposition: attachment; filename=\"ps-weapons-{$ps->conf['main']['gametype']}-{$ps->conf['main']['modtype']}.csv\"");
print $csv;


?>
