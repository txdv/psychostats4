<?php
/**
 * PsychoStats method get_players()
 * $Id$
 *
 * Defines or Re-defines custom fields to include in the get_players() SQL query.
 *
 */

// there is currently no 'halflife' specific parent class
require dirname(__FILE__) . '/../../' . basename(__FILE__);

class   Psychostats_Method_Get_Players_Halflife_Tf 
extends Psychostats_Method_Get_Players {
	protected function get_sql() {
		$sql = parent::get_sql();
		$sql[] = 'domination, revenge';
		return $sql;
	} 
} 

?>
