<?php
/**
 * PsychoStats method get_clans()
 * $Id$
 *
 */
class Psychostats_Method_Get_Clans extends Psychostats_Method {
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
			'limit' 	=> null,
			'start' 	=> null,
			'sort'		=> null,
			'order' 	=> null,
			'where' 	=> null,
			'min_members'	=> 2,
			'ranked_only'	=> null,
			'is_ranked' 	=> true,	// true (clan)
			'is_plr_ranked'	=> true,	// true
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
		$t_clan = $this->ps->tbl('clan', false);
		$t_clan_profile = $this->ps->tbl('clan_profile', false);
		$c_plr_data = $this->ps->tbl('c_plr_data', $gametype, $modtype);

		$stats = $criteria['select'] ? $criteria['select'] : $this->get_sql();
		$fields = is_array($stats) ? implode(',', $stats) : $stats;

		// Using a LEFT JOIN allows mysql to optimize the query based
		// on the total players ranked which lowers the total rows that
		// need to be scanned. If all tables are in the FROM clause
		// then mysql will scan the entire data table every time.

		$cmd =
<<<CMD
		SELECT $fields
		FROM ($t_clan clan, $t_plr plr)
		LEFT JOIN $c_plr_data d ON d.plrid=plr.plrid
		LEFT JOIN $t_clan_profile cp ON cp.clantag=clan.clantag
		WHERE
CMD;
		//$cmd = preg_replace('/^\s+/m', '', $cmd); // remove leading whitespace (I'm OCD)

		// add join clause for tables
		//$criteria['where'][] = 'clan.clantag=cp.clantag';
		$criteria['where'][] = 'clan.clanid=plr.clanid';
		//$criteria['where'][] = 'plr.plrid=d.plrid';

		// apply is_ranked shortcut
		if ($criteria['ranked_only'] || $criteria['is_ranked']) {
			$criteria['where'][] = $this->ps->is_clan_ranked_sql;
		}
		if ($criteria['ranked_only'] || $criteria['is_plr_ranked']) {
			$criteria['where'][] = $this->ps->is_ranked_sql;
		}
		
		// apply sql clauses
		$cmd .= $this->ps->where($criteria['where']);
		
		// apply group by
		$cmd .= " GROUP BY plr.clanid";
		
		// apply min_members criteria. Using 'HAVING' sucks since its
		// applied using NO optimizations by MYSQL but unless I store
		// the total_members count in the clan table there's no other
		// way to do it.
		if ($criteria['min_members']) {
			$cmd .= ' HAVING COUNT(DISTINCT plr.plrid) >= ' . $criteria['min_members'];
		}
		
		$cmd .= $this->ps->order_by($criteria['sort'], $criteria['order']);
		$cmd .= $this->ps->limit($criteria['limit'], $criteria['start']);
		//print $cmd;
		
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
		$sql = array(
			'*' => '',
			//'*' => 'SUM(kills) kills, SUM(deaths) deaths, SUM(headshot_kills) headshot_kills', 

			'clan' 	=> 'clan.clanid, clan.clantag',
			'cp' 	=> 'cp.name, cp.icon, cp.cc',

			'total_members' => 'COUNT(DISTINCT plr.plrid) total_members',
			'skill' => 'ROUND(AVG(skill),4) skill',

			// calculated stats
			'kills_per_death' 	=> 'ROUND(IFNULL(SUM(kills) / SUM(deaths), 0), 2) kills_per_death',
			//'kills_per_minute' 	=> 'ROUND(IFNULL(SUM(kills) / (SUM(online_time)/60), 0), 2) kills_per_minute', 
			'headshot_kills_pct' 	=> 'ROUND(IFNULL(SUM(headshot_kills) / SUM(kills) * 100, 0), 2) headshot_kills_pct',
		);

		$t_plr_data = $this->ps->tbl('c_plr_data', $this->ps->gametype(), $this->ps->modtype());
		$cols = $this->ps->get_columns($t_plr_data, false, array( 'plrid' ));

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