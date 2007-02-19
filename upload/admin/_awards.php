<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

function award_defaults() {
	global $data, $ps;
	// assign defaults to settings that are empty
	foreach (array('format','gametype','modtype','limit','order','enabled','type') as $key) {
		if (array_key_exists($key, $ps->conf['awards'])) {
			$data[$key] = $ps->conf['awards'][$key];
		}
	}
}

?>
