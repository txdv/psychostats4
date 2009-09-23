<?php
/**
 * PsychoStats method get_clan_players()
 * $Id$
 *
 */
class Psychostats_Method_Get_Clan_Players extends Psychostats_Method {
	/**
	 * Fetches a list of player stats (members) for a specific clan.
	 * 
	 * @param array $criteria
	 * 	Criteria that defines what players will be returned.
	 */
	public function execute($criteria = array(), $gametype = null, $modtype = null) {
		$ci =& get_instance();
		// set defaults
		$criteria += array(
			'id'		=> 0,		// clanid
			'select' 	=> null,
			'limit' 	=> null,
			'start' 	=> null,
			'sort'		=> null,
			'order' 	=> null,
			'where' 	=> null,
			'is_ranked' 	=> true,	// true
		);
		$id = isset($criteria['id']) ? $criteria['id'] : 0;

		$res = array();

		if (!$gametype) {
			$g = $this->ps->get_clan_gametype($id);
			if (!$g) {
				return false;
			}
			list($gametype, $modtype) = $g;
		}

		// setup table names
		$t_plr = $this->ps->tbl('plr', false);
		$t_plr_profile = $this->ps->tbl('plr_profile', false);
		$c_plr_data = $this->ps->tbl('c_plr_data', $gametype, $modtype);

		$stats = $criteria['select'] ? $criteria['select'] : $this->get_sql();
		$fields = is_array($stats) ? implode(',', $stats) : $stats;

		$cmd =
<<<CMD
		SELECT $fields
		FROM ($c_plr_data d, $t_plr plr)
		LEFT JOIN $t_plr_profile pp ON plr.uniqueid=pp.uniqueid
		WHERE plr.clanid=?
CMD;
		//$cmd = preg_replace('/^\s+/m', '', $cmd); // remove leading whitespace (I'm OCD)

		// add join clause for tables
		$criteria['where'][] = 'plr.plrid=d.plrid';

		// apply is_ranked shortcut
		if ($criteria['is_ranked']) {
			$criteria['where'][] = $this->ps->is_ranked_sql;
		}
		
		// apply sql clauses
		$cmd .= $this->ps->where($criteria['where'], 'AND', true, ' AND ');
		$cmd .= $this->ps->order_by($criteria['sort'], $criteria['order']);
		$cmd .= $this->ps->limit($criteria['limit'], $criteria['start']);

		$q = $ci->db->query($cmd, $id);

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
		$sql = array(
			// basic player information
			'plr' 	=> 'plr.plrid, plr.uniqueid, plr.activity, plr.clanid, ' .
				   'plr.skill, plr.skill_prev, plr.rank, plr.rank_prev',
			'pp' 	=> 'pp.name, pp.avatar, pp.cc',

			// static stats
			'static' => 'kills, deaths, headshot_kills, online_time',

			// calculated stats
			'kills_per_death' 	=> 'ROUND(IFNULL(kills / deaths, 0), 2) kills_per_death',
			'kills_per_minute' 	=> 'ROUND(IFNULL(kills / (online_time/60), 0), 2) kills_per_minute', 
			'headshot_kills_pct' 	=> 'ROUND(IFNULL(headshot_kills / kills * 100, 0), 0) headshot_kills_pct',
		);

		return $sql;
	}
} 

?>