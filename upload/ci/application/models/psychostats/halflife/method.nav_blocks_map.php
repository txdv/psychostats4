<?php
/**
 * PsychoStats method nav_blocks_map()
 * $Id$
 *
 *
 */

class Psychostats_Method_Nav_Blocks_Map_Halflife extends Psychostats_Method {
	public function execute(&$blocks, &$plr, &$stats) {
		if (!isset($blocks['map_kill_profile'])) {
			$blocks['map_kill_profile'] = array(
				'title'	=> trans('Kill Profile'),
				'rows' => array()
			);
		}
		$blocks['map_kill_profile']['rows'] = array( 
			'kills' => array(
				'row_class' => 'hdr',
				'label' => trans('Kills'),
				'value' => number_format($stats['kills']),
			),
			'headshot_kills' => array(
				'row_class' => 'sub',
				'label' => trans('Headshots'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['headshot_kills_pct']), number_format($stats['headshot_kills'])),
			),
			'team_kills' => array(
				'row_class' => 'sub',
				'label' => trans('Friendly'),
				'value' => number_format($stats['team_kills']),
			),
			'kills_per_minute' => array(
				'row_class' => 'sub',
				'label' => sprintf('<acronym title="%s">%s</acronym>', trans('Kills per Minute'), trans('KpM')),
				'value' => $stats['kills_per_minute'],
			),
			'suicides' => array(
				'row_class' => 'sub',
				'label' => trans('Suicides'),
				'value' => number_format($stats['suicides']),
			),
		) + $blocks['map_kill_profile']['rows'];
	} 
} 

?>