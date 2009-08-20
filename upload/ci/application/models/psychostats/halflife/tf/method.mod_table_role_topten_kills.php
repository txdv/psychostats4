<?php
/**
 * Psychostats game specific method to modify the main roles table.
 * $Id: method.mod_table_role_topten_kills.php 624 2009-08-20 11:16:44Z lifo $
 */

class Psychostats_Method_Mod_Table_Role_Topten_Kills_Halflife_Tf
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
