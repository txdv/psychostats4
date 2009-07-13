<?php
/**
 * Psychostats game specific method to modify the player sessions table.
 * $Id$
 */

class Psychostats_Method_Mod_Table_Player_Sessions extends Psychostats_Method {
	public function execute($table, $gametype, $modtype = null) {
		$table
			->column_after('deaths',
				       'assisted_kills',
				       trans('KA'),
				       'number_format',
				       array( 'tooltip' => trans('Kill Assists') )
			)
			->column_before('skill',
					'custom_kills',
					trans('CK'),
					'number_format',
					array( 'tooltip' => trans('Custom Kills') )
			)
			->column_remove('headshot_kills')
			->column_before('skill',
					'domination',
					trans('Dom'),
					'number_format',
					array( 'tooltip' => trans('Dominations') )
			)
			->column_before('skill',
					'wins',
					trans('Wins'),
					'cb:cb_blu_red_wins',
					array('tooltip' => trans('Blu / Red Wins'),
					      'nosort' => true)
			)
			;
	}
}

if (!function_exists('cb_blu_red_wins')) {
	function cb_blu_red_wins($name, $val, $data, $td, $table) {
		$wins = $data['blue_wins'] + $data['red_wins'];
		if ($wins) {
			$pct1 = $data['blue_wins'] / $wins * 100;
			$pct2 = $data['red_wins'] / $wins * 100;
			return dual_bar(array(
				'pct1'	=> $pct1,
				'pct2'	=> $pct2,
				'title1'=> trans('%d Blu Wins (%0.02f%%)', $data['blue_wins'], $pct1),
				'title2'=> trans('%d Red Wins (%0.02f%%)', $data['red_wins'], $pct2),
				'color1'=> '0000CC',
				'color2'=> 'CC0000',
			));
		} else {
			// don't show a normal dual_bar if we have no wins
			return pct_bar(0);
		}
	}
}

?>