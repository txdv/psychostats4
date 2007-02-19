<?php 
if (defined("CLASS_DB_PARENT_PHP")) return 1;
define("CLASS_DB_PARENT_PHP", 1);

class DB_PARENT {
var $DEBUG 	= 0;
var $errno	= 0;
var $errstr	= '';
var $conf 	= array();
var $ps_dbh	= null;
var $escape_func	= 'addslashes';

function __construct($conf=array()) {
	$this->DB_PARENT($conf);
}

// class constructor
function DB_PARENT($conf=array()) {
	$this->conf = $conf;
	$this->dbtype = $conf['dbtype'];
	$this->dbhost = $conf['dbhost'];
	$this->dbname = $conf['dbname'];
	$this->dbport = $conf['dbport'];
	$this->dbname = $conf['dbname'];
	$this->dbuser = $conf['dbuser'];
	$this->dbpass = $conf['dbpass'];
	$this->dbtblprefix = $conf['dbtblprefix'];

	$this->errors = array();
	$this->totalqueries = 0;
	$this->queries = array();

	$this->classname = "DB::" . $conf['dbtype'];
}

function connect() {
	die("Abstract method called: " . $this->classname . "::connect");
}

// Return the MAX() value of the key given
function max($tbl, $key='id', $where='') {
	if (empty($key)) $key = "id";
	if (!empty($where)) $where = "WHERE $where";
	$res = $this->query("SELECT MAX($key) FROM $tbl $where");
	list($max) = ($res) ? $this->fetch_row(0) : array(0);
	return $max;    
}

function min($tbl, $key='id', $where='') {               
	if (empty($key)) $key = "id";
	if (!empty($where)) $where = "WHERE $where";
	$res = $this->query("SELECT MIN($key) FROM $tbl $where");
	list($min) = ($res) ? $this->fetch_row(0) : array(0);           
	return $min;                  
}

function next_id($tbl, $key='id') {
	if (empty($key)) $key = 'id';
	return $this->max($tbl, $key) + 1;
}

function server_info() {
	return array('version' => 'Unknown');
}

function table_status($ps_dbname='') {
	if (!empty($ps_dbname)) $ps_dbname = "FROM $ps_dbname";
	$cmd = "SHOW TABLE STATUS $ps_dbname";
	$this->query($cmd);
	return $this->fetch_rows();
}

// returns the column names of a table as an array
function table_columns($tbl) {
	die("Abstract method called: " . $this->classname . "::table_columns");
}

// Sends a query ...
function query($cmd) {
	die("Abstract method called: " . $this->classname . "::query");
}

// fetches the next row from the last query performed (only use after a SELECT query)
// If $cmd is specified, it will be queried first, and then the first row returned (if no errors occur)
function fetch_row($assoc=1, $cmd="") {
	die("Abstract method called: " . $this->classname . "::fetch_row");
}

// fetches all remaining rows from the last SELECT query performed
// If $cmd is specified, it will be queried first, and then all rows returned (if no errors occur)
function fetch_rows($assoc=1, $cmd="") {
	$list = array();
	if ($cmd) $this->query($cmd);
	if (!$this->res) return $list;
	while ($row = $this->fetch_row($assoc)) {
		$list[] = $row;
	}
	return $list;
}

// fetches a list of items from a select. All columns in a row are returned as a single array
function fetch_list($cmd="") {
	$list = array();
	if ($cmd) $this->query($cmd);
	if (!$this->res) return $list;
	while ($row = $this->fetch_row(0)) {
		$list = array_merge($list, $row);
	}
	return $list;
}

// returns the first element from the next row. uses $cmd if needed to start a new query
function fetch_item($cmd="") {
	$row = $this->fetch_row(0, $cmd);
	return $row[0];
}

// returns the number of rows from the last SELECT query performed
function num_rows() {
	die("Abstract method called: " . $this->classname . "::num_rows");
}

// returns the number of rows that were affected from the last INSERT, UPDATE, or DELETE query performed
function affected_rows() {
	die("Abstract method called: " . $this->classname . "::affected_rows");
}

// returns the last auto_increment ID used
function last_insert_id() {
	die("Abstract method called: " . $this->classname . "::last_insert_id");
}

// delete row(s) from a table 
function delete($tbl, $key, $id=NULL) {
	if ($id===NULL) {	// assume $key is a full where clause
		return $this->query("DELETE FROM $tbl WHERE $key");
	} else {
		return $this->query("DELETE FROM $tbl WHERE " . $this->qi($key) . "='" . $this->escape($id) . "'");
	}
}

// truncates (deletes) the table given entirely. 
function truncate($tbl) {
	return $this->query("DELETE FROM $tbl");
}

// fetch a single row from the DB, matching on a single field name (select * From TBL WHERE key=value)
function select_row($tbl, $values, $key, $id=NULL, $assoc=0) {
	if (!is_array($key)) {
		$res = $this->query(sprintf("SELECT %s FROM %s WHERE %s = '%s' LIMIT 1", $values, $tbl, $this->qi($key), $this->escape($id)));
	} else {
		$where = "";
		foreach ($key as $k => $v) {
			$where .= $this->qi($k) . " = '" . $this->escape($v) . "' and ";
		}
		$where = !empty($where) ? substr($where,0,-5) : "1";		// strip off ' and ', or return '1' if there's no where clause
		$res = $this->query(sprintf("SELECT %s FROM %s WHERE %s LIMIT 1", $values, $tbl, $where));
	}
	return ($res) ? $this->fetch_row($assoc) : array();
}

// returns the total count of a key in a table
function count($tbl, $key='*', $where='') {
	if (empty($key)) $key = "*";
	if (!empty($where)) $where = "WHERE $where";
	$res = $this->query("SELECT count($key) FROM $tbl $where");
	list($total) = ($res) ? $this->fetch_row(0) : array(0);
	return $total;
}

// updates a row in a table with the values in the set array. if set is not an array it's assumed to be a valid query string
function update($tbl, $set, $key, $id) {
	$values = "";
	if (is_array($set)) {
		foreach ($set as $k => $v) {
			$values .= $this->qi($k) . "='" . $this->escape($v) . "', ";
		}
		if (strlen($values) > 2) $values = substr($values, 0, -2);
	} else {
		$values = $set;
	}
	return $this->query("UPDATE $tbl SET $values WHERE " . $this->qi($key) . "='" . $this->escape($id) . "'");
}

// inserts a row into the table using the values in set
function insert($tbl, $set) {
	$values = "";
	if (is_array($set)) {
		foreach ($set as $k => $v) {
			$values .= $this->qi($k) . "='" . $this->escape($v) . "', ";
		}
		if (count($set)) {
			$values = substr($values, 0, -2);
		}
	} else {
		$values = $set;
	}
	return $this->query("INSERT INTO $tbl SET $values");
}

// returns true if a row exists based on the key=id given
function exists($tbl, $key, $id=NULL) {
	if ($id === NULL) {		// assume $key is in the form: 'mykey=value'
		$cmd = "SELECT count(*) FROM $tbl WHERE $key";
	} else {
		$cmd = "SELECT count(*) FROM $tbl WHERE " . $this->qi($key) . "='" . $this->escape($id) . "'";
	}
	$res = $this->query($cmd);
	$total = 0;
	if ($this->num_rows()) {
		list($total) = $this->fetch_row(0);
	}
	return $total;
}

function dropdb($ps_dbname) {
	return $this->query("DROP DATABASE " . $this->qi($ps_dbname));
}

function droptable($tbl) {
	return $this->query("DROP TABLE " . $this->qi($tbl));
}

// returns true if the database name given exists
function dbexists($ps_dbname) {
	die("Abstract method called: " . $this->classname . "::dbexists");
}

function quote_identifier($id) {
	if ($id === NULL) return $id;
	$quote = SQL_IDENTIFIER_QUOTE_CHAR;
	return $quote . str_replace($quote, $quote.$quote, $id) . $quote;
}
function qi($id) {	// alias for quote_identifier
	return $this->quote_identifier($id); 
}

// optimize the given table (or array of tables)
function optimize($tbl) { }	// abstract method

function begin() {
	$this->query("BEGIN");
}

function commit() {
	$this->query("COMMIT");
}

function rollback() {
	$this->query("ROLLBACK");
}

// escapes a value for insertion into the DB, will try to use the best method available on the current PHP version
function escape($str) {
	$func = $this->escape_func;
	return @$func($str);
}

function fatal($new=NULL) {
	$old = $this->conf['fatal'];
	if ($new !== NULL) $this->conf['fatal'] = $new;
	return $old;
}

// reports a fatal error and DIE's
function _fatal($msg) {
	$err = $msg;
	$err .= "\n\n" . $this->errstr;
	$err .= "<hr>";
	if ($this->fatal()) die(nl2br($err));
}

// returns the last error generated
function lasterr() {
	return $this->errstr;
}

// assigns a new error to the current 'errstr' and stores the old 'errstr' in the $errors array
function error($e) {
	if ($this->errstr != '') $this->errors[] = $this->errstr;	// store the last error if there is one
	$this->errstr = $e;						// assign the current error
}

// returns all errors generated
function allerrors() {
	$this->error('');						// store the last possible error, and clear out the current errstr
	return $this->errors;
}

// these expressions are used in queries that combine players together (ie: clan pages)
function _expr_percent($ary) 		{ return "IFNULL(SUM($ary[0]) / SUM($ary[1]) * 100, 0.00)"; }
function _expr_percent2($ary) 		{ return "IFNULL(SUM($ary[0]) / (SUM($ary[0])+SUM($ary[1])) * 100, 0.00)"; }
function _expr_ratio($ary) 		{ return "IFNULL(SUM($ary[0]) / SUM($ary[1]), SUM($ary[0]))"; }
function _expr_ratio_minutes($ary) 	{ return "IFNULL(SUM($ary[0]) / (SUM($ary[1]) / 60), SUM($ary[0]))"; }

// same as above but these are used for non-clan pages that aren't compiled (plr_sessions)
function _soloexpr_percent($ary) 	{ return "IFNULL($ary[0] / $ary[1] * 100, 0.00)"; }
function _soloexpr_ratio($ary) 		{ return "IFNULL($ary[0] / $ary[1], $ary[0])"; }
function _soloexpr_ratio_minutes($ary) 	{ return "IFNULL($ary[0] / ($ary[1] / 60), $ary[0])"; }

function _expr_min($ary)		{ return "IF($ary[0] < $ary[1], $ary[0], $ary[1])"; }
function _expr_max($ary)		{ return "IF($ary[0] > $ary[1], $ary[0], $ary[1])"; }


}

?>
