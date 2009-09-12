<?php
/**
 * PsychoStats method get_clan_stats()
 * $Id$
 *
 * Fetches the basic stats for a single clan.
 *
 */

class Psychostats_Method_Get_Clan_Stats
extends Psychostats_Method {
	public function execute($criteria = array(), $gametype = null, $modtype = null) {
		$ci =& get_instance();
		if (!is_array($criteria)) {
			$criteria = array( 'id' => $criteria );
		}
		// set defaults
		$criteria += array(
			'id' => 0,
			'where' => null,
			'is_plr_ranked'	=> true,	// true
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

		$t_plr = $this->ps->tbl('plr', false);
		$t_plr_data = $this->ps->tbl('c_plr_data', $gametype, $modtype);
		
		$stats = $criteria['select'] ? $criteria['select'] : $this->get_sql();
		$fields = is_array($stats) ? implode(',', $stats) : $stats;
		
		$cmd =
<<<CMD
		SELECT $fields
		FROM ($t_plr_data d, $t_plr plr)
		WHERE plr.clanid=? AND d.plrid=plr.plrid
CMD;
		//$cmd = preg_replace('/^\s+/m', '', $cmd); // remove leading whitespace (I'm OCD)

		if ($criteria['ranked_only'] || $criteria['is_plr_ranked']) {
			$criteria['where'][] = $this->ps->is_ranked_sql;
		}
		
		// apply sql clauses
		$cmd .= $this->ps->where($criteria['where'], 'AND', true, ' AND ');

		// group results
		$cmd .= " GROUP BY plr.clanid";

		$q = $ci->db->query($cmd, $id);

		$res = false;
		if ($q->num_rows()) {
			$res = $q->row_array();
		}
		$q->free_result();
		
		return $res;
	}
	
	protected function get_sql() {
		// non game specific stats
		$sql = array(
			'*' 			=> '',
			// normally you'd expect these stats to be in the
			// get_clan() results but since we can't determine them
			// without the players they are performed here instead.
			'rank'			=> 'ROUND(AVG(rank),0) rank',
			'rank_prev'		=> 'ROUND(AVG(rank_prev),0) rank_prev',
			'skill'			=> 'ROUND(AVG(skill),4) skill',
			'skill_prev'		=> 'ROUND(AVG(skill_prev),4) skill_prev',
			'skill_change_pct' 	=> 'ROUND(IF(AVG(skill)>=AVG(skill_prev), ' .
							'(AVG(skill)-AVG(skill_prev))/AVG(skill_prev),' .
							'(AVG(skill_prev)-AVG(skill))/AVG(skill)*-1' .
							')*100, 2) skill_change_pct',
			'total_members'		=> 'COUNT(DISTINCT d.plrid) total_members',

			'kills_per_death' 	=> 'ROUND(IFNULL(SUM(kills) / SUM(deaths), 0),2) kills_per_death',
			'headshot_kills_pct' 	=> 'IFNULL(SUM(headshot_kills) / SUM(kills) * 100, 0) headshot_kills_pct',
			'headshot_deaths_pct' 	=> 'IFNULL(SUM(headshot_deaths) / SUM(deaths) * 100, 0) headshot_deaths_pct',
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