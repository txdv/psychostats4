<?php
/**
 * PsychoStats method get_player_stats()
 * $Id$
 *
 * Fetches the basic stats for a single player.
 *
 */

class Psychostats_Method_Get_Player_Stats
extends Psychostats_Method {
	public function execute($criteria = array(), $gametype = null, $modtype = null) {
		$ci =& get_instance();
		if (!is_array($criteria)) {
			$criteria = array( 'id' => $criteria );
		}
		// set defaults
		$criteria += array(
			'id' => 0,
		);
		$id = isset($criteria['id']) ? $criteria['id'] : 0;
		
		$res = array();

		if (!$gametype) {
			$g = $this->ps->get_player_gametype($id);
			if (!$g) {
				return false;
			}
			list($gametype, $modtype) = $g;
		}

		$t_plr_data = $this->ps->tbl('c_plr_data', $gametype, $modtype);
		
		$stats = $criteria['select'] ? $criteria['select'] : $this->get_sql();
		$fields = is_array($stats) ? implode(',', $stats) : $stats;
		
		$cmd = "SELECT $fields FROM $t_plr_data d WHERE plrid=?";
		$q = $ci->db->query($cmd, $id);

		$res = false;
		if ($q->num_rows()) {
			$res = $q->row_array();
			unset($res['plrid']); // remove useless column
		}
		$q->free_result();
		
		return $res;
	}
	
	protected function get_sql() {
		// non game specific stats
		$sql = array(
			'*' 			=> 'd.*',
			'kills_per_death' 	=> 'ROUND(IFNULL(kills / deaths, 0),2) kills_per_death',
			'kills_per_minute' 	=> 'ROUND(IFNULL(kills / (online_time/60), 0),2) kills_per_minute',
			'headshot_kills_pct' 	=> 'IFNULL(headshot_kills / kills * 100, 0) headshot_kills_pct',
			'headshot_deaths_pct' 	=> 'IFNULL(headshot_deaths / deaths * 100, 0) headshot_deaths_pct',
		);
		
		return $sql;
	}
} 

?>