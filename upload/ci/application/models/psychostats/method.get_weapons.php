<?php
/**
 * PsychoStats method get_weapons()
 * $Id$
 *
 */
class Psychostats_Method_Get_Weapons extends Psychostats_Method {
	/**
	 * Fetches a list of weapon stats for a specific game.
	 * 
	 * @param array $criteria
	 * 	Criteria that defines what weapons will be returned.
	 * @param string $gametype
	 * 	Game type to fetch weapons list for. Leave null for current
	 * 	default (from set_gametype).
	 * @param string $modtype
	 * 	Mod type to fetch weapons list for. Leave null for current
	 * 	default (from set_gametype or set_modtype)
	 */
	public function execute($criteria = array(), $gametype = null, $modtype = null) {
		// set defaults
		$criteria += array(
			'select' 	=> null,
			'select_overload_method'=> 'get_weapons_sql',
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
		$t_weapon = $this->ps->tbl('weapon', false);
		$c_weapon_data = $ci->db->dbprefix('c_weapon_data_' . $gametype);
		if ($modtype) {
			$c_weapon_data .= '_' . $modtype;
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
		$sql = "SELECT $fields FROM $t_weapon weapon, $c_weapon_data d WHERE ";

		// add join clause for tables
		$criteria['where'][] = 'd.weaponid=weapon.weaponid';
		
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