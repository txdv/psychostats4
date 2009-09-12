<?php
/**
 * PsychoStats method get_clan_victims()
 * $Id$
 *
 */
class Psychostats_Method_Get_Clan_Victims extends Psychostats_Method {
	/**
	 * Fetches a list of victim stats for a specific clan.
	 * 
	 * @param array $criteria
	 * 	Criteria that defines what victims will be returned.
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
		$c_plr_victims = $this->ps->tbl('c_plr_victims', $gametype, $modtype);

		$stats = $criteria['select'] ? $criteria['select'] : $this->get_sql();
		$fields = is_array($stats) ? implode(',', $stats) : $stats;

		$cmd =
<<<CMD
		SELECT $fields
		FROM ($c_plr_victims d, $t_plr plr)
		LEFT JOIN $t_plr vic ON vic.plrid=d.victimid
		LEFT JOIN $t_plr_profile vp on vp.uniqueid=vic.uniqueid
		WHERE plr.plrid=d.plrid AND plr.clanid=?
CMD;
		//$cmd = preg_replace('/^\s+/m', '', $cmd); // remove leading whitespace (I'm OCD)

		// apply is_ranked shortcut
		if ($criteria['is_ranked']) {
			$criteria['where'][] = $this->ps->is_ranked_sql;
		}

		// apply sql clauses
		$cmd .= $this->ps->where($criteria['where'], 'AND', true, ' AND ');

		// group results
		$cmd .= " GROUP BY d.victimid ";

		$cmd .= $this->ps->order_by($criteria['sort'], $criteria['order']);
		$cmd .= $this->ps->limit($criteria['limit'], $criteria['start']);

		//print "$cmd<br/><br/>\n";
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
			'*'	=> '',
			// basic victim information
			'vic'	=> 'vic.*',
			'vp'	=> 'vp.*',

			// calculated stats
			'kills_per_death' 	=> 'ROUND(IFNULL(SUM(kills) / SUM(deaths), 0),2) kills_per_death',
			'headshot_kills_pct' 	=> 'IFNULL(SUM(headshot_kills) / SUM(kills) * 100, 0) headshot_kills_pct',
			'headshot_deaths_pct' 	=> 'IFNULL(SUM(headshot_deaths) / SUM(deaths) * 100, 0) headshot_deaths_pct',
		);

		$t_plr_victims = $this->ps->tbl('c_plr_victims', $this->ps->gametype(), $this->ps->modtype());
		$cols = $this->ps->get_columns($t_plr_victims, false, array( 'plrid','victimid' ));

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