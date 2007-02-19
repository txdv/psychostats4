<?php

define("SQL_IDENTIFIER_QUOTE_CHAR", '"');
define("SQL_CATALOG_NAME_SEPARATOR", '.');

class DB_sqlite extends DB_PARENT {

function __construct($conf=array()) {
	return $this->DB_sqlite($conf);
}

function DB_sqlite($conf) {
	$this->DB_PARENT($conf);
	$this->conf = $conf;
	return $this->connect();
}

function connect() {
	if (!function_exists('sqlite_open')) {
		$this->error("Your installation of PHP v" . PHP_VERSION . " does not include SQLite support.");
		$this->_fatal("Extension Error!");
		return 0;
	}

#	die("DBNAME = " . $this->dbname);
	$this->dbh = @sqlite_open($this->dbname . ".db", 0666, $err);
	if ($this->dbh) {
		$this->connected = 1;
	} else {
		$this->error("<b>SQLITE Error:</b> $err");
		$this->_fatal(sprintf("Error connecting to SQLITE database '<b>%s</b>'", $this->dbname));
		$this->connected = 0;
	}

	$this->escape_func = 'sqlite_escape_string';

	return 1;
}

// Sends a query ...
function query($cmd) {
	if (!$this->connected) return 0;
	$this->totalqueries++;
	$this->lastcmd = $cmd;
	$this->error("");
#	print $this->lastcmd . ";<br><br>\n\n";
	$this->res = sqlite_query($this->dbh, $cmd, $this->errcode);
	if (!$this->res) {
		$this->error("<b>SQLITE Error:</b> " . @sqlite_error_string(sqlite_last_error($this->dbh)));
		$this->_fatal("<b>SQL Error in query string:</b> \n\n$cmd");
	}
	return $this->res;
}

function table_columns($tbl) {
	$list = array();
	$this->query("PRAGMA table_info(" . $this->quote_identifier($tbl) . ")");
	while ($row = $this->fetch_row()) {
		$list[] = $row['name'];
	}
	return $list;
}

}

?>
