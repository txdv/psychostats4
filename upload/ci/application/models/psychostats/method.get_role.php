<?php
/**
 * PsychoStats method get_role()
 * $Id$
 *
 * Fetches a single role record.
 *
 */

class Psychostats_Method_Get_Role extends Psychostats_Method {
	public function execute($criteria = array()) {
		if (!is_array($criteria)) {
			$criteria = array( 'id' => $criteria );
		}
		// set defaults
		$criteria += array(
			'id'		=> 0,
			'force_string'	=> false,
		);
		
		$id = isset($criteria['id']) ? $criteria['id'] : 0;

		$t_role = $this->ps->tbl('role', false);

		// Match the record based on the numeric ID or its name
		$key = (is_numeric($criteria['id']) and !$criteria['force_string'])
			? 'roleid'
			: 'name';
		
		// load the basic record first.
		$sql = 
<<<CMD
		SELECT role.*, COALESCE(full_name, name) long_name
		FROM $t_role role
		WHERE role.$key = ?
CMD;

		$ci =& get_instance();
		$q = $ci->db->query($sql, $id);

		if ($q->num_rows() == 0) {
			// not found
			return false;
		}

		$res = $q->row_array();
		$q->free_result();

		return $res;
	} 
} 

?>
