<?php
/**
 * PsychoStats method get_players_sql()
 * $Id$
 *
 * Defines or Re-defines custom fields to include in the get_players() SQL query.
 *
 */

class   Psychostats_Method_Get_Players_Sql_Halflife_Tf 
extends Psychostats_Method {
	public function execute(&$sql) {
		$sql[] = 'domination, revenge';
	} 
} 

?>
