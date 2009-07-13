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
	 * Load a method into the class. Throws an Exception if loading fails.
	 * @param mixed $name Method name (or array of names) to load.
	 */
	public function load_method($name, $methods_dir = null, $missing_allowed = false) {
		if (is_array($name)) {
			foreach ($name as $n) {
				$this->load_method($n, $methods_dir, $missing_allowed);
			}
			return true;
		}
		
		if (is_null($methods_dir)) {
			$methods_dir = $this->methods_dir;
		}
		
		$class_name = 'Psychostats_Method_' . $name;
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
				throw new Exception("Method file $filename does not define class $class_name");
			}
			$this->loaded_methods[$name] = new $class_name($this);
		}
		return true;
	}

	public function mod_table($table, $func, $gametype, $modtype = null) {
		$parts = array( $gametype );
		if ($modtype) {
			$parts[] = $modtype;
		}

		while (count($parts)) {
			$methods_dir = $this->methods_dir .
				implode(DIRECTORY_SEPARATOR, $parts) .
				DIRECTORY_SEPARATOR;
			$name = 'mod_table_' . $func;

			// attempt to load the method and execute it
			if ($this->load_method($name, $methods_dir, true)) {
				return $this->__call($name, array( $table, $gametype, $modtype ));
			}

			array_pop($parts);
		}
	}

	/**
	 * Returns the gametype and modtype for the player that matches the ID.
	 * @param integer $id 	The plrid of the player to match.
	 * @param boolean $ary 	If true a 2 element array is returned, false (default)
	 * 			an object is returned instead.
	 * @return mixed 	FALSE on failure, or either a 2 element array or an object.
	 */
	public function get_player_gametype($id, $ary = false) {
		// return preset gametype/modtype if available
		if ($this->gametype !== null) {
			return array($this->gametype, $this->modtype);
		}

		$ci =& get_instance();
		$t_plr = $this->tbl('plr', false);
		$sql = "SELECT gametype, modtype FROM $t_plr WHERE plrid = ? LIMIT 1";
		$q = $ci->db->query($sql, $id);
		
		if ($q->num_rows() == 0) {
			// player not found
			return false;
		}

		if ($ary) {
			$r = $g->row_array();
		} else {
			$r = $q->row();
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
			$sql = ' LIMIT ' . intval($limit) .
			       ' OFFSET ' . intval($start);
		} else if ($limit) {
			$sql = ' LIMIT ' . intval($limit);
		}
		return $sql;
	}

	public function tbl($tbl, $gametype = null, $modtype = null) {
		$ci =& get_instance();
		if ($gametype === null) {
			$gametype = $this->gametype;
		}
		if ($modtype === null) {
			$modtype = $this->modtype;
		}
		
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
				$this->load_method($name);
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