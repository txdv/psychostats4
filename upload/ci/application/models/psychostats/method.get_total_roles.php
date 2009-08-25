<?php
/**
 * PsychoStats method get_total_roles()
 * $Id$
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
		$c_role_data = $this->ps->tbl('c_role_data', $gametype, $modtype);
		
		// start basic query
		$cmd = "SELECT COUNT(*) total FROM $c_role_data d,$t_role role WHERE ";
		
		// add join clause for tables
		$criteria['where'][] = 'd.roleid=role.roleid';

		$cmd .= $this->ps->where($criteria['where']);

		$q = $ci->db->query($cmd);

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
