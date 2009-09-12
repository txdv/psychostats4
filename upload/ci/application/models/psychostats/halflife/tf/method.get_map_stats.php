<?php
/**
 * PsychoStats method get_map_stats()
 * $Id$
 *
 * Defines or Re-defines custom fields to include in the get_map_stats() SQL query.
 *
 */

include dirname(__FILE__) . '/../' . basename(__FILE__);

class   Psychostats_Method_Get_Map_Stats_Halflife_Tf 
extends Psychostats_Method_Get_Map_Stats_Halflife {
	protected function get_sql() {
		$sql = parent::get_sql();

		//$sql['assisted_kills_pct'] = 'IFNULL(assisted_kills / kills * 100, 0) assisted_kills_pct';
		$sql['backstab_kills_pct'] = 'IFNULL(backstab_kills / kills * 100, 0) backstab_kills_pct';

		return $sql;
	} 
} 

?>
