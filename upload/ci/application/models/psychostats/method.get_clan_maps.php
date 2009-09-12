<?php
/**
 * PsychoStats method get_clan_maps()
 * $Id$
 *
 */
class Psychostats_Method_Get_Clan_Maps extends Psychostats_Method {
	/**
	 * Fetches a list of map stats for a specific clan.
	 * 
	 * @param array $criteria
	 * 	Criteria that defines what maps will be returned.
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
		$this->clanid = $id;		// used in get_sql()
		$this->criteria = $criteria; 	// used in get_sql()

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
		$t_map = $this->ps->tbl('map', false);
		$c_plr_maps = $this->ps->tbl('c_plr_maps', $gametype, $modtype);

		$stats = $criteria['select'] ? $criteria['select'] : $this->get_sql();
		$fields = is_array($stats) ? implode(',', $stats) : $stats;

		$cmd =
<<<CMD
		SELECT $fields
		FROM ($c_plr_maps d, $t_plr plr, $t_map map)
		WHERE plr.plrid=d.plrid AND d.mapid=map.mapid AND plr.clanid=?
CMD;
		//$cmd = preg_replace('/^\s+/m', '', $cmd); // remove leading whitespace (I'm OCD)

		// apply is_ranked shortcut
		if ($criteria['is_ranked']) {
			$criteria['where'][] = $this->ps->is_ranked_sql;
		}

		// apply sql clauses
		$cmd .= $this->ps->where($criteria['where'], 'AND', true, ' AND ');

		// group results
		$cmd .= " GROUP BY d.mapid ";

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
		$t_plr = $this->ps->tbl('plr', false);
		$t_maps = $this->ps->tbl('c_plr_maps', $this->ps->gametype(), $this->ps->modtype());
		$t_data = $this->ps->tbl('c_plr_data', $this->ps->gametype(), $this->ps->modtype());

		$is_ranked = $this->criteria['is_ranked'] ? 'AND ' . $this->ps->is_ranked_sql('p') : '';

		$sql = array(
			'*'	=> '',
			// basic map information
			'map'	=> 'map.*',

			// UGLY QUERY! Returns the scaled percentage of kills
			// for the clan's maps... This shouldn't be slow even
			// for very large player databases since the results
			// will be limited by the indexes. But realistically
			// it's still very ugly and isn't as efficient as it
			// could potentially be.
			'kills_scaled_pct' 	=>
				"IFNULL(SUM(d.kills) / 
				(SELECT MAX(max_kills) FROM 
					(
					SELECT SUM(kills) max_kills 
					FROM $t_maps d3, $t_plr p 
					WHERE p.plrid=d3.plrid $is_ranked AND p.clanid=$this->clanid
					GROUP BY d3.mapid
					) kill_list
				) * 100, 0) kills_scaled_pct",
			'kills_pct' 		=> "IFNULL(SUM(d.kills) / (SELECT SUM(d2.kills) FROM $t_data d2, $t_plr p WHERE p.plrid=d2.plrid AND p.clanid=$this->clanid) * 100, 0) kills_pct",
			'win_ratio' 		=> 'IFNULL(SUM(wins) / SUM(losses), SUM(wins)) win_ratio',
			'win_pct' 		=> 'IFNULL(SUM(wins) / (SUM(wins) + SUM(losses)) * 100, 0) win_pct',

			'kills_per_death' 	=> 'ROUND(IFNULL(SUM(kills) / SUM(deaths), 0),2) kills_per_death',
			'headshot_kills_pct' 	=> 'IFNULL(SUM(headshot_kills) / SUM(kills) * 100, 0) headshot_kills_pct',
			'headshot_deaths_pct' 	=> 'IFNULL(SUM(headshot_deaths) / SUM(deaths) * 100, 0) headshot_deaths_pct',
		);
	
		$cols = $this->ps->get_columns($t_maps, false, array( 'plrid','mapid' ));

		// aggregate static columns for all members
		foreach ($cols as $c) {
			$func = '';
			if (substr($c, -7) == '_streak') {
				// if streak is at the end of the column name
				// then we want the maximum value instead of
				// a summary.
				$func = 'MAX';
			} else {
				$func = 'SUM';
			}
			$sql['*'] .= "$func($c) $c,";
		}
		$sql['*'] = substr($sql['*'], 0, -1); // remove trailing comma

		return $sql;
	}
} 

?>