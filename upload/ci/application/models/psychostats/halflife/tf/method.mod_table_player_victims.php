<?php
/**
 * Psychostats game specific method to modify the player victims table.
 * $Id$
 */

class Psychostats_Method_Mod_Table_Player_Victims extends Psychostats_Method {
	public function execute($table, $gametype, $modtype = null) {
		$table
			->column_after('headshot_kills',
				       'custom_kills',
				       trans('CK'),
				       'number_format',
				       array( 'tooltip' => trans('Custom Kills') )
			)
			->column_remove('headshot_kills')
			;
	}
}

?>