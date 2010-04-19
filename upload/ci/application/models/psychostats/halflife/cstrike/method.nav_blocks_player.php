<?php
/**
 * PsychoStats method Nav_Blocks_Player()
 * $Id$
 *
 *
 */

include dirname(__FILE__) . '/../' . basename(__FILE__);

class   Psychostats_Method_Nav_Blocks_Player_Halflife_Cstrike
extends Psychostats_Method_Nav_Blocks_Player_Halflife {
	public function execute(&$blocks, &$plr, &$stats) {
		parent::execute($blocks, $plr, $stats);

		// dual_bar defaults
		$bar = array(
			'color1' => '00CC00',
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
						'pct1'	=> $stats['ct_wins'] / $stats['wins'] * 100,
						'pct2'	=> $stats['terrorist_wins'] / $stats['wins'] * 100,
						'title1'=> trans('%s CT wins', number_format($stats['ct_wins'])),
						'title2'=> trans('%s Terrorist wins', number_format($stats['terrorist_wins'])),
					)) : '',
					number_format($stats['wins'])),
			),
			'team_losses' => array(
				'row_class' => 'sub',
				'label' => trans('Losses'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s',
					$stats['losses'] ? dual_bar($bar + array(
						'pct1'	=> $stats['ct_losses'] / $stats['losses'] * 100,
						'pct2'	=> $stats['terrorist_losses'] / $stats['losses'] * 100,
						'title1'=> trans('%s CT losses', number_format($stats['ct_losses'])),
						'title2'=> trans('%s Terrorist losses', number_format($stats['terrorist_losses'])),
					)) : '',
					number_format($stats['losses'])),
			),
			'bombs' => array(
				'row_class' => 'hdr',
				'label' => trans('Bombs'),
			),
			'bomb_exploded' => array(
				'row_class' => 'sub',
				'label' => trans('Exploded'),
				'value' => $stats['bomb_planted']
					? sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['bomb_exploded'] / $stats['bomb_planted'] * 100), number_format($stats['bomb_exploded']))
					: number_format($stats['bomb_exploded'])
			),
			'bomb_planted' => array(
				'row_class' => 'sub',
				'label' => trans('Planted'),
				'value' => number_format($stats['bomb_planted']),
			),
			'bomb_defused' => array(
				'row_class' => 'sub',
				'label' => trans('Defused'),
				'value' => number_format($stats['bomb_defused']),
			),

			'hostages' => array(
				'row_class' => 'hdr',
				'label' => trans('Hostages'),
			),
			'hostages_rescued' => array(
				'row_class' => 'sub',
				'label' => trans('Rescued'),
				'value' => $stats['hostages_touched']
					? sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['hostages_rescued'] / $stats['hostages_touched'] * 100), number_format($stats['hostages_rescued']))
					: number_format($stats['hostages_rescued'])
			),
			'hostages_touched' => array(
				'row_class' => 'sub',
				'label' => trans('Touched'),
				'value' => number_format($stats['hostages_touched']),
			),
			'hostages_killed' => array(
				'row_class' => 'sub',
				'label' => trans('Killed'),
				'value' => number_format($stats['hostages_killed']),
			),
		);
			
		array_push_after($blocks['player_kill_profile']['rows'],
			'kills',
			array( 
			       'row_class' => 'sub',
			       'label' => trans('Killed'),
			       'value' => sprintf('<div class="pct-stat">%s</div>&nbsp;',
				       $stats['kills'] ? dual_bar($bar + array(
					       'pct1'	=> $stats['killed_ct'] / $stats['kills'] * 100,
					       'pct2'	=> $stats['killed_terrorist'] / $stats['kills'] * 100,
					       'title1'=> trans('%s CT kills', number_format($stats['killed_ct'])),
					       'title2'=> trans('%s Terrorist kills', number_format($stats['killed_terrorist'])),
				       )) : ''
			       ),
			),
			'killed'
		);

		array_push_after($blocks['player_kill_profile']['rows'],
			'kills_per_minute',
			array(
				'row_class' => 'sub',
				'label' => sprintf('<acronym title="%s">%s</acronym>', trans('Kills per Round'), trans('KpR')),
				'value' => $stats['kills_per_round'],
			),
			'kills_per_round'
		);

		// deaths
		array_push_after($blocks['player_kill_profile']['rows'],
			'deaths',
			array( 
			       'row_class' => 'sub',
			       'label' => trans('Deaths From'),
			       'value' => sprintf('<div class="pct-stat">%s</div>&nbsp;',
				       $stats['deaths'] ? dual_bar($bar + array(
					       'pct1'	=> $stats['deathsby_ct'] / $stats['deaths'] * 100,
					       'pct2'	=> $stats['deathsby_terrorist'] / $stats['deaths'] * 100,
					       'title1'=> trans('%s deaths from CT', number_format($stats['deathsby_ct'])),
					       'title2'=> trans('%s deaths from Terrorist', number_format($stats['deathsby_terrorist'])),
				       )) : ''
			       ),
			),
			'deathsby'
		);

	}
}

?>
