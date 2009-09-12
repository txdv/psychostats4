<?php
/**
 * PsychoStats method get_map_stats()
 * $Id$
 *
 * Defines or Re-defines custom fields to include in the get_map_stats() SQL query.
 * For HALFLIFE based games (any mod).
 *
 */

include dirname(__FILE__) . '/../' . basename(__FILE__);

class   Psychostats_Method_Get_Map_Stats_Halflife
extends Psychostats_Method_Get_Map_Stats {
	protected function get_sql() {
		$sql = parent::get_sql();
		$sql = array(
			'headshot_kills_pct' 	=> 'IFNULL(headshot_kills / kills * 100, 0) headshot_kills_pct',
		) + $sql;
		
		return $sql;
	}
}

?>
