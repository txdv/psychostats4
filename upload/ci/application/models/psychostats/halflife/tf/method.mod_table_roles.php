<?php
/**
 * Psychostats game specific method to modify the main roles table.
 * $Id: method.mod_table_roles.php 624 2009-08-20 11:16:44Z lifo $
 */

class Psychostats_Method_Mod_Table_Roles_Halflife_Tf
extends Psychostats_Method {
	public function execute($table, $gametype, $modtype = null) {
		$table
			->column_remove('headshot_kills','headshot_kills_pct')
			->column('custom_kills',
				       trans('CK'),
				       'number_format',
				       array( 'tooltip' => trans('Custom Kills (backstabs or headshots)') )
			)
			->column('custom_kills_pct',
				       trans('CK%'),
				       array($this->ps->controller, '_cb_pct'),
				       array( 'tooltip' => trans('Custom Kills Percentage') )
			)
			;
	}
}

?>
