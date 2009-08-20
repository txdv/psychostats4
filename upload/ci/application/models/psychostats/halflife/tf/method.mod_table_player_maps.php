<?php
/**
 * Psychostats game specific method to modify the player maps table.
 * $Id$
 */

class Psychostats_Method_Mod_Table_Player_Maps_Halflife_Tf
extends Psychostats_Method {
	public function execute($table, $gametype, $modtype = null) {
		$table
			//->column_last(	'blue_wins',
			//		trans('BW'),
			//		'number_format',
			//		array('tooltip' => trans('Blu Wins'))
			//)
			//->column_last(	'blue_losses',
			//		trans('BL'),
			//		'number_format',
			//		array('tooltip' => trans('Blu Losses'))
			//)
			//->column_last(	'red_wins',
			//		trans('RW'),
			//		'number_format',
			//		array('tooltip' => trans('Red Wins'))
			//)
			//->column_last(	'red_losses',
			//		trans('RL'),
			//		'number_format',
			//		array('tooltip' => trans('Red Losses'))
			//)
			->column_last(	'win_ratio',
					trans('WR'),
					array($this, 'cb_win_ratio'),
					array('tooltip' => trans('Win Ratio'))
			)
			->column_last(	'win_pct',
					trans('Win%'),
					array($this, 'cb_win_pct'),
					array('tooltip' => trans('Win Percentage'))
			)
			->column_last(	'balance',
					trans('Balance'),
					'cb:cb_blu_red_wins',
					array('tooltip' => trans('Blu / Red Wins'),
					      'nosort' => true)
			)
			;
	}
	
	function cb_win_pct($name, $val, $data, $td, $table) {
		return pct_bar(array(
			'pct'	=> $val,
			'title'	=> sprintf('%0.02f%%', $data['win_pct'])
		));
	}

	function cb_win_ratio($name, $val, $data, $td, $table) {
		$color = '#000000';
		if (!is_null($val)) {
			$val = (float)$val;
		}
		
		if (is_null($val) || $val == 0.0) {
			$color = '#aaaaaa';
		} elseif (intval($val) > 0) {
			$color = '#00aa00';
		} else {
			$color = '#aa0000';
		}
	
		return sprintf('<span style="color: %s">%0.04f</span>', $color, $val);
	}
}

// this needs to be removed and made public or put in parent class
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