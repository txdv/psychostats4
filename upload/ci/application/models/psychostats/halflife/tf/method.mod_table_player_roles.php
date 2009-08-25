<?php
/**
 * Psychostats game specific method to modify the player roles table.
 * $Id$
 */

class Psychostats_Method_Mod_Table_Player_Roles_Halflife_Tf
extends Psychostats_Method {
	public function execute($table, $gametype, $modtype = null) {
		$table
			->column('domination',
				trans('Dom'),
				'number_format',
				array( 'tooltip' => trans('Dominations') )
			)
			->column('revenge',
				trans('Rev'),
				'number_format',
				array( 'tooltip' => trans('Revenges') )
			)
			->column('custom_kills',
				trans('CK'),
				'number_format',
				array( 'tooltip' => trans('Custom Kills') )
			)
			->column('custom_kills_pct',
				trans('CK%'),
				array($this->ps->controller, '_cb_pct'),
				array( 'tooltip' => trans('Custom Kills Percentage') )
			)
			->column_remove('headshot_kills')
			;
	}
}

?>