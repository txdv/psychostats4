<?php
/**
 * Psychostats game specific method to modify the main weapons table.
 * $Id$
 */

class Psychostats_Method_Mod_Table_Weapons_Halflife_Tf
extends Psychostats_Method {
	public function execute($table, $gametype, $modtype = null) {
		$table
			->column_remove('headshot_kills','headshot_kills_pct')
			->column_after('kills',
				       'custom_kills',
				       trans('CK'),
				       'number_format',
				       array( 'tooltip' => trans('Custom Kills (backstabs or headshots)') )
			)
			->column_after('custom_kills',
				       'custom_kills_pct',
				       trans('CK%'),
				       array($this->ps->controller, '_cb_pct'),
				       array( 'tooltip' => trans('Custom Kills Percentage') )
			)
			;
	}
}

?>
