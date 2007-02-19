<?php
/********

	Main PsychoQuery Factory class. This returns a new PQ object based on the type of server you intend to query.
	This is the only file you need to 'include' into your applications. The PQ directory must be somewhere in your
	'include_path'.

	Example:

		include("class_PQ.php");

		$pq = PQ::create($conf);
		print_r($pq->query_info('1.2.3.4:27015'));

********/

if (defined("CLASS_PQ_PHP")) return 1;
define("CLASS_PQ_PHP", 1);

include_once(PS_ROOTDIR . "/includes/PQ/PQ_PARENT.php");

class PQ {

// Our factory method to create a valid object for our querytype specified
function &create($conf) {
	if (!is_array($conf)) {			// force $conf into an array.
		$ip = $conf;			// If $conf is not an array it's assumed to be an ipaddr[:port]
		$conf = array( 'ip' => $ip );
		unset($ip);
	}

	// Add defaults to the config. Defaults do not override values passed in the $conf array
	$conf += array(
		'ip'		=> '',
		'port'		=> '',
		'querytype'	=> 'halflife',
		'master'	=> 0,
		'timeout'	=> 3,
		'retries'	=> 1,
	);

	// Separate IP:Port if needed
	if (strpos($conf['ip'], ':') !== FALSE) {
		$ipport = $conf['ip'];
		list($conf['ip'], $conf['port']) = explode(':', $ipport, 2);
		if (!is_numeric($conf['port'])) {
			$conf['port'] = '';
		}
	} else {
//		$conf['port'] = '';
	}

	// If no 'querytype' is specified default to 'halflife'
	if (!$conf['querytype']) {
		$conf['querytype'] = 'halflife';
	}

	// Attempt to load the proper class for our specified 'querytype'.
	$filename = strtolower($conf['querytype']);
	$classname = "PQ_" . $filename;

	if (!include_once("PQ/" . $filename . ".php")) {
		trigger_error("Unsupported 'querytype' specified (${conf['querytype']}) for new PQ object", E_USER_ERROR);
	} else {
		return new $classname($conf);
	}
}

}  // end of class PQ

// returns an array of query types that are allowed when creating a new PQ object. 
// this is not an object method. It's a plain function.
function pq_query_types() {
	// this should be made more robust to read the files in the PQ directory to create a list.
	$q = array();
	$q['halflife'] 		= 'Halflife 1 or 2';
	$q['oldhalflife'] 	= 'Halflife 1 only (no steam)';
	$q['gamespy'] 		= 'Gamespy (partial support)';
	$q['quake3'] 		= 'Quake 3';
	return $q;
}

?>
