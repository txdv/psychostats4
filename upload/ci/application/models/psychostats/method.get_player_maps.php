<?php
/**
 * PsychoStats method get_player_maps()
 * $Id$
 *
 * Fetches the maps stats for a single player.
 *
 */

class Psychostats_Method_Get_Player_Maps extends Psychostats_Method {
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
			'fields'	=> null,	// extra fields to select
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

		$t_data = $this->ps->tbl('c_plr_data', $gametype, $modtype);
		$t_maps = $this->ps->tbl('c_plr_maps', $gametype, $modtype);
		$t_map = $this->ps->tbl('map', false);

		// non game specific stats
		$stats = array(
			'd.*, m.name',
			'ROUND(IFNULL(d.kills / d.deaths, 0),2) kills_per_death',
			'ROUND(IFNULL(d.kills / (d.online_time / 60), 0),2) kills_per_minute',
			"IFNULL(d.kills / (SELECT MAX(d3.kills) FROM $t_maps d3 WHERE d3.plrid=d.plrid) * 100, 0) kills_scaled_pct",
			'IFNULL(d.kills / d2.kills * 100, 0) kills_pct',
			'IFNULL(d.wins / d.losses, d.wins) win_ratio',
			'IFNULL(d.wins / (d.wins + d.losses) * 100, 0) win_pct',
		);

		// allow game::mod specific stats to be added
		if ($meth = $this->ps->load_overloaded_method('get_player_maps_sql', $gametype, $modtype)) {
			$meth->execute($stats);
		}
		
		// combine everything into a string for our query
		$fields = implode(',', $stats);
		
		// load the compiled stats
		$sql =
<<<CMD
		SELECT $fields
		FROM ($t_maps d, $t_map m)
		LEFT JOIN $t_data d2 ON d2.plrid=d.plrid
		WHERE m.mapid = d.mapid AND d.plrid = ?
CMD;

		$sql .= $this->ps->order_by($criteria['sort'], $criteria['order']);
		$sql .= $this->ps->limit($criteria['limit'], $criteria['start']);
		$q = $ci->db->query($sql, $id);

		$res = array();
		if ($q->num_rows()) {
			foreach ($q->result_array() as $row) {
				// remove useless fields
				unset($row['plrid']);
				$res[] = $row;
			}
		}
		$q->free_result();

		return $res;
	} 
} 

?>