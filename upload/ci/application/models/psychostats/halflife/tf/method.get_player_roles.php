<?php
/**
 * PsychoStats method get_player_roles()
 * $Id$
 *
 * Defines or Re-defines custom fields to include in the get_player_roles() SQL query.
 *
 */

include dirname(__FILE__) . '/../../' . basename(__FILE__);

class   Psychostats_Method_Get_Player_Roles_Halflife_Tf 
extends Psychostats_Method_Get_Player_Roles {
	protected function get_sql() {
		$sql = parent::get_sql();
		$sql = array(
			'custom_kills_pct' 	=> 'ROUND(IFNULL(custom_kills / kills * 100, 0), 0) custom_kills_pct',
		) + $sql;
		unset($sql['headshot_kills_pct'], $sql['headshot_deaths_pct']);
		return $sql;
	} 
} 

?>
