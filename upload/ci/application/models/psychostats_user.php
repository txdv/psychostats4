<?php
/**
 *	Main Psychostats User model.
 */

class Psychostats_user extends MY_Model {
	// @var array Stores the currently loaded user information. Only valid
	// column names belong in this array.
	// @access private
	private $user = array();
	
	// @var integer The ID of the currently loaded user.
	// @access private
	private $id = 0;
	
	// @var array What columns are allowed in the user table.
	// @access private
	private $valid_columns = array(
		'user_id', 'username', 'password', 'password_salt', 'email',
		'access_level', 'last_login', 'prev_login', 'session_salt'
	);
	// @var string The last error that occured, If any.
	// @access public
	public $error = null;
	
	/**
	 * Constructor.
	 * @param mixed $id
	 * 	An integer ID or username string for the user.
	 * @param string $key
	 * 	Which unique key to load user by (user_id or username).
	 * 	user_id is default.
	 * @access public
	 */
	public function __construct($id = null, $key = 'user_id') {
		parent::MY_Model();
		if (isset($id)) {
			if (!$this->load($id, $key)) {
				$this->error = 'User not found';
				log_message('debug', "Session error: " . $this->error . " ($key=$id)");
				throw new Exception($this->error);
			}
		}
	}
	
	/**
	 * Load a user based on their ID or username.
	 * @param mixed $id
	 * 	An integer ID or username string for the user.
	 * @param string $key
	 * 	Which unique key to load user by (user_id or username).
	 * 	user_id is default.
	 * @return mixed
	 * 	Returns an array of data for the loaded user if the user was
	 * 	loaded successfully. Returns false otherwise.
	 * @access public
	 */
	public function load($id, $key = 'user_id') {
		$this->error = '';
		if (!in_array($key, $this->valid_columns)) {
			$this->error = 'Invalid user column key';
			log_message('debug', "User error: $this->error ($key)");
			//throw new Exception($this->error);
			return false;
		}
		
		$q = $this->db->get_where('user', array( $key => $id ));
		if ($q->num_rows() == 0) {
			$this->error = 'User not found';
			log_message('debug', "User error: $this->error ($key=$id)");
			//throw new Exception($this->error);
			return false;
		}
		
		$this->user = $q->row_array();
		return $this->user;
	}
	
	/**
	 * Saves the user to the database.
	 * @param array $data
	 * 	New set of data to save
	 * @access public
	 */
	public function save($data = array()) {
		$this->error = '';
		$set = array();
		if (is_array($data)) {
			// determine what columns are changed
			foreach ($data as $key => $value) {
				if (!isset($this->user[$key]) or $value != $this->user[$key]) {
					$set[$key] = $value;
				}
			}
		}
		
		// nothing to save, so we're done.
		if (!count($set)) {
			return true;
		}

		// sanitize the password_salt to NULL if its empty
		if (isset($set['password_salt']) and $set['password_salt'] == '') {
			$set['password_salt'] = null;
		}

		// determine our user_id
		$new = false;
		$id = $this->id();
		if (!$id) {
			$new = true;
			$id = $this->next_id();
			if (!$id) {
				$this->error = 'Error determining user ID';
				log_message('debug', "User error: $this->error");
				//throw new Exception($this->error);
				return false;
			}
		}
		
		// insert or update the user record
		if ($new) {
			// username and password must be present for new users
			if (!isset($set['username']) or !isset($set['password'])) {
				$this->error = 'Username and password are required for new users';
				throw new Exception($this->error);
				return false;
			}
			
			// the username must be unique
			if ($this->username_exists($set['username'])) {
				$this->error = 'Username already exists';
				throw new Exception($this->error);
				return false;
			}
			
			$set['user_id'] = $id;
			$this->db->insert('user', $set);
		} else {
			$this->db->where('user_id', $id);
			$this->db->update('user', $set);
		}
		
		$this->user = array_merge($this->user, $set);
		
		return true;
	}

	/**
	 * Returns true/false if the username specified already exists
	 * @param string $username The username to check against.
	 * @return boolean Returns true if the username already exists.
	 */
	public function username_exists($username) {
		$this->db->select('user_id');
		$q = $this->db->get_where('user', array('username' => $username));
		return ($q->num_rows() != 0);
	}

	/**
	 * Returns a new random salt string.
	 * @param integer $length Length of salt to generate.
	 * @param string $chars A string of characters for generating the random string.
	 * @return string Returns a random string of characters.
	 */
	public function generate_salt($length = 8, $chars = '') {
		if (!is_string($chars) or $chars == '') {
			$chars = '1234567890qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM';
		}
		$salt = '';
		$max = strlen($chars) - 1;
		while (strlen($salt) < $length) {
			$salt .= $chars[ mt_rand(0, $max) ];
		}
		return $salt;
	}

	/**
	 * Returns true/false if the username and password provided authenticate
	 * against a user in the database.
	 * @param string $username Username to auth against.
	 * @param string $password Password to auth against.
	 * @param boolean $is_hashed (optional; default=false) If true the
	 * 	password is considered to already be a proper hash for
	 * 	authenticating against.
	 */
	public function auth($username, $password, $is_hashed = false) {
		if (!$is_hashed) {
			$salt = '';
			// load the current password_salt for the username.
			$this->db->select('password_salt');
			$q = $this->db->get_where('user', array('username' => $username), 1);
			if ($q->num_rows() == 0) {
				// username doesn't exist
				return false;
			}
			$r = $q->row();
			$salt = $r->password_salt;
			// hash the password
			$password = $this->hash($password, $salt);
		}
		$this->db->select('user_id');
		$q = $this->db->get_where('user', array('username' => $username, 'password' => $password), 1);
		if ($q->num_rows() == 0) {
			// the username + password combination failed
			return false;
		}
		$q->free_result();
		
		// authentication succeeded!
		return true;
	}

	/**
	 * Returns true/false if the user_id and password provided authenticate.
	 * This is different from the normal auth() method in that it expects
	 * the password to have some extra salt on it so the DB password has to
	 * be rehashed in order to match properly.
	 */
	public function auth_remembered($user_id, $password, $salt = null) {
		// first load the user on the user_id so we can get its session_salt
		// if no $salt was given.
		$this->db->select('password, password_salt, session_salt');
		$q = $this->db->get_where('user', array( 'user_id' => $user_id ));
		if ($q->num_rows() == 0) {
			return false;
		}
		$u = $q->row();
		$q->free_result();

		if ($salt === null) {
			$salt = $u->session_salt;
		}
		
		// if there is no salt specified then we do not allow the auth
		// to succeed. This way only users who specifically check the
		// "remember me" box on login will authenticate this way.
		if (!$salt) {
			return false;
		}

		$user_pw = $this->hash($u->password, $salt);
		return ($user_pw == $password);
	}

	/**
	 * Hash a password (or any string) using a pre-determined hashing
	 * function and optional salt.
	 * @param string $str String to hash.
	 * @param string $salt (optional) Salt to add to string for hashing.
	 */
	public function hash($str, $salt = '') {
		if (!is_string($salt)) {
			$salt = '';
		}
		return md5($salt . $str . strrev($salt));
	}

	/**
	 * @return integer
	 *      Returns the ID (user_id) of the current user or FALSE if no user
	 *	is loaded.
	 * @param boolean $next
	 * 	If true next_id() will be called if no current user_id is set.
	 * @access public
	 */
	public function id($next = false) { // read-only
		$id = isset($this->user['user_id']) ? $this->user['user_id'] : false;
		if (!$id and $next) {
			$id = $this->next_id();
		}
		return $id;
	}
	
	/**
	 * @return integer Returns the next user_id available.
	 * @access public
	 */
	public function next_id() {
		$this->db->select_max('user_id');
		$q = $this->db->get('user');
		if ($q->num_rows() == 0) {
			return false;
		}
		$r = $q->row();
		$id = $r->user_id + 1;
		return $id;
	}
	
	/**
	 * Returns the user data array that was loaded.
	 * @return array Returns the user data array that was loaded.
	 */
	public function data($key = null) {
		if (isset($key)) {
			return isset($this->user[$key]) ? $this->user[$key] : false;
		} else {
			return $this->user;
		}
	}
	
	/**
	 * Returns true if the user is currently logged in
	 * @return boolean True/false if the user has a logged in session
	 */
	public function logged_in() {
		$ci =& get_instance();
		// if the user_id is available in the session then the user
		// is logged in.
		return $ci->session->userdata('user_id') ? true : false;
	}
	
	function __get($name) {
		return isset($this->user[$name]) ? $this->user[$name] : false;
	}
	
}

?>