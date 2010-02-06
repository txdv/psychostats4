<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 *	Main Psychostats model
 *	
 *	$Id$
 *	
 */
class Psychostats extends Model {
	// @var string Path to directory where method files are located.
	public $methods_dir = '';
	// @var boolean Allow methods to be autoloaded if true (default).
	// If false load_method() must be called explicitly.
	public $allow_autoload_methods = true;
	// @var string SQL string used to limit SQL results to players that are ranked.
	public $is_ranked_sql = '(plr.rank IS NOT NULL AND plr.rank <> 0)';
	public $is_not_ranked_sql = '(plr.rank IS NULL OR plr.rank=0)';
	public $is_clan_ranked_sql = '(clan.rank IS NOT NULL)';
	// @var array Array of methods that have been loaded.
	protected $loaded_methods = array();
	// @var array PsychoStats configuration that has been loaded from DB.
	protected $ps_config = null;

	// @var string Default gametype to use for stats functions.
	protected $gametype;
	// @var string Default modtype to use for stats functions.
	protected $modtype;

	public function Psychostats() {
		parent::Model();
		$this->methods_dir =
			dirname(__FILE__) .
			DIRECTORY_SEPARATOR .
			'psychostats' .
			DIRECTORY_SEPARATOR;
	}
	
	// Returns the raw config as an array.
	public function get_raw_config($conftype = null, $section = null, $var = null) {
		$this->db->select('*');
		if (!is_null($conftype)) {
			if (is_array($conftype)) {
				$this->db->where_in('conftype', $conftype);
			} else {
				$this->db->where('conftype', $conftype);
			}
		}
		if (!is_null($section)) {
			$this->db->where('section', $section);
		}
		if (!is_null($var)) {
			$this->db->where('var', $var);
		} else {
			$this->db->where('var IS NOT NULL');
		}
		
		$query = $this->db->get('config');
		$config = $this->_process_config_query($query, true);
		return $config;
	}

	public function get_keyed_config($glue = '.', $conftype = null, $section = null, $var = null) {
		$list = $this->get_raw_config($conftype, $section, $var);
		$conf = array();
		foreach ($list as $c) {
			if ($c['conftype'] == 'info') {
				// ignore 'info' variables ...
				continue;
			}
			$key = implode($glue, array(
				$c['conftype'],
				$c['section'] ? $c['section'] : '',
				$c['var']
			));
			$conf[$key] = $c;
		}
		ksort($conf);
		return $conf;
	}

	public function get_config_sections() {
		// note: the space within the COALESCE() function below is
		// required since the activeQuery routines in CI are buggy.
		$this->db->select('conftype,section,COALESCE(label, section ) AS label,value');
		$this->db->where('var IS NULL');
		$this->db->order_by('conftype,label,section');
		$query = $this->db->get('config');
		$sections = array();
		foreach ($query->result_array() as $row) {
			$sections[ $row['conftype'] ][] = $row;
		}
		$query->finish;
		return $sections;
	}

	// resets the config so get_config() will fetch a fresh result from db.
	public function reset_config() {
		$this->ps_config = null;
	}
	
	// Returns the config in an associated array for easy access.
	public function get_ps_config($conftype = null, $section = null, $var = null) {
		// short-cut; return config if it was already loaded.
		if (!is_null($this->ps_config)) {
			return $this->ps_config;
		}
		
		$this->db->select('conftype, section, var, value');
		if (!is_null($conftype)) {
			if (is_array($conftype)) {
				$this->db->where_in('conftype', $conftype);
			} else {
				$this->db->where('conftype', $conftype);
			}
		}
		if (!is_null($section)) {
			$this->db->where('section', $section);
		}
		if (!is_null($var)) {
			$this->db->where('var', $var);
		} else {
			$this->db->where('var IS NOT NULL');
		}
		
		$query = $this->db->get('config');
		$this->ps_config = $this->_process_config_query($query);
		$query->free_result();
		return $this->ps_config;
	}

	protected function _process_config_query($query, $raw = false) {
		$config = array();
		foreach ($query->result_array() as $row) {
			if ($raw) {
				$config[ $row['id'] ] = $row;
			} else {
				if (!empty($row['section'])) {
					$config[$row['conftype']][$row['section']][$row['var']] = $row['value'];
				} else {
					$config[$row['conftype']][$row['var']] = $row['value'];
				}
			}
		}
		return $config;
	}

	/**
	 * Set's the default gametype and modtype to use for various functions.
	 * @param string $gametype Default gametype to use.
	 * @param string $modtype (Optional) Default modtype to use.
	 */
	public function set_gametype($gametype, $modtype = null) {
		$this->gametype = $gametype;
		if (isset($modtype)) {
			$this->set_modtype($modtype);
		}
	}

	/**
	 * Returns the current default for gametype
	 */
	public function gametype() {
		return $this->gametype;
	}

	/**
	 * Resets the default gametype and modtype to null.
	 */
	public function reset_gametype() {
		$this->gametype = null;
		$this->modtype = null;
	}

	/**
	 * Set's the default modtype to use for various functions.
	 * @param string $modtype Default modtype to use.
	 */
	public function set_modtype($modtype) {
		$this->modtype = $modtype;
	}

	/**
	 * Returns the current default for modtype
	 */
	public function modtype() {
		return $this->modtype;
	}

	/**
	 * Load a method into the class. Throws an Exception if loading fails.
	 * @param mixed $name Method name (or array of names) to load.
	 * @param string $methods_dir altnerate path to search for method file.
	 * @param boolean $missing_allowed If true an exception is not thrown
	 * 				if the method file does not exist.
	 */
	public function load_method($name, $methods_dir = null, $missing_allowed = false, $class = null) {
		if (is_array($name)) {
			$m = false;
			foreach ($name as $n) {
				$m = $this->load_method($n, $methods_dir, $missing_allowed);
			}
			// when loading multiple methods only return the last one
			return $m;
		}
		
		if (is_null($methods_dir)) {
			$methods_dir = $this->methods_dir;
		}

		if (is_null($class)) {
			$class = $name;
		}
		$class_name = 'Psychostats_Method_' . $class;
		
		// If the class does'nt exist try to load and instantiate it
		if (!class_exists($class_name, false)) {
			$filename = 'method.' . strtolower($name) . EXT;
			if (!file_exists($methods_dir . $filename)) {
				if ($missing_allowed) {
					return false;
				} else {
					throw new Exception("Method file $filename does not exist");
				}
			} 
			include $methods_dir . $filename;
			if (!class_exists($class_name, false)) {
				if ($missing_allowed) {
					return false;
				} else {
					throw new Exception("Method file $filename does not define class $class_name");
				}
			}
			$this->loaded_methods[$name] = new $class_name($this);
		}
		return $this->loaded_methods[$name];
	}

	/**
	 * Searches for a game specific method to load.
	 * @param string $func Name of the function to load.
	 * @param string $gametype Gametype
	 * @param string $modtype Modtype
	 */
	public function load_overloaded_method($func, $gametype, $modtype = null) {
		// short-circuit return if the method is loaded already
		if (array_key_exists($func, $this->loaded_methods)) {
			return $this->loaded_methods[$func];
		}

		$parts = array( $gametype );
		if ($modtype) {
			$parts[] = $modtype;
		}

		while (count($parts)) {
			$methods_dir = $this->methods_dir .
				implode(DIRECTORY_SEPARATOR, $parts) .
				DIRECTORY_SEPARATOR;

			// attempt to load the method
			$class_name = $func . '_' . implode('_', $parts);
			$method = $this->load_method($func, $methods_dir, true, $class_name);
			if ($method) {
				return $method;
			}
			array_pop($parts);
		}

		// attempt to load base method since an overloaded method was
		// not found above.
		if ($method = $this->load_method($func, null, true)) {
			return $method;
		}
		
		return false;
	}
	
	/**
	 * Shortcut method that allows game specific methods to change the
	 * behavior or look of a table before it's rendered.
	 * @param object $table Table object reference.
	 * @param string $func Name of the function to call (automatically
	 * 		       prefixed with 'mod_table_')
	 * @param string $gametype Gametype
	 * @param string $modtype Modtype
	 */
	public function mod_table($table, $func, $gametype, $modtype = null) {
		$name = 'mod_table_' . $func;
		if ($meth = $this->load_overloaded_method($name, $gametype, $modtype)) {
			$meth->execute($table, $gametype, $modtype);
		}
		return false;

	}

	/**
	 * Returns the gametype and modtype for the player that matches the ID.
	 * @param integer $id 	   The plrid of the player to match.
	 * @param boolean $ret_obj If true a 2 element array is returned, false (default)
	 * 			   an object is returned instead.
	 * @return mixed 	   FALSE on failure, or either a 2 element array or an object.
	 */
	public function get_player_gametype($id, $ret_obj = false) {
		return $this->get_object_gametype($id, 'plr', 'plrid', $ret_obj);
	}
	public function get_clan_gametype($id, $ret_obj = false) {
		return $this->get_object_gametype($id, 'clan', 'clanid', $ret_obj);
	}
	public function get_weapon_gametype($id, $ret_obj = false) {
		return $this->get_object_gametype($id, 'weapon', 'weaponid', $ret_obj);
	}
	public function get_map_gametype($id, $ret_obj = false) {
		return $this->get_object_gametype($id, 'map', 'mapid', $ret_obj);
	}
	public function get_role_gametype($id, $ret_obj = false) {
		return $this->get_object_gametype($id, 'role', 'roleid', $ret_obj);
	}
	
	/**
	 * Returns the gametype::modtype for the object within the table.
	 * @param integer $id The ID of the record to fetch.
	 * @param string $tbl The table name to query.
	 * @param string $key The key name of the primary key.
	 */
	public function get_object_gametype($id, $tbl, $key, $ret_obj = false) {
		// return preset gametype/modtype if available
		if ($this->gametype !== null) {
			if ($ret_obj) {
				$o = new stdClass();
				$o->gametype = $this->gametype;
				$o->modtype = $this->modtype;
				return $o;
			} else {
				return array($this->gametype, $this->modtype);
			}
		}

		$tbl = $this->tbl($tbl, false);
		$sql = "SELECT gametype, modtype FROM $tbl WHERE $key = ? LIMIT 1";
		$q = $this->db->query($sql, $id);
		
		if ($q->num_rows() == 0) {
			// not found
			return false;
		}

		$r = null;
		if ($ret_obj) {
			$r = $q->row();
		} else {
			$r = $q->row_array();
		}
		$q->free_result();
		return $r;
	}
	

	/**
	 * Shortcut for ORDER BY x y.
	 * @param string $sort A comma separated string of fields to sort by
	 * @param string $order 'asc' or 'desc' order applied to the $sort.
	 */
	public function order_by($sort, $order = 'asc') {
		$sql = '';
		if ($sort) {
			$list = explode(',', $sort);
			foreach ($list as $s) {
				$s = trim($s);
				if (strpos($s, ' ')) {
					list($s, $o) = array_map('trim', explode(' ', $s));
				} else {
					$o = $order;
				}
				if (!in_array(strtolower($o), array('asc','desc'))) {
					$o = 'asc';
				}
				$sql .= ', ' . $this->db->_protect_identifiers($s) . ' ' . strtoupper($o);
			}
		}
		if (!empty($sql)) {
			// remove the leading ', ' 
			$sql = ' ORDER BY ' . substr($sql, 2);
		}
		return $sql;
	}
	
	/**
	 * Shortcut for LIMIT x,y. Returns a string for use in SQL queries.
	 */
	public function limit($limit, $start = null) {
		$sql = '';
		if ($limit and $start) {
			// 'LIMIT x OFFSET y' is compatible with PostgreSQL
			$sql = ' LIMIT ' . intval($limit) .
			       ' OFFSET ' . intval($start);
			//$sql = ' LIMIT ' . intval($start) ',' . intval($limit);
		} else if ($limit) {
			$sql = ' LIMIT ' . intval($limit);
		}
		return $sql;
	}

	/**
	 * Returns a SQL string that can be used within a WHERE clause.
	 * @param array $criteria An array of key => values that defines the
	 * 			  where expression. The key can have an optional
	 * 			  operator.
	 * @param string $glue Logical operator to glue where clause together.
	 * @param boolean $escape Should values be escaped?
	 * @param string $prefix Optional string to prepend to the result, only
	 * 			 if a SQL string is not going to be empty.
	 */
	public function where($criteria = array(), $glue = 'AND', $escape = true, $prefix = ' ') {
		if (empty($criteria)) {
			return '';
		}
		if (!is_array($criteria)) {
			if (!empty($criteria)) {
				// treat the value as a key string that is a
				// full logical expression w/o any explict
				// value.
				$criteria = array( $criteria => null );
			} else {
				return '';
			}
		}
		$glue = ' ' . trim($glue) . ' ';
		
		$sql = '';
		foreach ($criteria as $key => $val) {
			// $key is a numeric array index and val is a sub-array.
			// use the array from $val as our key => val pair. This
			// allows us to have multiple tests for the same key.
			// e.g.: array( array( 'key <>' => 1 ), array( 'key <>' => 2 ) )
			if (is_numeric($key)) {
				if (is_array($val)) {
					if (is_numeric(key($val))) {
						// there is no key, so use the
						// literal value.
						$key = current($val);
						$val = null;
					} else {
						$key = key($val);
						$val = current($val);
					}
				} else {
					// there is no actual key, so use $val
					// as a literal key+op string.
					$key = $val;
					$val = null;
				}
			}
			
			$has_op = $this->has_op($key);
			$where = ($has_op || !$escape) ? $key : $this->db->_protect_identifiers($key);
			
			if (!$has_op) {
				// No OP was given so default one depending on the value.
				$where .= is_null($val) ? ' IS NULL' : '=';
			}
			
			if (!is_null($val)) {
				if (is_bool($val)) {
					// convert boolean values into 1 or 0
					$val = $val ? 1 : 0;
				}
				$where .= $escape ? $this->db->escape($val) : $val;
			}
			
			$sql .= $where . $glue;
		}
		
		return $sql != '' ? $prefix . substr($sql, 0, -strlen($glue)) : '';
	}

	/**
	 * Returns true if the key string given has an operator embedded in it.
	 * @param string $key Key name to check.
	 */
	public function has_op($key) {
		$key = strtolower(trim($key));
		return preg_match('/[ <>!=]|is (?:not )?null|between/', $key) ? true : false;
	}

	/**
	 * Returns a fully qualified table name with proper prefix and suffix.
	 * @param string $tbl Base table name.
	 * @param string $gametype Optional gametype. Leave null for current default.
	 * @param string $modtype Optional modtype. Leave null for current default.
	 *
	 */
	public function tbl($tbl, $gametype = null, $modtype = null) {
		if ($gametype === null) {
			$gametype = $this->gametype;
		}
		if ($modtype === null) {
			$modtype = $this->modtype;
		}
		
		$tbl = $this->db->dbprefix($tbl);
		if ($gametype) {
			if ($modtype) {
				$tbl .= '_' . $gametype . '_' . $modtype;
			} else {
				$tbl .= '_' . $gametype;
			}
		}
		return $tbl;
	}
	
	/**
	 * Returns an array of column names for the table specified.
	 * @param boolean $keyed If true an assoc array is returned where the
	 * 			 key is the column name that points to a sub
	 * 			 array with the create info for the column.
	 * 			 Default is FALSE.
	 * @param array $exclude A list of column names to exclude from result.
	 */
	public function get_columns($tbl, $keyed = false, $exclude = array()) {
		static $cache = array();
		if (array_key_exists($tbl, $cache) and array_key_exists($keyed?1:0, $cache[$tbl])) {
			return $cache[$tbl][$keyed?1:0];
		}
		
		$q = $this->db->query("EXPLAIN $tbl");

		if (!is_array($exclude)) {
			$exclude = $exclude ? array($exclude) : array();
		}

		$res = array();
		if ($q->num_rows()) {
			foreach ($q->result_array() as $row) {
				if ($exclude and in_array($row['Field'], $exclude)) {
					// ignore excluded fields
					continue;
				}
				
				if ($keyed) {
					$res[ $row['Field'] ] = $row;
				} else {
					$res[] = $row['Field'];
				}
			}
		}
		$q->free_result();

		$cache[$tbl][$keyed?1:0] = $res;
		return $res;
		
	}

	/**
	 * Returns the is_ranked_sql string for players with a different
	 * prefix string.
	 * @param string $prefix Optional player prefix string.
	 *
	 */
	public function is_ranked_sql($prefix = 'plr') {
		if ($prefix == 'plr') {
			return $this->is_ranked_sql;
		}
		return str_replace('plr.', $prefix.'.', $this->is_ranked_sql);
	}

	/**
	 * Returns the is_not_ranked_sql string for players with a different
	 * prefix string.
	 * @param string $prefix Optional player prefix string.
	 *
	 */
	public function is_not_ranked_sql($prefix = 'plr') {
		if ($prefix == 'plr') {
			return $this->is_not_ranked_sql;
		}
		return str_replace('plr.', $prefix.'.', $this->is_not_ranked_sql);
	}

	/**
	 * Returns the is_clan_ranked_sql string for clans with a different
	 * prefix string.
	 * @param string $prefix Optional clan prefix string.
	 *
	 */
	public function is_clan_ranked_sql($prefix = 'clan') {
		if ($prefix == 'clan') {
			return $this->is_clan_ranked_sql;
		}
		return str_replace('clan.', $prefix.'.', $this->is_clan_ranked_sql);
	}

	/**
	 * Generates a new unique search string.
	 *
	 * @return  string  A new unique search ID.
	 */
	function init_search() {
		if (function_exists('uuid')) {
			$id = uuid(false);
		} else {
			$id = sha1(uniqid(rand(), true));
		}
		return $id;
	}

	/*
	 * Determines if the search id given is a valid search ID string.
	 * 
	 * @param  string  $search_id  Search ID string to validate.
	 * @return boolean Returns true if the search is valid.
	 */
	function is_search($search_id) {
		return preg_match('/^[a-z0-9-]{32,40}$/', $search_id);
	}

	/*
	 * Saves the results of a search.
	 * 
	 * @param  array    $search  Search paramters to save.
	 * @return boolean  Returns true if the search was saved, false otherwise.
	 */
	function save_search($search) {
		$tbl = $this->tbl('search_results', false);
		return $this->db->insert($tbl, $search);
	}

	/*
	 * Touches a search (updates it's timestamp)
	 * 
	 * @param  string $search_id  Search ID to touch.
	 */
	function touch_search($search_id) {
		$tbl = $this->tbl('search_results', false);
		return $this->db->query("UPDATE $tbl SET updated=NOW() WHERE search_id=?", $search_id);
	}


	/*
	 * Deletes the search results assoicated with the search ID given.
	 * 
	 * @param  string  $search_id  Search ID to delete
	 * @return boolean  True if successful
	 */
	function delete_search($search_id) {
		if ($this->is_search($search_id)) {
			$tbl = $this->tbl('search_results', false);
			return $this->db->delete($tbl, array( 'search_id' => $search_id ));
		}
		return false;
	}

	/*
	 * Deletes stale searches more than a few hours old.
	 * 
	 * @param  integer  $hours  Maximum hours allowed to be stale (defaults to 4)
	 */
	function delete_stale_searches($hours = 4) {
		if (!is_numeric($hours) or $hours < 0) $hours = 4;
		$tbl = $this->tbl('search_results', false);
		$this->db->query("DELETE FROM $tbl WHERE updated < NOW() - INTERVAL ? HOUR", $hours);
	}

	/*
	 * Returns a saved search result.
	 * 
	 * @param  string  $search_id  Search_id to load.
	 * @return array   Returns array of search results (empty array on failure)
	 */
	function get_search($search_id) {
		$res = array();
		if ($this->is_search($search_id)) {
			$tbl = $this->tbl('search_results', false);
			$sql = "SELECT * FROM $tbl WHERE search_id=? LIMIT 1";
			$q = $this->db->query($sql, $search_id);
			if ($q->num_rows()) {
				$res = $q->row_array();
				$res['results'] = explode(',', $res['results']);
			}
			$q->free_result();
		}
		return $res;
	}

	/*
	 * Converts the token string into a SQL string based on the $mode given.
	 * 
	 * @param  string  $str  The token string
	 * @param  string  $mode Token mode (contains, begins, ends, exact)
	 * @return string  Returns the string ready to be used in a SQL statement.
	 */
	function token_to_sql($str, $mode = 'contains') {
		$token = $this->db->escape_str($str);
		switch ($mode) {
			case 'exact':	return $token; break;
			case 'begins':	return $token . '%'; break;
			case 'ends':	return '%' . $token; break;
			default:
			case 'contains':return '%' . $token . '%'; break;
		}
	}
	
	/**
	 * Magic method to autoload Psychostats methods (lazy loading).
	 * $allow_autoload_methods must be enabled for this to work.
	 */
	public function __call($name, $args) {
		$loaded = true;
		if (!array_key_exists($name, $this->loaded_methods)) {
			if ($this->allow_autoload_methods) {
				if ($this->gametype) {
					$loaded = $this->load_overloaded_method($name, $this->gametype, $this->modtype);
				} else {
					$loaded = $this->load_method($name);
				}
			}
		}

		$ret = null;
		if (!array_key_exists($name, $this->loaded_methods)) {
				$caller = debug_backtrace();
				trigger_error('Call to undefined method '
					. get_class()
					. '::' . $name
					. '() in '
					. $caller[1]['file']
					. ' on line '
					. $caller[1]['line'],
					E_USER_ERROR
				);
		} else {
			$ret = call_user_func_array(array($this->loaded_methods[$name], 'execute'), $args);
		}
		return $ret;
	} 
}

/**
 * Base class for autoloaded Psychostats methods. A "Psychostats" method is
 * actually a class with a single "execute()" method that is called.
 */
class Psychostats_Method {
	// @var object Parent "PsychoStats" object that called the method.
	protected $ps;
	public function __construct($parent) {
		$this->ps = $parent;
	}
	
	public function execute() {
		// NOP
	}
}

?>