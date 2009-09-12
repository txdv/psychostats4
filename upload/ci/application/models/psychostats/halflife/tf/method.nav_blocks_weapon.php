<?php
/**
 * PsychoStats method weapon_nav_blocks()
 * $Id$
 *
 *
 */

include dirname(__FILE__) . '/../' . basename(__FILE__);

class   Psychostats_Method_Nav_Blocks_Weapon_Halflife_Tf
extends Psychostats_Method_Nav_Blocks_Weapon_Halflife {
	public function execute(&$blocks, &$plr, &$stats) {
		parent::execute($blocks, $plr, $stats);

		// dual_bar defaults
		$bar = array(
			'color1' => '0000CC',
			'color2' => 'CC0000',
		);
		
		array_push_after($blocks['weapon_kill_profile']['rows'],
				 'headshot_kills',
				 array( 
					'row_class' => 'sub',
					'label' => trans('Backstabs'),
					'value' => sprintf('<div class="pct-stat">%s</div>%s', pct_bar($stats['backstab_kills_pct']), number_format($stats['backstab_kills'])),
				 ),
				 'backstab_kills'
		);

	}
}

?>
