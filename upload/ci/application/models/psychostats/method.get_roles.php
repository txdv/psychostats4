<?php
/**
 * PsychoStats method get_roles()
 * $Id$
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
		if (!$this->ps->has_roles()) {
			return array();
		}
		
		// set defaults
		$criteria += array(
			'limit' 	=> null,
			'start' 	=> null,
			'sort'		=> null,
			'order' 	=> null,
			'where' 	=> null,
			'select' 	=> null,
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
		$c_role_data = $this->ps->tbl('c_role_data', $gametype, $modtype);

		$stats = $criteria['select'] ? $criteria['select'] : $this->get_sql();
		$fields = is_array($stats) ? implode(',', $stats) : $stats;
		
		$cmd = "SELECT $fields FROM $t_role role, $c_role_data d WHERE ";

		// add join clause for tables
		$criteria['where'][] = 'd.roleid=role.roleid';
		
		// apply sql clauses
		$cmd .= $this->ps->where($criteria['where']);
		$cmd .= $this->ps->order_by($criteria['sort'], $criteria['order']);
		$cmd .= $this->ps->limit($criteria['limit'], $criteria['start']);

		$q = $ci->db->query($cmd);

		$res = array();
		if ($q->num_rows()) {
			foreach ($q->result_array() as $row) {
				$res[] = $row;
			}
		}
		$q->free_result();

		return $res;
	}

	protected function get_sql() {
		$c_roles = $this->ps->tbl('c_role_data');

		// non game specific stats		
		$sql = array(
			'*' 			=> 'd.*',
			'role' 			=> 'name, full_name, COALESCE(full_name, name) long_name',

			'kills_per_death' 	=> 'ROUND(IFNULL(kills / deaths, 0), 2) kills_per_death',
			'kills_scaled_pct' 	=> "IFNULL(d.kills / (SELECT MAX(d3.kills) FROM $c_roles d3) * 100, 0) kills_scaled_pct",
			'kills_pct' 		=> "IFNULL(d.kills / (SELECT SUM(d2.kills) FROM $c_roles d2) * 100, 0) kills_pct",
			'headshot_kills_pct' 	=> 'ROUND(IFNULL(headshot_kills / kills * 100, 0), 0) headshot_kills_pct',
		);
		return $sql;
	}
} 

?>