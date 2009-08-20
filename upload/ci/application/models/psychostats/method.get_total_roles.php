<?php
/**
 * PsychoStats method get_total_roles()
 * $Id: method.get_total_roles.php 624 2009-08-20 11:16:44Z lifo $
 *
 * Returns the total roles available based on the criteria given.
 *
 */

class Psychostats_Method_Get_Total_Roles extends Psychostats_Method {
	public function execute($criteria = array(), $gametype = null, $modtype = null) {
		// set defaults
		if (!is_array($criteria)) {
			$criteria = array();
		}
		$criteria += array(
			'where' 	=> null
		);

		$ci =& get_instance();
		if ($gametype === null) {
			$gametype = $this->ps->gametype();
		}
		if ($modtype === null) {
			$modtype = $this->ps->modtype();
		}

		$t_role = $this->ps->tbl('role', false);
		$c_role_data = $ci->db->dbprefix('c_role_data_' . $gametype);
		if ($modtype) {
			$c_role_data .= '_' . $modtype;
		}
		
		// start basic query
		$sql = "SELECT COUNT(*) total FROM $t_role role,$c_role_data d WHERE ";
		
		// add join clause for tables
		$criteria['where'][] = 'd.roleid=role.roleid';

		$sql .= $this->ps->where($criteria['where']);

		$q = $ci->db->query($sql);

		$count = 0;
		if ($q->num_rows()) {
			$res = $q->row_array();
			$count = $res['total'];
		}
		$q->free_result();

		return $count;
	} 
} 

?>
