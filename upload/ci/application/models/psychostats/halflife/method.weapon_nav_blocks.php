<?php
/**
 * PsychoStats method weapon_nav_blocks()
 * $Id$
 *
 *
 */

class Psychostats_Method_Weapon_Nav_Blocks_Halflife extends Psychostats_Method {
	public function execute(&$blocks, &$plr, &$stats) {
		if (!isset($blocks['weapon_kill_profile'])) {
			$blocks['weapon_kill_profile'] = array(
				'title'	=> trans('Kill Profile'),
				'rows' => array()
			);
		}

		$blocks['weapon_kill_profile']['rows'] = array( 
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
			'suicides' => array(
				'row_class' => 'sub',
				'label' => trans('Suicides'),
				'value' => number_format($stats['suicides']),
			),
		) + $blocks['weapon_kill_profile']['rows'];

		if (!isset($blocks['weapon_accuracy_profile'])) {
			$blocks['weapon_accuracy_profile'] = array(
				'title'	=> trans('Accuracy Profile'),
				'rows' => array()
			);
		}
		
		$blocks['weapon_accuracy_profile']['rows'] = array( 
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

		) + $blocks['weapon_accuracy_profile']['rows'];

		// add overall hitbox totals
		if (true or $stats['hits']) {
			$blocks['weapon_accuracy_profile']['rows']['hitbox'] = array(
				'row_class' => 'hdr',
				'label' => trans('Hitbox'),
			);
			$blocks['weapon_accuracy_profile']['rows']['hit_head'] = array(
				'row_class' => 'sub',
				'label' => trans('Head'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['hit_head_pct']), number_format($stats['hit_head'])),
			);
			$blocks['weapon_accuracy_profile']['rows']['hit_chest'] = array(
				'row_class' => 'sub',
				'label' => trans('Chest'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['hit_chest_pct']), number_format($stats['hit_chest'])),
			);
			$blocks['weapon_accuracy_profile']['rows']['hit_leftarm'] = array(
				'row_class' => 'sub',
				'label' => trans('Left Arm'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['hit_leftarm_pct']), number_format($stats['hit_leftarm'])),
			);
			$blocks['weapon_accuracy_profile']['rows']['hit_rightarm'] = array(
				'row_class' => 'sub',
				'label' => trans('Right Arm'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['hit_rightarm_pct']), number_format($stats['hit_rightarm'])),
			);
			$blocks['weapon_accuracy_profile']['rows']['hit_stomach'] = array(
				'row_class' => 'sub',
				'label' => trans('Stomach'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['hit_stomach_pct']), number_format($stats['hit_stomach'])),
			);
			$blocks['weapon_accuracy_profile']['rows']['hit_leftleg'] = array(
				'row_class' => 'sub',
				'label' => trans('Left Leg'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['hit_leftleg_pct']), number_format($stats['hit_leftleg'])),
			);
			$blocks['weapon_accuracy_profile']['rows']['hit_rightleg'] = array(
				'row_class' => 'sub',
				'label' => trans('Right Leg'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['hit_rightleg_pct']), number_format($stats['hit_rightleg'])),
			);
		}

		if (true or $stats['damage']) {
			$blocks['weapon_accuracy_profile']['rows']['dmgbox'] = array(
				'row_class' => 'hdr',
				'label' => sprintf('<acronym title="%s">%s</acronym>', trans('Damage Hitbox'), trans('Dmgbox')),
			);
			$blocks['weapon_accuracy_profile']['rows']['dmg_head'] = array(
				'row_class' => 'sub',
				'label' => trans('Head'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['dmg_head_pct']), abbrnum($stats['dmg_head'], 2)),
			);
			$blocks['weapon_accuracy_profile']['rows']['dmg_chest'] = array(
				'row_class' => 'sub',
				'label' => trans('Chest'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['dmg_chest_pct']), number_format($stats['dmg_chest'])),
			);
			$blocks['weapon_accuracy_profile']['rows']['dmg_leftarm'] = array(
				'row_class' => 'sub',
				'label' => trans('Left Arm'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['dmg_leftarm_pct']), number_format($stats['dmg_leftarm'])),
			);
			$blocks['weapon_accuracy_profile']['rows']['dmg_rightarm'] = array(
				'row_class' => 'sub',
				'label' => trans('Right Arm'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['dmg_rightarm_pct']), number_format($stats['dmg_rightarm'])),
			);
			$blocks['weapon_accuracy_profile']['rows']['dmg_stomach'] = array(
				'row_class' => 'sub',
				'label' => trans('Stomach'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['dmg_stomach_pct']), number_format($stats['dmg_stomach'])),
			);
			$blocks['weapon_accuracy_profile']['rows']['dmg_leftleg'] = array(
				'row_class' => 'sub',
				'label' => trans('Left Leg'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['dmg_leftleg_pct']), number_format($stats['dmg_leftleg'])),
			);
			$blocks['weapon_accuracy_profile']['rows']['dmg_rightleg'] = array(
				'row_class' => 'sub',
				'label' => trans('Right Leg'),
				'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['dmg_rightleg_pct']), number_format($stats['dmg_rightleg'])),
			);
		}
	} 
} 

?>