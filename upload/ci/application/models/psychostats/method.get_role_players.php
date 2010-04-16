<?php
/**
 * PsychoStats method get_role_players()
 * $Id$
 *
 */
class Psychostats_Method_Get_Role_Players extends Psychostats_Method {
	/**
	 * Fetches a list of players that have used a specific role.
	 * 
	 * @param array $criteria
	 * 	Criteria that defines what players will be returned.
	 * @param string $gametype
	 * 	Game type to fetch players list for. Leave null for current
	 * 	default (from set_gametype).
	 * @param string $modtype
	 * 	Mod type to fetch players list for. Leave null for current
	 * 	default (from set_gametype or set_modtype)
	 */
	public function execute($criteria = array(), $gametype = null, $modtype = null) {
		if (!$this->ps->has_roles()) {
			return array();
		}
		
		// set defaults
		$criteria += array(
			'id'		=> 0,		// roleid
			'limit' 	=> null,
			'start' 	=> null,
			'order' 	=> null,
			'where' 	=> null,
			'is_ranked' 	=> null,
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
		$t_plr = $this->ps->tbl('plr', false);
		$t_plr_profile = $this->ps->tbl('plr_profile', false);
		$t_plr_roles = $this->ps->tbl('c_plr_roles', $gametype, $modtype);

		$stats = $criteria['select'] ? $criteria['select'] : $this->get_sql();
		$fields = is_array($stats) ? implode(',', $stats) : $stats;

		$cmd =
<<<CMD
		SELECT $fields
		FROM $t_plr_roles d, $t_plr plr, $t_plr_profile pp
		WHERE d.roleid=? AND plr.plrid=d.plrid AND pp.uniqueid=plr.uniqueid
CMD;

		// apply is_ranked shortcut
		if ($criteria['is_ranked']) {
			$criteria['where'][] = $this->ps->is_ranked_sql;
		}
		
		$cmd .= $this->ps->where($criteria['where'], 'AND', true, ' AND ');
		$cmd .= $this->ps->order_by($criteria['sort'], $criteria['order']);
		$cmd .= $this->ps->limit($criteria['limit'], $criteria['start']);

		$q = $ci->db->query($cmd, $criteria['id']);

		$list = array();		
		if ($q->num_rows()) {
			foreach ($q->result_array() as $row) {
				// remove id so it doesn't cause problems with
				// some url generation.
				unset($row['roleid']);
				$list[] = $row;
			}
		}
		$q->free_result();
		return $list;
	}

	protected function get_sql() {
		// non game specific stats
		$sql = array(
			'*' => 'd.*', 
			'plr' => 'plr.*',
			'pp' => 'pp.*', 
			'kills_per_death' => 'ROUND(IFNULL(kills / deaths, 0),2) kills_per_death',
		);
		return $sql;
	}
} 

?>