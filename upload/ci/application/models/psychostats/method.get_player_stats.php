<?php
/**
 * PsychoStats method get_player_stats()
 * $Id$
 *
 * Fetches the basic stats for a single player.
 *
 */

class Psychostats_Method_Get_Player_Stats extends Psychostats_Method {
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

		// non game specific stats
		$stats = array(
			'*',
			'IFNULL(d.kills / d.deaths, 0) kills_per_death',
			'IFNULL(d.kills / (d.online_time / 60), 0) kills_per_minute',
			'IFNULL(d.headshot_kills / d.kills * 100, 0) headshot_kills_pct',
			'IFNULL(d.headshot_deaths / d.deaths * 100, 0) headshot_deaths_pct',
			'IFNULL(d.shots / d.hits * 100, 0) accuracy',
			'IFNULL(d.hit_head / d.hits * 100, 0) hit_head_pct',
			'IFNULL(d.hit_chest / d.hits * 100, 0) hit_chest_pct',
			'IFNULL(d.hit_leftarm / d.hits * 100, 0) hit_leftarm_pct',
			'IFNULL(d.hit_rightarm / d.hits * 100, 0) hit_rightarm_pct',
			'IFNULL(d.hit_stomach / d.hits * 100, 0) hit_stomach_pct',
			'IFNULL(d.hit_leftleg / d.hits * 100, 0) hit_leftleg_pct',
			'IFNULL(d.hit_rightleg / d.hits * 100, 0) hit_rightleg_pct',
		);

		// allow game::mod specific stats to be added
		if ($meth = $this->ps->load_overloaded_method('get_player_stats_sql', $gametype, $modtype)) {
			$meth->execute($stats);
		}
		
		// combine everything into a string for our query
		$fields = implode(',', $stats);
		
		$sql = "SELECT $fields FROM $t_plr_data d WHERE plrid = ? LIMIT 1";
		$q = $ci->db->query($sql, $id);

		$res = false;
		if ($q->num_rows()) {
			$res = $q->row_array();
			unset($res['plrid']);
		}
		$q->free_result();

		return $res;
	} 
} 

?>