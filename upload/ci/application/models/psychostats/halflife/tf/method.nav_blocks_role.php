<?php
/**
 * PsychoStats method role_nav_blocks()
 * $Id$
 *
 *
 */

include dirname(__FILE__) . '/../' . basename(__FILE__);

class   Psychostats_Method_Nav_Blocks_Role_Halflife_Tf
extends Psychostats_Method_Nav_Blocks_Role_Halflife {
	public function execute(&$blocks, &$plr, &$stats) {
		parent::execute($blocks, $plr, $stats);

		// dual_bar defaults
		$bar = array(
			'color1' => '0000CC',
			'color2' => 'CC0000',
		);

		if (!array_key_exists('role_actions', $blocks)) {
			array_push_after($blocks, 'role_kill_profile', array(
				'title' => trans('Role Actions'),
				'rows' => array(),
			), 'role_actions');
		}
		
		$blocks['role_actions']['rows'] += array(
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
		
		array_push_after($blocks['role_kill_profile']['rows'],
				 'headshot_kills',
				 array( 
					'row_class' => 'sub',
					'label' => trans('Backstabs'),
					'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['backstab_kills_pct']), number_format($stats['backstab_kills'])),
				 ),
				 'backstab_kills'
		);

		array_push_after($blocks['role_kill_profile']['rows'],
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
