<?php
/**
 * PsychoStats method get_roles_sql()
 * $Id: method.get_roles_sql.php 624 2009-08-20 11:16:44Z lifo $
 *
 * Defines or Re-defines custom fields to include in the get_roles() SQL query.
 *
 */

class   Psychostats_Method_Get_Roles_Sql_Halflife_Tf 
extends Psychostats_Method {
	public function execute(&$sql) {
		$sql[] = 'ROUND(IFNULL(custom_kills / kills * 100, 0), 0) custom_kills_pct';
	} 
} 

?>
