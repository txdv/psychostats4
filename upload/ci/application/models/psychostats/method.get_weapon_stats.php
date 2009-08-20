<?php
/**
 * PsychoStats method get_weapon_stats()
 * $Id$
 *
 * Fetches the basic stats for a single weapon.
 *
 */

class Psychostats_Method_Get_Weapon_Stats extends Psychostats_Method {
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
			$g = $this->ps->get_weapon_gametype($id);
			if (!$g) {
				return false;
			}
			list($gametype, $modtype) = $g;
		}

		$t_weapon_data = $this->ps->tbl('c_weapon_data', $gametype, $modtype);

		// non game specific stats
		$stats = array(
			'*',
			'IFNULL(d.headshot_kills / d.kills * 100, 0) headshot_kills_pct',
			'IFNULL(d.shots / d.hits * 100, 0) accuracy',
			'IFNULL(d.hit_head / d.hits * 100, 0) hit_head_pct',
			'IFNULL(d.hit_chest / d.hits * 100, 0) hit_chest_pct',
			'IFNULL(d.hit_leftarm / d.hits * 100, 0) hit_leftarm_pct',
			'IFNULL(d.hit_rightarm / d.hits * 100, 0) hit_rightarm_pct',
			'IFNULL(d.hit_stomach / d.hits * 100, 0) hit_stomach_pct',
			'IFNULL(d.hit_leftleg / d.hits * 100, 0) hit_leftleg_pct',
			'IFNULL(d.hit_rightleg / d.hits * 100, 0) hit_rightleg_pct',
			'IFNULL(d.dmg_head / d.damage * 100, 0) dmg_head_pct',
			'IFNULL(d.dmg_chest / d.damage * 100, 0) dmg_chest_pct',
			'IFNULL(d.dmg_leftarm / d.damage * 100, 0) dmg_leftarm_pct',
			'IFNULL(d.dmg_rightarm / d.damage * 100, 0) dmg_rightarm_pct',
			'IFNULL(d.dmg_stomach / d.damage * 100, 0) dmg_stomach_pct',
			'IFNULL(d.dmg_leftleg / d.damage * 100, 0) dmg_leftleg_pct',
			'IFNULL(d.dmg_rightleg / d.damage * 100, 0) dmg_rightleg_pct',
		);

		// allow game::mod specific stats to be added
		if ($meth = $this->ps->load_overloaded_method('get_weapon_stats_sql', $gametype, $modtype)) {
			$meth->execute($stats);
		}
		
		// combine everything into a string for our query
		$fields = implode(',', $stats);
		
		$sql = "SELECT $fields FROM $t_weapon_data d WHERE weaponid = ? LIMIT 1";
		$q = $ci->db->query($sql, $id);

		$res = false;
		if ($q->num_rows()) {
			$res = $q->row_array();
			unset($res['weaponid']);
		}
		$q->free_result();

		return $res;
	} 
} 

?>