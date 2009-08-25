<?php
/**
 * PsychoStats method get_player_victims()
 * $Id$
 *
 * Fetches the victims stats for a single player.
 *
 */

class Psychostats_Method_Get_Player_Victims extends Psychostats_Method {
	public function execute($criteria = array(), $gametype = null, $modtype = null) {
		if (!is_array($criteria)) {
			$criteria = array( 'id' => $criteria );
		}
		// set defaults
		$criteria += array(
			'id'		=> 0,
			'sort'		=> 'kills',
			'order' 	=> 'desc',
			'limit' 	=> null,
			'start' 	=> 0,
			'where'		=> null,
			'is_ranked'	=> null,
			'select'	=> null,
		);
		
		$ci =& get_instance();
		$res = array();
		$id = isset($criteria['id']) ? $criteria['id'] : 0;

		if (!$gametype) {
			$g = $this->ps->get_player_gametype($id);
			if (!$g) {
				return false;
			}
			list($gametype, $modtype) = $g;
		}

		$t_plr = $this->ps->tbl('plr', false);
		$t_profile = $this->ps->tbl('plr_profile', false);
		$t_data = $this->ps->tbl('c_plr_data', $gametype, $modtype);
		$t_victims = $this->ps->tbl('c_plr_victims', $gametype, $modtype);

		$stats = $criteria['select'] ? $criteria['select'] : $this->get_sql();
		$fields = is_array($stats) ? implode(',', $stats) : $stats;

		$cmd =
<<<CMD
		SELECT $fields
		FROM ($t_victims d, $t_plr plr)
		LEFT JOIN $t_profile pp ON pp.uniqueid = plr.uniqueid
		WHERE plr.plrid = d.victimid AND d.plrid = ?
CMD;

		// apply is_ranked shortcut
		if ($criteria['is_ranked']) {
			$criteria['where'][] = $this->ps->is_ranked_sql;
		}
		
		$cmd .= $this->ps->where($criteria['where'], 'AND', true, ' AND ');
		$cmd .= $this->ps->order_by($criteria['sort'], $criteria['order']);
		$cmd .= $this->ps->limit($criteria['limit'], $criteria['start']);
		
		$q = $ci->db->query($cmd, $id);

		$list = array();
		if ($q->num_rows()) {
			foreach ($q->result_array() as $row) {
				// remove useless fields
				unset($row['plrid'], $row['gametype'], $row['modtype']);
				$list[] = $row;
			}
		}
		$q->free_result();

		return $list;
	} 

	protected function get_sql() {
		$t_victims = $this->ps->tbl('c_plr_victims', $this->ps->gametype(), $this->ps->modtype());

		// non game specific stats
		$sql = array(
			'*' 			=> 'd.*',
			'plr' 			=> 'plr.*',
			'pp' 			=> 'pp.*',
			'kills_per_death' 	=> 'ROUND(IFNULL(kills / deaths, 0),2) kills_per_death',
			'kills_scaled_pct' 	=> "IFNULL(d.kills / (SELECT MAX(d3.kills) FROM $t_victims d3 WHERE d3.plrid=d.plrid) * 100, 0) kills_scaled_pct",
			'kills_pct' 		=> "IFNULL(d.kills / (SELECT SUM(d2.kills) FROM $t_victims d2) * 100, 0) kills_pct",
		);
		return $sql;
	}
} 

?>