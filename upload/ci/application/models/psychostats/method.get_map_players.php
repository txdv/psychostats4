<?php
/**
 * PsychoStats method get_map_players()
 * $Id$
 *
 */
class Psychostats_Method_Get_Map_Players extends Psychostats_Method {
	/**
	 * Fetches a list of players that have used a specific map.
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
			'id'		=> 0,		// mapid
			'limit' 	=> null,
			'start' 	=> null,
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

		if (empty($criteria['select'])) {
			$stats = $this->get_sql();
		} else {
			$stats = $criteria['select'];
		}

		// setup table names
		$t_plr = $this->ps->tbl('plr', false);
		$t_plr_profile = $this->ps->tbl('plr_profile', false);
		$t_plr_maps = $this->ps->tbl('c_plr_maps', $gametype, $modtype);

		$stats = $criteria['select'] ? $criteria['select'] : $this->get_sql();
		$fields = is_array($stats) ? implode(',', $stats) : $stats;

		// start basic query
		$cmd =
<<<CMD
		SELECT $fields
		FROM $t_plr_maps d, $t_plr plr, $t_plr_profile pp
		WHERE d.mapid=? AND plr.plrid=d.plrid AND pp.uniqueid=plr.uniqueid
CMD;

		$cmd .= $this->ps->where($criteria['where'], 'AND', true, ' AND ');
		$cmd .= $this->ps->order_by($criteria['sort'], $criteria['order']);
		$cmd .= $this->ps->limit($criteria['limit'], $criteria['start']);

		$q = $ci->db->query($cmd, $criteria['id']);

		$list = array();		
		if ($q->num_rows()) {
			foreach ($q->result_array() as $row) {
				// remove id so it doesn't cause problems with
				// some url generation.
				unset($row['mapid']);
				$list[] = $row;
			}
		}
		$q->free_result();
		return $list;
	}

	protected function get_sql() {
		$sql = array(
			'plr' => 'plr.*',
			'pp' => 'pp.*',
			'd' => 'd.*',
			'kills_per_death' => 'ROUND(IFNULL(d.kills / d.deaths, 0),2) kills_per_death',
		);
		return $sql;
	}
} 

?>
