<?php
/**
 * PsychoStats method get_maps()
 * $Id$
 *
 * Defines or Re-defines custom fields to include in the get_maps() SQL query.
 *
 */

include dirname(__FILE__) . '/../../' . basename(__FILE__);

class   Psychostats_Method_Get_Maps_Halflife_Tf 
extends Psychostats_Method_Get_Maps {
	protected function get_sql() {
		$sql = parent::get_sql();
		$sql['custom_kills_pct'] = 'ROUND(IFNULL(custom_kills / kills * 100, 0), 0) custom_kills_pct';
		return $sql;
	} 
} 

?>
