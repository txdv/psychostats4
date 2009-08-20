<?php
/**
 * PsychoStats method get_weapon_players_sql()
 * $Id$
 *
 * Defines or Re-defines custom fields to include in the get_weapons() SQL query.
 *
 */

class   Psychostats_Method_Get_Weapon_Players_Sql_Halflife_Tf 
extends Psychostats_Method {
	public function execute(&$sql) {
		$sql[] = 'ROUND(IFNULL(custom_kills / kills * 100, 0), 0) custom_kills_pct';
	} 
} 

?>
