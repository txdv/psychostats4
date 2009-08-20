<?php
/**
 * PsychoStats method get_roles()
 * $Id: method.get_roles.php 624 2009-08-20 11:16:44Z lifo $
 *
 */
class Psychostats_Method_Get_Roles extends Psychostats_Method {
	/**
	 * Fetches a list of role stats for a specific game.
	 * 
	 * @param array $criteria
	 * 	Criteria that defines what roles will be returned.
	 * @param string $gametype
	 * 	Game type to fetch roles list for. Leave null for current
	 * 	default (from set_gametype).
	 * @param string $modtype
	 * 	Mod type to fetch roles list for. Leave null for current
	 * 	default (from set_gametype or set_modtype)
	 */
	public function execute($criteria = array(), $gametype = null, $modtype = null) {
		// set defaults
		$criteria += array(
			'select' 	=> null,
			'select_overload_method'=> 'get_roles_sql',
			'limit' 	=> null,
			'start' 	=> null,
			'sort'		=> null,
			'order' 	=> null,
			'where' 	=> null,
		);
		
		$ci =& get_instance();
		if ($gametype === null) {
			$gametype = $this->ps->gametype();
		}
		if ($modtype === null) {
			$modtype = $this->ps->modtype();
		}

		// setup table names
		$t_role = $this->ps->tbl('role', false);
		$c_role_data = $ci->db->dbprefix('c_role_data_' . $gametype);
		if ($modtype) {
			$c_role_data .= '_' . $modtype;
		}

		// allow game::mod specific stats to be added
		$stats = $criteria['select'];
		if ($meth = $this->ps->load_overloaded_method($criteria['select_overload_method'], $gametype, $modtype)) {
			$meth->execute($stats);
		}
		
		// combine everything into a string for our query
		$fields = is_array($stats) ? implode(',', $stats) : $stats;
		if (empty($fields)) {
			$fields = '*';
		}

		// start basic query
		$sql = "SELECT $fields FROM $t_role role, $c_role_data d WHERE ";

		// add join clause for tables
		$criteria['where'][] = 'd.roleid=role.roleid';
		
		// apply sql clauses
		$sql .= $this->ps->where($criteria['where']);
		$sql .= $this->ps->order_by($criteria['sort'], $criteria['order']);
		$sql .= $this->ps->limit($criteria['limit'], $criteria['start']);

		$q = $ci->db->query($sql);

		$res = array();
		if ($q->num_rows()) {
			foreach ($q->result_array() as $row) {
				$res[] = $row;
			}
		}
		$q->free_result();

		return $res;
	}
} 

?>