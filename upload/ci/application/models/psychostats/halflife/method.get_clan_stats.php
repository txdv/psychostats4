<?php
/**
 * PsychoStats method get_clan_stats()
 * $Id$
 *
 * Defines or Re-defines custom fields to include in the get_clan_stats() SQL query.
 * For HALFLIFE based games (any mod).
 *
 */

include dirname(__FILE__) . '/../' . basename(__FILE__);

class   Psychostats_Method_Get_Clan_Stats_Halflife
extends Psychostats_Method_Get_Clan_Stats {
	protected function get_sql() {
		$sql = parent::get_sql();
		$sql = array(
			'headshot_kills_pct' 	=> 'IFNULL(SUM(headshot_kills) / SUM(kills) * 100, 0) headshot_kills_pct',
			'headshot_deaths_pct' 	=> 'IFNULL(SUM(headshot_deaths) / SUM(deaths) * 100, 0) headshot_deaths_pct',
			
			'accuracy' 		=> 'IFNULL(SUM(shots) / SUM(hits) * 100, 0) accuracy', 
			'hit_head_pct' 		=> 'IFNULL(SUM(hit_head) / SUM(hits) * 100, 0) hit_head_pct',
			'hit_chest_pct' 	=> 'IFNULL(SUM(hit_chest) / SUM(hits) * 100, 0) hit_chest_pct',
			'hit_leftarm_pct' 	=> 'IFNULL(SUM(hit_leftarm) / SUM(hits) * 100, 0) hit_leftarm_pct',
			'hit_rightarm_pct' 	=> 'IFNULL(SUM(hit_rightarm) / SUM(hits) * 100, 0) hit_rightarm_pct',
			'hit_stomach_pct' 	=> 'IFNULL(SUM(hit_stomach) / SUM(hits) * 100, 0) hit_stomach_pct',
			'hit_leftleg_pct' 	=> 'IFNULL(SUM(hit_leftleg) / SUM(hits) * 100, 0) hit_leftleg_pct',
			'hit_rightleg_pct' 	=> 'IFNULL(SUM(hit_rightleg) / SUM(hits) * 100, 0) hit_rightleg_pct',
			'dmg_head_pct' 		=> 'IFNULL(SUM(dmg_head) / SUM(damage) * 100, 0) dmg_head_pct',
			'dmg_chest_pct' 	=> 'IFNULL(SUM(dmg_chest) / SUM(damage) * 100, 0) dmg_chest_pct',
			'dmg_leftarm_pct' 	=> 'IFNULL(SUM(dmg_leftarm) / SUM(damage) * 100, 0) dmg_leftarm_pct',
			'dmg_rightarm_pct' 	=> 'IFNULL(SUM(dmg_rightarm) / SUM(damage) * 100, 0) dmg_rightarm_pct',
			'dmg_stomach_pct' 	=> 'IFNULL(SUM(dmg_stomach) / SUM(damage) * 100, 0) dmg_stomach_pct',
			'dmg_leftleg_pct' 	=> 'IFNULL(SUM(dmg_leftleg) / SUM(damage) * 100, 0) dmg_leftleg_pct',
			'dmg_rightleg_pct' 	=> 'IFNULL(SUM(dmg_rightleg) / SUM(damage) * 100, 0) dmg_rightleg_pct',
		) + $sql;
		
		return $sql;
	}
}

?>
