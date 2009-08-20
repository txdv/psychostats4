<?php
/**
 * PsychoStats method role_nav_blocks()
 * $Id: method.role_nav_blocks.php 624 2009-08-20 11:16:44Z lifo $
 *
 *
 */

class Psychostats_Method_Role_Nav_Blocks_Halflife extends Psychostats_Method {
	public function execute(&$blocks, &$plr, &$stats) {
		if (!isset($blocks['role_kill_profile'])) {
			$blocks['role_kill_profile'] = array(
				'title'	=> trans('Kill Profile'),
				'rows' => array()
			);
		}
		$blocks['role_kill_profile']['rows'] = array( 
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
		) + $blocks['role_kill_profile']['rows'];

	} 
} 

?>
