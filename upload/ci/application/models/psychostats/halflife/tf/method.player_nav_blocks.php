<?php
/**
 * PsychoStats method player_nav_blocks()
 * $Id$
 *
 *
 */

include dirname(__FILE__) . '/../method.player_nav_blocks.php';

class   Psychostats_Method_Player_Nav_Blocks_Halflife_Tf
extends Psychostats_Method_Player_Nav_Blocks_Halflife {
	public function execute(&$blocks, &$plr, &$stats) {
		parent::execute($blocks, $plr, $stats);

		if (!isset($blocks['player_actions'])) {
			$blocks['player_actions'] = array(
				'title' => trans('Player Actions'),
				'rows' => array(),
			);
		}
		
		// dual_bar defaults
		$bar = array(
			'color1' => '0000CC',
			'color2' => 'CC0000',
		);
		
		$blocks['player_actions']['rows'] += array(
			'flags' => array(
				'row_class' => 'hdr',
				'label' => trans('Flags'),
			),
			'flag_captured' => array(
				'row_class' => 'sub',
				'label' => trans('Captured'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s',
					dual_bar($bar + array(
						'pct1'	=> $stats['blue_flag_captured'] / $stats['flag_captured'] * 100,
						'pct2'	=> $stats['red_flag_captured'] / $stats['flag_captured'] * 100,
						'title1'=> trans('%d Blu captures', $stats['blue_flag_captured']),
						'title2'=> trans('%d Red captures', $stats['red_flag_captured']),
					)),
					number_format($stats['flag_captured'])),
			),
			'flag_defended' => array(
				'row_class' => 'sub',
				'label' => trans('Defended'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s',
					dual_bar($bar + array(
						'pct1'	=> $stats['blue_flag_defended'] / $stats['flag_defended'] * 100,
						'pct2'	=> $stats['red_flag_defended'] / $stats['flag_defended'] * 100,
						'title1'=> trans('%d Blu defends', $stats['blue_flag_defended']),
						'title2'=> trans('%d Red defends', $stats['red_flag_defended']),
					)),
					number_format($stats['flag_defended'])),
			),
			'flag_pickedup' => array(
				'row_class' => 'sub',
				'label' => trans('Picked Up'),
				'value' => number_format($stats['flag_pickedup']),
			),
			'flag_dropped' => array(
				'row_class' => 'sub',
				'label' => trans('Dropped'),
				'value' => number_format($stats['flag_dropped']),
			),
			
			'built_objects' => array(
				'row_class' => 'hdr',
				'label' => trans('Structures Built'),
				'value' => number_format($stats['built_objects'])
			),
			'built_attachment_sapper' => array(
				'row_class' => 'sub',
				'label' => trans('Sappers'),
				'value' => number_format($stats['built_attachment_sapper']),
			),
			'built_dispenser' => array(
				'row_class' => 'sub',
				'label' => trans('Dispensers'),
				'value' => number_format($stats['built_dispenser']),
			),
			'built_sentrygun' => array(
				'row_class' => 'sub',
				'label' => trans('Sentry Guns'),
				'value' => number_format($stats['built_sentrygun']),
			),
			'built_teleporter_entrance' => array(
				'row_class' => 'sub',
				'label' => trans('Teleporter Entry'),
				'value' => number_format($stats['built_teleporter_entrance']),
			),
			'built_teleporter_exit' => array(
				'row_class' => 'sub',
				'label' => trans('Teleporter Exit'),
				'value' => number_format($stats['built_teleporter_exit']),
			),
		);
			


		array_push_after($blocks['player_kill_profile']['rows'],
				 'headshot_kills',
				 array( 
					'row_class' => 'sub',
					'label' => trans('Dominations'),
					'value' => number_format($stats['domination']), 
					//'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['assisted_kills_pct']), number_format($stats['assisted_kills'])),
				 ),
				 'domation'
		);

		array_push_after($blocks['player_kill_profile']['rows'],
				 'headshot_kills',
				 array( 
					'row_class' => 'sub',
					'label' => trans('Revenges'),
					'value' => number_format($stats['revenge']), 
					//'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['assisted_kills_pct']), number_format($stats['assisted_kills'])),
				 ),
				 'revenge'
		);

		array_push_after($blocks['player_kill_profile']['rows'],
				 'headshot_kills',
				 array( 
					'row_class' => 'sub',
					'label' => trans('Backstabs'),
					'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['backstab_kills_pct']), number_format($stats['backstab_kills'])),
				 ),
				 'backstab_kills'
		);

		array_push_after($blocks['player_kill_profile']['rows'],
				 'headshot_kills',
				 array( 
					'row_class' => 'sub',
					'label' => trans('Assists'),
					'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['assisted_kills_pct']), number_format($stats['assisted_kills'])),
				 ),
				 'assisted_kills'
		);


		// deaths
		array_push_after($blocks['player_kill_profile']['rows'],
				 'headshot_deaths',
				 array( 
					'row_class' => 'sub',
					'label' => trans('Backstabs'),
					'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['backstab_deaths_pct']), number_format($stats['backstab_deaths'])),
				 ),
				 'backstab_deaths'
		);

	
	}
}

?>
