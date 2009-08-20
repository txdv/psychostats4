<?php
/**
 * PsychoStats method get_players()
 * $Id$
 *
 */
class Psychostats_Method_Get_Players extends Psychostats_Method {
	/**
	 * Fetches a list of player stats for a specific game.
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
			'select' 	=> null,
			'select_overload_method'=> 'get_players_sql',
			'limit' 	=> null,
			'start' 	=> null,
			'sort'		=> null,
			'order' 	=> null,
			'where' 	=> null,
			'is_ranked' 	=> true,	// true
		);
		
		$ci =& get_instance();
		if ($gametype === null) {
			$gametype = $this->ps->gametype();
		}
		if ($modtype === null) {
			$modtype = $this->ps->modtype();
		}

		// add the game::mod to our where clause
		if ($gametype) {
			$criteria['where']['gametype'] = $gametype;
			$criteria['where']['modtype']  = $modtype;
		}

		// setup table names
		$t_plr = $this->ps->tbl('plr', false);
		$t_plr_profile = $this->ps->tbl('plr_profile', false);
		$c_plr_data = $ci->db->dbprefix('c_plr_data_' . $gametype);
		if ($modtype) {
			$c_plr_data .= '_' . $modtype;
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
		$sql =
<<<CMD
		SELECT $fields
		FROM ($t_plr plr, $c_plr_data d)
		LEFT JOIN $t_plr_profile pp ON plr.uniqueid=pp.uniqueid
		WHERE
CMD;
		//$sql = preg_replace('/^\s+/m', '', $sql); // remove leading whitespace (I'm OCD)

		// add join clause for tables
		$criteria['where'][] = 'plr.plrid=d.plrid';

		// apply is_ranked shortcut
		if ($criteria['is_ranked']) {
			$criteria['where'][] = $this->ps->is_ranked_sql;
		}
		
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