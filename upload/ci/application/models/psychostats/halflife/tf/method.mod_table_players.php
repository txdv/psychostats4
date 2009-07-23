<?php
/**
 * Psychostats game specific method to modify the main players table.
 * $Id$
 */

class Psychostats_Method_Mod_Table_Players_Halflife_Tf
extends Psychostats_Method {
	public function execute($table, $gametype, $modtype = null) {
		$table
			->column_remove('headshot_kills','headshot_kills_pct')
			->column_before('online_time',
				       'domination',
				       trans('Dom'),
				       'number_format',
				       array( 'tooltip' => trans('Dominations') )
			)
			->column_before('online_time',
				       'revenge',
				       trans('Rev'),
				       'number_format',
				       array( 'tooltip' => trans('Revenges') )
			)
			;
	}
}

?>