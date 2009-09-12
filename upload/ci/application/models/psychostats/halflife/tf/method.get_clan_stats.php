<?php
/**
 * PsychoStats method get_clan_stats()
 * $Id$
 *
 * Defines or Re-defines custom fields to include in the get_clan_stats() SQL query.
 *
 */

include dirname(__FILE__) . '/../' . basename(__FILE__);

class   Psychostats_Method_Get_Clan_Stats_Halflife_Tf 
extends Psychostats_Method_Get_Clan_Stats_Halflife {
	protected function get_sql() {
		$sql = parent::get_sql();
		$sql = array(
			'assisted_kills_pct' 	=> 'IFNULL(SUM(assisted_kills) / SUM(kills) * 100, 0) assisted_kills_pct',
			'backstab_kills_pct' 	=> 'IFNULL(SUM(backstab_kills) / SUM(kills) * 100, 0) backstab_kills_pct',
			'backstab_deaths_pct' 	=> 'IFNULL(SUM(backstab_deaths) / SUM(deaths) * 100, 0) backstab_deaths_pct',
		) + $sql;
		return $sql;
	} 
} 

?>
