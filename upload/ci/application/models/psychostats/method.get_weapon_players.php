<?php
/**
 * PsychoStats method get_weapon_players()
 * $Id$
 *
 */
class Psychostats_Method_Get_Weapon_Players extends Psychostats_Method {
	/**
	 * Fetches a list of players that have used a specific weapon.
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
		// set defaults
		$criteria += array(
			'id'		=> 0,		// weaponid
			'select' 	=> null,
			'select_overload_method' => 'get_weapon_players_sql',
			'limit' 	=> null,
			'start' 	=> null,
			'order' 	=> null,
			'where' 	=> null,
			'is_ranked' 	=> null,
		);
		
		$ci =& get_instance();
		if ($gametype === null) {
			$gametype = $this->ps->gametype();
		}
		if ($modtype === null) {
			$modtype = $this->ps->modtype();
		}

		if (empty($criteria['select'])) {
			$criteria['select'] = array(
				'plr.*, pp.*, d.*',
				'ROUND(IFNULL(d.kills / d.deaths, 0),2) kills_per_death',
			);
		}

		// setup table names
		$t_plr = $this->ps->tbl('plr', false);
		$t_plr_profile = $this->ps->tbl('plr_profile', false);
		$t_plr_weapons = $ci->db->dbprefix('c_plr_weapons_' . $gametype);
		if ($modtype) {
			$t_plr_weapons .= '_' . $modtype;
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
		$cmd =
<<<CMD
		SELECT $fields
		FROM $t_plr_weapons d, $t_plr plr, $t_plr_profile pp
		WHERE d.weaponid=? AND plr.plrid=d.plrid AND pp.uniqueid=plr.uniqueid
CMD;

		$sql .= $this->ps->where($criteria['where'], 'AND', true, ' AND ');
		$cmd .= $this->ps->order_by($criteria['sort'], $criteria['order']);
		$cmd .= $this->ps->limit($criteria['limit'], $criteria['start']);

		$q = $ci->db->query($cmd, array($criteria['id']));

		$list = array();		
		if ($q->num_rows()) {
			$list = $q->result_array();
		}
		$q->free_result();
		return $list;
	}
} 

?>