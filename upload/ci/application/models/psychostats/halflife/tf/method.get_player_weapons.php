<?php
/**
 * PsychoStats method get_player_weapons()
 * $Id$
 *
 * Defines or Re-defines custom fields to include in the get_player_weapons() SQL query.
 *
 */

include dirname(__FILE__) . '/../../' . basename(__FILE__);

class   Psychostats_Method_Get_Player_Weapons_Halflife_Tf 
extends Psychostats_Method_Get_Player_Weapons {
	protected function get_sql() {
		$sql = parent::get_sql();
		$sql = array(
			'backstab_kills_pct' 	=> 'IFNULL(backstab_kills / kills * 100, 0) backstab_kills_pct',
			'custom_kills_pct' 	=> 'ROUND(IFNULL(custom_kills / kills * 100, 0), 0) custom_kills_pct',
		) + $sql;
		return $sql;
	} 
} 

?>
