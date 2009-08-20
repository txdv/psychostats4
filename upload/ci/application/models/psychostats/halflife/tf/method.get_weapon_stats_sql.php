<?php
/**
 * PsychoStats method get_weapon_stats_sql()
 * $Id$
 *
 * Defines or Re-defines custom fields to include in the get_weapons() SQL query.
 *
 */

class   Psychostats_Method_Get_Weapon_Stats_Sql_Halflife_Tf 
extends Psychostats_Method {
	public function execute(&$sql) {
		$sql[] = 'ROUND(IFNULL(backstab_kills / kills * 100, 0), 0) backstab_kills_pct';
	} 
} 

?>
