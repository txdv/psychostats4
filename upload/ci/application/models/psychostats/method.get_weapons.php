<?php
/**
 * PsychoStats method get_weapons()
 * $Id$
 *
 */
class Psychostats_Method_Get_Weapons extends Psychostats_Method {
	/**
	 * Fetches a list of weapon stats for a specific game.
	 * 
	 * @param array $criteria
	 * 	Criteria that defines what weapons will be returned.
	 * @param string $gametype
	 * 	Game type to fetch weapons list for. Leave null for current
	 * 	default (from set_gametype).
	 * @param string $modtype
	 * 	Mod type to fetch weapons list for. Leave null for current
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
		);
		
		$ci =& get_instance();
		if ($gametype === null) {
			$gametype = $this->ps->gametype();
		}
		if ($modtype === null) {
			$modtype = $this->ps->modtype();
		}

		// setup table names
		$t_weapon = $this->ps->tbl('weapon', false);
		$c_weapon_data = $this->ps->tbl('c_weapon_data', $gametype, $modtype);

		$stats = $criteria['select'] ? $criteria['select'] : $this->get_sql();
		$fields = is_array($stats) ? implode(',', $stats) : $stats;

		// start basic query
		$cmd = "SELECT $fields FROM $t_weapon wpn, $c_weapon_data d WHERE ";

		// add join clause for tables
		$criteria['where'][] = 'd.weaponid=wpn.weaponid';
		
		// apply sql clauses
		$cmd .= $this->ps->where($criteria['where']);
		$cmd .= $this->ps->order_by($criteria['sort'], $criteria['order']);
		$cmd .= $this->ps->limit($criteria['limit'], $criteria['start']);

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
		$c_weapons = $this->ps->tbl('c_weapon_data');

		// non game specific stats		
		$sql = array(
			// basic information
			'wpn' => 'name, full_name, weight, class, COALESCE(full_name, name) long_name',

			// static stats
			'*' => 'd.*',

			// calculated stats
			'kills_scaled_pct' => "IFNULL(d.kills / (SELECT MAX(d3.kills) FROM $c_weapons d3) * 100, 0) kills_scaled_pct",
			'kills_pct' => "IFNULL(d.kills / (SELECT SUM(d2.kills) FROM $c_weapons d2) * 100, 0) kills_pct",
			'headshot_kills_pct' => 'ROUND(IFNULL(headshot_kills / kills * 100, 0), 0) headshot_kills_pct',

			'accuracy' => 'IFNULL(d.shots / d.hits * 100, 0) accuracy',

			'hit_head_pct' => 'IFNULL(d.hit_head / d.hits * 100, 0) hit_head_pct',
			'hit_chest_pct' => 'IFNULL(d.hit_chest / d.hits * 100, 0) hit_chest_pct',
			'hit_leftarm_pct' => 'IFNULL(d.hit_leftarm / d.hits * 100, 0) hit_leftarm_pct',
			'hit_rightarm_pct' => 'IFNULL(d.hit_rightarm / d.hits * 100, 0) hit_rightarm_pct',
			'hit_stomach_pct' => 'IFNULL(d.hit_stomach / d.hits * 100, 0) hit_stomach_pct',
			'hit_leftleg_pct' => 'IFNULL(d.hit_leftleg / d.hits * 100, 0) hit_leftleg_pct',
			'hit_rightleg_pct' => 'IFNULL(d.hit_rightleg / d.hits * 100, 0) hit_rightleg_pct',
			'dmg_head_pct' => 'IFNULL(d.dmg_head / d.damage * 100, 0) dmg_head_pct',
			'dmg_chest_pct' => 'IFNULL(d.dmg_chest / d.damage * 100, 0) dmg_chest_pct',
			'dmg_leftarm_pct' => 'IFNULL(d.dmg_leftarm / d.damage * 100, 0) dmg_leftarm_pct',
			'dmg_rightarm_pct' => 'IFNULL(d.dmg_rightarm / d.damage * 100, 0) dmg_rightarm_pct',
			'dmg_stomach_pct' => 'IFNULL(d.dmg_stomach / d.damage * 100, 0) dmg_stomach_pct',
			'dmg_leftleg_pct' => 'IFNULL(d.dmg_leftleg / d.damage * 100, 0) dmg_leftleg_pct',
			'dmg_rightleg_pct' => 'IFNULL(d.dmg_rightleg / d.damage * 100, 0) dmg_rightleg_pct',
		);
		return $sql;
	}
} 

?>