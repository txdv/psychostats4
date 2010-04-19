<?php
/**
 * PsychoStats method Nav_Blocks_Player()
 * $Id$
 *
 *
 */

include dirname(__FILE__) . '/../' . basename(__FILE__);

class   Psychostats_Method_Nav_Blocks_Player_Halflife_Tf
extends Psychostats_Method_Nav_Blocks_Player_Halflife {
	public function execute(&$blocks, &$plr, &$stats) {
		parent::execute($blocks, $plr, $stats);

		// dual_bar defaults
		$bar = array(
			'color1' => '0000CC',
			'color2' => 'CC0000',
		);
		
		if (!array_key_exists('player_actions', $blocks)) {
			array_push_after($blocks, 'player_kill_profile', array(
				'title' => trans('Player Actions'),
				'rows' => array(),
			), 'player_actions');
		}
		
		$blocks['player_actions']['rows'] += array(
			'games' => array(
				'row_class' => 'hdr',
				'label' => trans('Games'),
			),
			'team_wins' => array(
				'row_class' => 'sub',
				'label' => trans('Wins'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s',
					$stats['wins'] ? dual_bar($bar + array(
						'pct1'	=> $stats['blue_wins'] / $stats['wins'] * 100,
						'pct2'	=> $stats['red_wins'] / $stats['wins'] * 100,
						'title1'=> trans('%s Blu wins', number_format($stats['blue_wins'])),
						'title2'=> trans('%s Red wins', number_format($stats['red_wins'])),
					)) : '',
					number_format($stats['wins'])),
			),
			'team_losses' => array(
				'row_class' => 'sub',
				'label' => trans('Losses'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s',
					$stats['losses'] ? dual_bar($bar + array(
						'pct1'	=> $stats['blue_losses'] / $stats['losses'] * 100,
						'pct2'	=> $stats['red_losses'] / $stats['losses'] * 100,
						'title1'=> trans('%s Blu losses', number_format($stats['blue_losses'])),
						'title2'=> trans('%s Red losses', number_format($stats['red_losses'])),
					)) : '',
					number_format($stats['losses'])),
			),
			'flags' => array(
				'row_class' => 'hdr',
				'label' => trans('Flags'),
			),
			'flag_captured' => array(
				'row_class' => 'sub',
				'label' => trans('Captured'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s',
					$stats['flag_captured'] ? dual_bar($bar + array(
						'pct1'	=> $stats['blue_flag_captured'] / $stats['flag_captured'] * 100,
						'pct2'	=> $stats['red_flag_captured'] / $stats['flag_captured'] * 100,
						'title1'=> trans('%s Blu captures', number_format($stats['blue_flag_captured'])),
						'title2'=> trans('%s Red captures', number_format($stats['red_flag_captured'])),
					)) : '',
					number_format($stats['flag_captured'])),
			),
			'flag_defended' => array(
				'row_class' => 'sub',
				'label' => trans('Defended'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s',
					$stats['flag_defended'] ? dual_bar($bar + array(
						'pct1'	=> $stats['blue_flag_defended'] / $stats['flag_defended'] * 100,
						'pct2'	=> $stats['red_flag_defended'] / $stats['flag_defended'] * 100,
						'title1'=> trans('%s Blu defends', number_format($stats['blue_flag_defended'])),
						'title2'=> trans('%s Red defends', number_format($stats['red_flag_defended'])),
					)) : '',
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

			'points' => array(
				'row_class' => 'hdr',
				'label' => trans('Control Points'),
			),
			'point_captured' => array(
				'row_class' => 'sub',
				'label' => trans('Captured'),
				'value' => number_format($stats['point_captured']),
				//'value' => sprintf('<div class="pct-stat">%s</div>%s',
				//	$stats['point_captured'] ? dual_bar($bar + array(
				//		'pct1'	=> $stats['blue_point_captured'] / $stats['point_captured'] * 100,
				//		'pct2'	=> $stats['red_point_captured'] / $stats['point_captured'] * 100,
				//		'title1'=> trans('%s Blu captures', number_format($stats['blue_point_captured'])),
				//		'title2'=> trans('%s Red captures', number_format($stats['red_point_captured'])),
				//	)) : '',
				//	number_format($stats['point_captured'])),
			),
			'blocked_capture' => array(
				'row_class' => 'sub',
				'label' => trans('Blocked'),
				'value' => number_format($stats['blocked_capture']),
				//'value' => sprintf('<div class="pct-stat">%s</div>%s',
				//	$stats['blocked_capture'] ? dual_bar($bar + array(
				//		'pct1'	=> $stats['blue_blocked_capture'] / $stats['blocked_capture'] * 100,
				//		'pct2'	=> $stats['red_blocked_capture'] / $stats['blocked_capture'] * 100,
				//		'title1'=> trans('%s Blu defends', number_format($stats['blue_blocked_capture'])),
				//		'title2'=> trans('%s Red defends', number_format($stats['red_blocked_capture'])),
				//	)) : '',
				//	number_format($stats['blocked_capture'])),
			),

			'built_objects' => array(
				'row_class' => 'hdr',
				'label' => trans('Built'),
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

			'destroyed_objects' => array(
				'row_class' => 'hdr',
				'label' => trans('Destroyed'),
				'value' => number_format($stats['destroyed_objects'])
			),
			'destroyed_attachment_sapper' => array(
				'row_class' => 'sub',
				'label' => trans('Sappers'),
				'value' => number_format($stats['destroyed_attachment_sapper']),
			),
			'destroyed_dispenser' => array(
				'row_class' => 'sub',
				'label' => trans('Dispensers'),
				'value' => number_format($stats['destroyed_dispenser']),
			),
			'destroyed_sentrygun' => array(
				'row_class' => 'sub',
				'label' => trans('Sentry Guns'),
				'value' => number_format($stats['destroyed_sentrygun']),
			),
			'destroyed_teleporter_entrance' => array(
				'row_class' => 'sub',
				'label' => trans('Teleporter Entry'),
				'value' => number_format($stats['destroyed_teleporter_entrance']),
			),
			'destroyed_teleporter_exit' => array(
				'row_class' => 'sub',
				'label' => trans('Teleporter Exit'),
				'value' => number_format($stats['destroyed_teleporter_exit']),
			),
		);
			
		array_push_after($blocks['player_kill_profile']['rows'],
			'kills',
			array( 
			       'row_class' => 'sub',
			       'label' => trans('Killed'),
			       'value' => sprintf('<div class="pct-stat">%s</div>&nbsp;',
				       $stats['kills'] ? dual_bar($bar + array(
					       'pct1'	=> $stats['killed_blue'] / $stats['kills'] * 100,
					       'pct2'	=> $stats['killed_red'] / $stats['kills'] * 100,
					       'title1'=> trans('%s Blu kills', number_format($stats['killed_blue'])),
					       'title2'=> trans('%s Red kills', number_format($stats['killed_red'])),
				       )) : ''
			       ),
			),
			'killed'
		);

		array_push_after($blocks['player_kill_profile']['rows'],
			'killed',
			array( 
			       'row_class' => 'sub',
			       'label' => trans('Assists'),
			       'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['assisted_kills_pct']), number_format($stats['assisted_kills'])),
			),
			'assisted_kills'
		);

		array_push_after($blocks['player_kill_profile']['rows'],
			'headshot_kills',
			array( 
			       'row_class' => 'sub',
			       'label' => trans('Dominations'),
			       'value' => number_format($stats['domination']), 
			       //'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['assisted_kills_pct']), number_format($stats['assisted_kills'])),
			),
			'domination'
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

		// deaths
		array_push_after($blocks['player_kill_profile']['rows'],
			'deaths',
			array( 
			       'row_class' => 'sub',
			       'label' => trans('Deaths From'),
			       'value' => sprintf('<div class="pct-stat">%s</div>&nbsp;',
				       $stats['deaths'] ? dual_bar($bar + array(
					       'pct1'	=> $stats['deathsby_blue'] / $stats['deaths'] * 100,
					       'pct2'	=> $stats['deathsby_red'] / $stats['deaths'] * 100,
					       'title1'=> trans('%s deaths from Blu', number_format($stats['deathsby_blue'])),
					       'title2'=> trans('%s deaths from Red', number_format($stats['deathsby_red'])),
				       )) : ''
			       ),
			),
			'deathsby'
		);

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
