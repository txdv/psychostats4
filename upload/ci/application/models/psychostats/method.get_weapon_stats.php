<?php
/**
 * PsychoStats method get_weapon_stats()
 * $Id$
 *
 * Fetches the basic stats for a single weapon.
 *
 */

class Psychostats_Method_Get_Weapon_Stats
extends Psychostats_Method {
	public function execute($criteria = array(), $gametype = null, $modtype = null) {
		$ci =& get_instance();
		if (!is_array($criteria)) {
			$criteria = array( 'id' => $criteria );
		}
		// set defaults
		$criteria += array(
			'id' 		=> 0,
			'select'	=> null,
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

		$stats = $criteria['select'] ? $criteria['select'] : $this->get_sql();
		$fields = is_array($stats) ? implode(',', $stats) : $stats;
		
		$cmd = "SELECT $fields FROM $t_weapon_data d WHERE weaponid=?";
		$q = $ci->db->query($cmd, $id);

		$res = false;
		if ($q->num_rows()) {
			$res = $q->row_array();
			unset($res['weaponid']); // remove useless column
		}
		$q->free_result();

		return $res;
	} 

	protected function get_sql() {
		// non game specific stats
		$sql = array(
			'*' => 'd.*',

			'headshot_kills_pct' => 'ROUND(IFNULL(headshot_kills / kills * 100, 0), 0) headshot_kills_pct',

			'accuracy' => 'IFNULL(shots / hits * 100, 0) accuracy',
			'hit_head_pct' => 'IFNULL(hit_head / hits * 100, 0) hit_head_pct',
			'hit_chest_pct' => 'IFNULL(hit_chest / hits * 100, 0) hit_chest_pct',
			'hit_leftarm_pct' => 'IFNULL(hit_leftarm / hits * 100, 0) hit_leftarm_pct',
			'hit_rightarm_pct' => 'IFNULL(hit_rightarm / hits * 100, 0) hit_rightarm_pct',
			'hit_stomach_pct' => 'IFNULL(hit_stomach / hits * 100, 0) hit_stomach_pct',
			'hit_leftleg_pct' => 'IFNULL(hit_leftleg / hits * 100, 0) hit_leftleg_pct',
			'hit_rightleg_pct' => 'IFNULL(hit_rightleg / hits * 100, 0) hit_rightleg_pct',
			'dmg_head_pct' => 'IFNULL(dmg_head / damage * 100, 0) dmg_head_pct',
			'dmg_chest_pct' => 'IFNULL(dmg_chest / damage * 100, 0) dmg_chest_pct',
			'dmg_leftarm_pct' => 'IFNULL(dmg_leftarm / damage * 100, 0) dmg_leftarm_pct',
			'dmg_rightarm_pct' => 'IFNULL(dmg_rightarm / damage * 100, 0) dmg_rightarm_pct',
			'dmg_stomach_pct' => 'IFNULL(dmg_stomach / damage * 100, 0) dmg_stomach_pct',
			'dmg_leftleg_pct' => 'IFNULL(dmg_leftleg / damage * 100, 0) dmg_leftleg_pct',
			'dmg_rightleg_pct' => 'IFNULL(dmg_rightleg / damage * 100, 0) dmg_rightleg_pct',
		);
		return $sql;
	}
} 

?>