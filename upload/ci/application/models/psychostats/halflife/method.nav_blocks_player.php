<?php
/**
 * PsychoStats method Nav_Blocks_Player()
 * $Id$
 *
 *
 */

class Psychostats_Method_Nav_Blocks_Player_Halflife extends Psychostats_Method {
	public function execute(&$blocks, &$plr, &$stats) {
		if (!isset($blocks['player_kill_profile'])) {
			$blocks['player_kill_profile'] = array(
				'title'	=> trans('Kill Profile'),
				'rows' => array()
			);
		}

		$blocks['player_kill_profile']['rows'] = array( 
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
			'kill_streak' => array(
				'row_class' => 'sub',
				'label' => trans('Streak'),
				'value' => number_format($stats['kill_streak']),
			),
			'team_kills' => array(
				'row_class' => 'sub',
				'label' => trans('Friendly'),
				'value' => number_format($stats['team_kills']),
			),
			'kills_per_death' => array(
				'row_class' => 'sub',
				'label' => sprintf('<acronym title="%s">%s</acronym>', trans('Kills per Death'), trans('KpD')),
				'value' => $stats['kills_per_death'],
			),
			'kills_per_minute' => array(
				'row_class' => 'sub',
				'label' => sprintf('<acronym title="%s">%s</acronym>', trans('Kills per Minute'), trans('KpM')),
				'value' => $stats['kills_per_minute'],
			),
			'deaths' => array(
				'row_class' => 'hdr',
				'label' => trans('Deaths'),
				'value' => number_format($stats['deaths']),
			),
			'headshot_deaths' => array(
				'row_class' => 'sub',
				'label' => trans('Headshots'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['headshot_deaths_pct']), number_format($stats['headshot_deaths'])),
			),
			'death_streak' => array(
				'row_class' => 'sub',
				'label' => trans('Streak'),
				'value' => number_format($stats['death_streak']),
			),
			'team_deaths' => array(
				'row_class' => 'sub',
				'label' => trans('Friendly'),
				'value' => number_format($stats['team_deaths']),
			),
			'suicides' => array(
				'row_class' => 'sub',
				'label' => trans('Suicides'),
				'value' => number_format($stats['suicides']),
			),
			'damages' => array(
				'row_class' => 'hdr',
				'label' => trans('Damage'),
			),
			'damage' => array(
				'row_class' => 'sub',
				'label' => trans('Caused'),
				'value' => number_format($stats['damage']),
			),
			'damage_absorbed' => array(
				'row_class' => 'sub',
				'label' => trans('Absorbed'),
				'value' => $stats['damage']
					? sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['damage_absorbed'] / $stats['damage'] * 100), number_format($stats['damage_absorbed']))
					: $stats['damage_absorbed']
			),
			'damage_taken' => array(
				'row_class' => 'sub',
				'label' => trans('Taken'),
				'value' => number_format($stats['damage_taken']),
			),
			'damage_mitigated' => array(
				'row_class' => 'sub',
				'label' => trans('Mitigated'),
				'value' => $stats['damage']
					? sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['damage_mitigated'] / $stats['damage'] * 100), number_format($stats['damage_mitigated']))
					: $stats['damage_mitigated']
			),
		) + $blocks['player_kill_profile']['rows'];

		if (!isset($blocks['player_accuracy_profile'])) {
			$blocks['player_accuracy_profile'] = array(
				'title'	=> trans('Accuracy Profile'),
				'rows' => array()
			);
		}
		$blocks['player_accuracy_profile']['rows'] = array( 
			'accuracy' => array(
				'row_class' => 'hdr',
				'label' => trans('Accuracy'),
				'value' => sprintf('<div class="pct-stat">%s</div>%0.02f<small>%%</small>', pct_bar($stats['accuracy']), $stats['accuracy']),
			),
			'shots' => array(
				'row_class' => 'sub',
				'label' => trans('Shots'),
				'value' => number_format($stats['shots']),
			),
			'hits' => array(
				'row_class' => 'sub',
				'label' => trans('Hits'),
				'value' => number_format($stats['hits']),
			),

		) + $blocks['player_accuracy_profile']['rows'];

		// add overall hitbox totals
		if (true or $stats['hits']) {
			$blocks['player_accuracy_profile']['rows']['hitbox'] = array(
				'row_class' => 'hdr',
				'label' => trans('Hitbox'),
			);
			$blocks['player_accuracy_profile']['rows']['hit_head'] = array(
				'row_class' => 'sub',
				'label' => trans('Head'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['hit_head_pct']), number_format($stats['hit_head'])),
			);
			$blocks['player_accuracy_profile']['rows']['hit_chest'] = array(
				'row_class' => 'sub',
				'label' => trans('Chest'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['hit_chest_pct']), number_format($stats['hit_chest'])),
			);
			$blocks['player_accuracy_profile']['rows']['hit_leftarm'] = array(
				'row_class' => 'sub',
				'label' => trans('Left Arm'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['hit_leftarm_pct']), number_format($stats['hit_leftarm'])),
			);
			$blocks['player_accuracy_profile']['rows']['hit_rightarm'] = array(
				'row_class' => 'sub',
				'label' => trans('Right Arm'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['hit_rightarm_pct']), number_format($stats['hit_rightarm'])),
			);
			$blocks['player_accuracy_profile']['rows']['hit_stomach'] = array(
				'row_class' => 'sub',
				'label' => trans('Stomach'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['hit_stomach_pct']), number_format($stats['hit_stomach'])),
			);
			$blocks['player_accuracy_profile']['rows']['hit_leftleg'] = array(
				'row_class' => 'sub',
				'label' => trans('Left Leg'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['hit_leftleg_pct']), number_format($stats['hit_leftleg'])),
			);
			$blocks['player_accuracy_profile']['rows']['hit_rightleg'] = array(
				'row_class' => 'sub',
				'label' => trans('Right Leg'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['hit_rightleg_pct']), number_format($stats['hit_rightleg'])),
			);
		}

		if (true or $stats['damage']) {
			$blocks['player_accuracy_profile']['rows']['dmgbox'] = array(
				'row_class' => 'hdr',
				'label' => sprintf('<acronym title="%s">%s</acronym>', trans('Damage Hitbox'), trans('Dmgbox')),
			);
			$blocks['player_accuracy_profile']['rows']['dmg_head'] = array(
				'row_class' => 'sub',
				'label' => trans('Head'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['dmg_head_pct']), abbrnum($stats['dmg_head'], 2)),
			);
			$blocks['player_accuracy_profile']['rows']['dmg_chest'] = array(
				'row_class' => 'sub',
				'label' => trans('Chest'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['dmg_chest_pct']), number_format($stats['dmg_chest'])),
			);
			$blocks['player_accuracy_profile']['rows']['dmg_leftarm'] = array(
				'row_class' => 'sub',
				'label' => trans('Left Arm'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['dmg_leftarm_pct']), number_format($stats['dmg_leftarm'])),
			);
			$blocks['player_accuracy_profile']['rows']['dmg_rightarm'] = array(
				'row_class' => 'sub',
				'label' => trans('Right Arm'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['dmg_rightarm_pct']), number_format($stats['dmg_rightarm'])),
			);
			$blocks['player_accuracy_profile']['rows']['dmg_stomach'] = array(
				'row_class' => 'sub',
				'label' => trans('Stomach'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['dmg_stomach_pct']), number_format($stats['dmg_stomach'])),
			);
			$blocks['player_accuracy_profile']['rows']['dmg_leftleg'] = array(
				'row_class' => 'sub',
				'label' => trans('Left Leg'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['dmg_leftleg_pct']), number_format($stats['dmg_leftleg'])),
			);
			$blocks['player_accuracy_profile']['rows']['dmg_rightleg'] = array(
				'row_class' => 'sub',
				'label' => trans('Right Leg'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['dmg_rightleg_pct']), number_format($stats['dmg_rightleg'])),
			);
		}
	} 
} 

?>