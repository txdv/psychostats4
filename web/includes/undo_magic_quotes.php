<?php 
// thanks to an anonymous user for this elegant solution
//	http://us2.php.net/manual/en/function.get-magic-quotes-gpc.php
// undoing the magic quotes all at once allows me to never worry about
// them on a per-page basis
//
// NOTE: This might cause problems with third-party software when integrated
// with PsychoStats.

function undo_magic_quotes() {
	if (get_magic_quotes_gpc()) {
		$_GET = array_map_recursive('stripslashes', $_GET);
		$_POST = array_map_recursive('stripslashes', $_POST);
		$_COOKIE = array_map_recursive('stripslashes', $_COOKIE);
		$_REQUEST = array_map_recursive('stripslashes', $_REQUEST);
	}
}

if (!function_exists('array_map_recursive')) {
	function array_map_recursive($function, $data) {
		foreach ($data as $i => $item) {
			$data[$i] = is_array($item)
				? array_map_recursive($function, $item)
				: $function($item);
		}
		return $data;
	}
}

undo_magic_quotes();


?>
