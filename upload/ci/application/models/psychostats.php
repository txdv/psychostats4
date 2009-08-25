<?php
/**
 *	Main Psychostats model.
 */

class Psychostats extends Model {
	// @var string Path to directory where method files are located.
	public $methods_dir = '';
	// @var boolean Allow methods to be autoloaded if true (default).
	// If false load_method() must be called explicitly.
	public $allow_autoload_methods = true;
	// @var string SQL string used to limit SQL results to players that are ranked.
	public $is_ranked_sql = '(rank IS NOT NULL AND rank <> 0)';
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

		$ci =& get_instance();
		$tbl = $this->tbl($tbl, false);
		$sql = "SELECT gametype, modtype FROM $tbl WHERE $key = ? LIMIT 1";
		$q = $ci->db->query($sql, $id);
		
		if ($q->num_rows() == 0) {
			// not found
			return false;
		}

		$r = null;
		if ($ret_obj) {
			$r = $q->row();
		} else {
			$r = $g->row_array();
		}
		$q->free_result();
		return $r;
	}
	

	/**
	 * Shortcut for ORDER BY x y.
	 * $sort can be a comma separated string of fields to sort and the
	 * $order will be added, as needed. 
	 */
	public function order_by($sort, $order = 'asc') {
		$ci =& get_instance();
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
				$sql .= ', ' . $ci->db->_protect_identifiers($s) . ' ' . strtoupper($o);
			}
		}
		if (!empty($sql)) {
			// remove the leading ', ' 
			$sql = ' ORDER BY ' . substr($sql, 2);
		}
		return $sql;
	}
	
	/**
	 * Shortcut for LIMIT x,y
	 */
	public function limit($limit, $start = null) {
		$ci =& get_instance();
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
		
		$ci =& get_instance();
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
			$where = ($has_op || !$escape) ? $key : $ci->db->_protect_identifiers($key);
			
			if (!$has_op) {
				// No OP was given so default one depending on the value.
				$where .= is_null($val) ? ' IS NULL' : '=';
			}
			
			if (!is_null($val)) {
				if (is_bool($val)) {
					// convert boolean values into 1 or 0
					$val = $val ? 1 : 0;
				}
				$where .= $escape ? $ci->db->escape($val) : $val;
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


	public function tbl($tbl, $gametype = null, $modtype = null) {
		if ($gametype === null) {
			$gametype = $this->gametype;
		}
		if ($modtype === null) {
			$modtype = $this->modtype;
		}
		
		$ci =& get_instance();
		$tbl = $ci->db->dbprefix($tbl);
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
	 * Magic method to autoload Psychostats methods (lazy loading).
	 * $allow_autoload_methods must be enabled for this to work.
	 */
	public function __call($name, $args) {
		if (!array_key_exists($name, $this->loaded_methods)) {
			if ($this->allow_autoload_methods) {
				if ($this->gametype) {
					$this->load_overloaded_method($name, $this->gametype, $this->modtype);
				} else {
					$this->load_method($name);
				}
			} else {
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
				return;
			}
		}
		$ret = call_user_func_array(array($this->loaded_methods[$name], 'execute'), $args);
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