<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

function parsesource($str) {
	global $protocolport;
	$s = array();
	if (preg_match('/^([^:]+):\/\/(?:([^:]+)(?::([^@]+))?@)?([^\/]+)\/?(.*)/', $str, $m)) {
		$m[1] = strtolower($m[1]);
		$s += array( 'protocol' => $m[1] );
		if ($m[1] != 'file') {
			$s += array(
				'username'	=> $m[2],
				'password'	=> $m[3],
				'host'		=> $m[4],
				'port'		=> '',
				'path'		=> $m[5],
				'blankpassword'	=> 0,
			);
			if (preg_match('/^([^:]+):(.+)/', $m[4], $hp)) {
				$s['host'] = $hp[1];
				$s['port'] = $hp[2];
			} else {
				$s['host'] = $m[4];
			}
//			if ($s['port'] == '') $s['port'] = $protocolport[ $s['protocol'] ];
			if ($s['password'] == '') $s['blankpassword'] = 1;
		}
	} else {						// normal 'file' logsource
		$s += array( 'protocol' => 'file', 'path' => $str );
	}

	if ($s['protocol'] == 'file') {
		$s['safelogsource'] = $str;
	} else {
		$s['safelogsource'] = sprintf("%s://%s@%s%s/%s", 
			$s['protocol'], 
			$s['username'],
			$s['host'],
			$s['port'] ? ":" . $s['port'] : '',
			$s['path']
		);
	}

	return $s;
}

?>
