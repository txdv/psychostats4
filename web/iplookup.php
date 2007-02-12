<?php
/*
	GEOCODE IP Lookup. 

	This page will lookup the lat,lng of an IP address and print it out. 
	This is meant to be called from a bit of javascript code to populate
	a google map.

*/

define("VALID_PAGE", 1);
define("NOTHEME", 1);
//define("NO_CONTENT_TYPE", 1);
define("NOTIMER", 1);
include(dirname(__FILE__) . "/includes/common.php");

$validfields = array('ip');
globalize($validfields);

//$list = explode(',',$ip);
$csv = $ps->ip_lookup($ip);
$lines = array_map('trim', explode("\n", $csv));
$keys = explode(',', array_shift($lines));
$rip = array_search('requested_ip', $keys);
$lat = array_search('latitude', $keys);
$lng = array_search('longitude', $keys);

while (count($lines)) {
	$line = array_shift($lines);
	if (!$line) continue;
	$r = explode(',',$line);
	printf("%s,%s,%s\n", $r[$rip], $r[$lat], $r[$lng]);
}

?>
