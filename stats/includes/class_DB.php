<?php
if (defined("CLASS_DB_PHP")) return 1;
define("CLASS_DB_PHP", 1);

include_once(dirname(__FILE__) . "/DB/DB_PARENT.php");

class DB {

// Our factory method to create a valid object for our specified database
function &create($conf=array()) {
	if (!is_array($conf)) {				// force $conf into an array.
		$ps_dbhost = $conf;			// If $conf is not an array it's assumed to be a host[:port]
		$conf = array( 'dbhost' => $ps_dbhost );
		unset($ps_dbhost);
	}

	// Add defaults to the config. Defaults do not override values passed in the $conf array
	$conf += array(
		'dbtype'	=> 'mysql',
		'dbhost'	=> 'localhost',
		'dbport'	=> '',
		'dbname'	=> 'psychostats',
		'dbuser'	=> '',
		'dbpass'	=> '',
		'dbtblprefix'	=> '',
		'delaydb'	=> 0,
		'fatal'		=> 1,
	);

	// If no 'dbtype' is specified default to "mysql"
	if (!$conf['dbtype']) {
		$conf['dbtype'] = 'mysql';
	}

	// setup the object name and filename to include it
	$filename = strtolower($conf['dbtype']);
	$classname = "DB_" . $filename;
	$filepath = dirname(__FILE__) . "/DB/" . $filename . ".php";

	// Attempt to load the proper class for our specified 'dbtype'.
	if (!include_once($filepath)) {
		die("<b>Fatal Error:</b> Unsupported 'dbtype' specified (${conf['dbtype']}) for new DB object.");
	} else {
#		$this->classname = "DB::" . $conf['dbtype'];
		$_db = new $classname($conf);
		return $_db;
	}
}  // end of constructor

}  // end of class DB

?>
