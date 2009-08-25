<?php
/**
 * PsychoStats method get_player_stats()
 * $Id$
 *
 * Defines or Re-defines custom fields to include in the get_player_stats() SQL query.
 * For HALFLIFE based games (any mod).
 *
 */

include dirname(__FILE__) . '/../' . basename(__FILE__);

class   Psychostats_Method_Get_Player_Stats_Halflife
extends Psychostats_Method_Get_Player_Stats {
	protected function get_sql() {
		$sql = parent::get_sql();
		$sql = array(
			'headshot_kills_pct' 	=> 'IFNULL(headshot_kills / kills * 100, 0) headshot_kills_pct',
			'headshot_deaths_pct' 	=> 'IFNULL(headshot_deaths / deaths * 100, 0) headshot_deaths_pct',
			
			'accuracy' 		=> 'shots / hits * 100 accuracy', 
			'hit_head_pct' 		=> 'IFNULL(hit_head / hits * 100, 0) hit_head_pct',
			'hit_chest_pct' 	=> 'IFNULL(hit_chest / hits * 100, 0) hit_chest_pct',
			'hit_leftarm_pct' 	=> 'IFNULL(hit_leftarm / hits * 100, 0) hit_leftarm_pct',
			'hit_rightarm_pct' 	=> 'IFNULL(hit_rightarm / hits * 100, 0) hit_rightarm_pct',
			'hit_stomach_pct' 	=> 'IFNULL(hit_stomach / hits * 100, 0) hit_stomach_pct',
			'hit_leftleg_pct' 	=> 'IFNULL(hit_leftleg / hits * 100, 0) hit_leftleg_pct',
			'hit_rightleg_pct' 	=> 'IFNULL(hit_rightleg / hits * 100, 0) hit_rightleg_pct',
			'dmg_head_pct' 		=> 'IFNULL(dmg_head / damage * 100, 0) dmg_head_pct',
			'dmg_chest_pct' 	=> 'IFNULL(dmg_chest / damage * 100, 0) dmg_chest_pct',
			'dmg_leftarm_pct' 	=> 'IFNULL(dmg_leftarm / damage * 100, 0) dmg_leftarm_pct',
			'dmg_rightarm_pct' 	=> 'IFNULL(dmg_rightarm / damage * 100, 0) dmg_rightarm_pct',
			'dmg_stomach_pct' 	=> 'IFNULL(dmg_stomach / damage * 100, 0) dmg_stomach_pct',
			'dmg_leftleg_pct' 	=> 'IFNULL(dmg_leftleg / damage * 100, 0) dmg_leftleg_pct',
			'dmg_rightleg_pct' 	=> 'IFNULL(dmg_rightleg / damage * 100, 0) dmg_rightleg_pct',
		) + $sql;
		
		return $sql;
	}
}

?>
