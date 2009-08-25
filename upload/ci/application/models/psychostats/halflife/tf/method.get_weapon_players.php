<?php
/**
 * PsychoStats method get_weapon_players()
 * $Id$
 *
 * Defines or Re-defines custom fields to include in the get_weapons() SQL query.
 *
 */

include dirname(__FILE__) . '/../../' . basename(__FILE__);

class   Psychostats_Method_Get_Weapon_Players_Halflife_Tf 
extends Psychostats_Method_Get_Weapon_Players {
	protected function get_sql() {
		$sql = parent::get_sql();

		$sql['custom_kills_pct'] = 'ROUND(IFNULL(custom_kills / kills * 100, 0), 0) custom_kills_pct';

		return $sql;
	} 
} 

?>
