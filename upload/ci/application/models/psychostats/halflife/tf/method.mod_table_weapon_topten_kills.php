<?php
/**
 * Psychostats game specific method to modify the main weapons table.
 * $Id$
 */

class Psychostats_Method_Mod_Table_Weapon_Topten_Kills_Halflife_Tf
extends Psychostats_Method {
	public function execute($table, $gametype, $modtype = null) {
		$table
			->column_remove('headshot_kills')
			->column_before('skill',
				       'custom_kills',
				       trans('CK'),
				       'number_format',
				       array( 'tooltip' => trans('Custom Kills (backstabs or headshots)') )
			)
			->column_after('custom_kills',
				       'custom_kills_pct',
				       trans('CK%'),
				       array($this->ps->controller, '_cb_pct_bar'),
				       array( 'tooltip' => trans('Custom Kills Percentage') )
			)
			;
	}
}

?>
