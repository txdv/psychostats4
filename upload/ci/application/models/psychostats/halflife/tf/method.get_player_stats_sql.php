<?php
/**
 * PsychoStats method get_player_stats_sql()
 * $Id$
 *
 * Defines or Re-defines custom fields to include in the get_player_stats() SQL query.
 *
 */

class   Psychostats_Method_Get_Player_Stats_Sql_Halflife_Tf 
extends Psychostats_Method {
	public function execute(&$sql) {
		$sql[] = 'IFNULL(d.assisted_kills / d.kills * 100, 0) assisted_kills_pct';
		$sql[] = 'IFNULL(d.backstab_kills / d.kills * 100, 0) backstab_kills_pct';
		$sql[] = 'IFNULL(d.backstab_deaths / d.deaths * 100, 0) backstab_deaths_pct';
	} 
} 

?>
