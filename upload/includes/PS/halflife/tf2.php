<?php
/**
	PS::halflife::tf2
	$Id$

	Halflife::tf2 mod support for PsychoStats front-end
*/
if (!defined("PSYCHOSTATS_PAGE")) die("Unauthorized access to " . basename(__FILE__));
if (defined("CLASS_PS_HALFLIFE_TF2_PHP")) return 1;
define("CLASS_PS_HALFLIFE_TF2_PHP", 1);

include_once(dirname(dirname(__FILE__)) . '/halflife.php');

class PS_halflife_tf2 extends PS_halflife {

var $class = 'PS::halflife::tf2';
var $use_roles = true;

var $CLAN_MODTYPES = array(
	'redkills'		=> '+',
	'bluekills'		=> '+',
	'reddeaths'		=> '+',
	'bluedeaths'		=> '+',

	'redwon'		=> '+',
	'redwonpct'		=> array( 'percent2', 'redwon', 'bluewon' ),
	'bluewon'		=> '+',
	'bluewonpct'		=> array( 'percent2', 'bluewon', 'redwon' ),
	'redlost'		=> '+',
	'bluelost'		=> '+',

	'assists'		=> '+',
	'redassists'		=> '+',
	'blueassists'		=> '+',

	'flagscaptured'		=> '+',

	'redcaptureblocked'	=> '+',
	'redcaptureblockedpct'	=> array( 'percent', 'redcaptureblocked', 'captureblocked' ),
	'redpointcaptured'	=> '+',
	'redpointcapturedpct'	=> array( 'percent', 'redpointcaptured', 'pointcaptured' ),
	'redflagsdefended'	=> '+',
	'redflagsdefendedpct'	=> array( 'percent', 'redflagsdefended', 'flagsdefended' ),
	'redflagsdropped'	=> '+',
	'redflagspickedup'	=> '+',
	'redflagscaptured'	=> '+',
	'redflagscapturedpct'	=> array( 'percent', 'redflagscaptured', 'flagscaptured' ),

	'bluecaptureblocked'	=> '+',
	'bluecaptureblockedpct'	=> array( 'percent', 'bluecaptureblocked', 'captureblocked' ),
	'bluepointcaptured'	=> '+',
	'bluepointcapturedpct'	=> array( 'percent', 'bluepointcaptured', 'pointcaptured' ),
	'blueflagsdefended'	=> '+',
	'blueflagsdefendedpct'	=> array( 'percent', 'blueflagsdefended', 'flagsdefended' ),
	'blueflagsdropped'	=> '+',
	'blueflagspickedup'	=> '+',
	'blueflagscaptured'	=> '+',
	'blueflagscapturedpct'	=> array( 'percent', 'blueflagscaptured', 'flagscaptured' ),

	'dispenserdestroy'	=> '+',
	'sentrydestroy'		=> '+',
	'sapperdestroy'		=> '+',
	'teleporterdestroy'	=> '+',
	'dominations'		=> '+',
	'backstabkills'		=> '+',
	'itemsbuilt'		=> '+',
	'chargedeployed'	=> '+',
	'revenge'		=> '+',

	'joinedred'		=> '+',
	'joinedblue'		=> '+'
);

function PS_halflife_tf2(&$db) {
	parent::PS_halflife($db);
	$this->CLAN_MAP_MODTYPES = $this->CLAN_MODTYPES;
}

function add_map_player_list_mod($map, $setup = array()) {
	global $cms;
	$this->add_map_player_list('backstabkills',  $setup + array('label' => $cms->trans("Most Backstabs")) );
	$this->add_map_player_list('dominations',  $setup + array('label' => $cms->trans("Most Dominations")) );

	$prefix = substr($map['uniqueid'], 0, 3);
	if ($prefix == 'ctf') {
		$this->add_map_player_list('flagscaptured', $setup + array('label' => $cms->trans("Most Flags Captured")) );
		$this->add_map_player_list('flagsdefended', $setup + array('label' => $cms->trans("Most Flags Defended")) );
	} else {
		$this->add_map_player_list('captureblocked', $setup + array('label' => $cms->trans("Most Blocked Captures")) );
		$this->add_map_player_list('pointcaptured', $setup + array('label' => $cms->trans("Most Points Captured")) );
	}
}

// Add or remove columns from maps.php listing
function maps_table_mod(&$table) {
	global $cms;
	$table->remove_columns('rounds');
	$table->insert_columns(
		array( 
			'bluekillspct' => array( 'label' => 'Kill Ratio', 'callback' => array(&$this, 'team_wins'), 'tooltip' => $cms->trans("Blue / Red Kill Ratio") ), 
		),
		'onlinetime',
		false
	);
}

// Add or remove columns from roles.php listing
function roles_table_mod(&$table) { 
	global $cms;
	$table->insert_columns(
		array( 
			'backstabkills' => array( 'label' => 'BS', 'modifier' => 'commify', 'tooltip' => $cms->trans("Backstab Kills") ),
			'backstabkillspct' => array( 'label' => 'BS%', 'modifier' => '%s%%', 'tooltip' => $cms->trans("Backstab Kills Percentage") ),
		),
		'headshotkillspct',
		true
	);
}

function player_roles_table_mod(&$table) {
	$this->roles_table_mod($table);
}

function map_left_column_mod(&$map, &$theme) {
	// maps and players have the same stats ...
	$this->player_left_column_mod($map, $theme);
	$theme->assign('map_left_column_mod', $theme->get_template_vars('player_left_column_mod'));
}

function clan_left_column_mod(&$clan, &$theme) {
	// clans and players have the same stats ...r
	$this->player_left_column_mod($clan, $theme);
	$theme->assign('clan_left_column_mod', $theme->get_template_vars('player_left_column_mod'));
}

function player_left_column_mod(&$plr, &$theme) {
	global $cms;
	static $strings = array();
	if (!$strings) {
		$strings = array(
			'flagscaptured'		=> $cms->trans("Flags Captured"),
			'flagsdefended'		=> $cms->trans("Flags Defended"),
			'captureblocked'	=> $cms->trans("Captures Blocked"),
			'pointcaptured'		=> $cms->trans("Points Captured")
		);
	}
	$tpl = 'player_left_column_mod';
	if ($theme->template_found($tpl, false)) {
		$actions = array();
		foreach (array('flagscaptured', 'flagsdefended', 'captureblocked', 'pointcaptured') as $var) {
			$actions[] = array(
				'label'	=> $strings[$var],
				'value'	=> dual_bar(array(
					'pct1'	 	=> $plr['red' . $var . 'pct'],
					'pct2'	 	=> $plr['blue' . $var . 'pct'],
					'title1'	=> $plr['red' . $var] . ' Red (' . $plr['red' . $var . 'pct'] . '%)',
					'title2'	=> $plr['blue' . $var] . ' Blue (' . $plr['blue' . $var . 'pct'] . '%)',
					'color1'	=> 'cc0000',
					'color2'	=> '0000cc',
					'width'		=> 130
				))
			);
		}

		$theme->assign(array(
			'mod_actions'	=> $actions,
			'mod_actions_title' => $cms->trans("Team / Action Profile"),
		));
		$output = $theme->parse($tpl);
		$theme->assign('player_left_column_mod', $output);
	}
}

// used in maps.php as a callback for the wins of each team
function team_wins($value, $data) {
	global $cms;
	$bar = dual_bar(array(
		'pct1'	=> $data['redkillspct'], 
		'pct2'	=> $data['bluekillspct'],
		'title1'=> $cms->trans("Red Kills") . " (" . $data['redkillspct'] . "%)",
		'title2'=> $cms->trans("Blue Kills") . " (" . $data['bluekillspct'] . "%)",
		'color1'=> 'cc0000',
		'color2'=> '0000cc',
	));
/*
	$bar = dual_bar(array(
		'pct1'	=> $data['redwonpct'], 
		'pct2'	=> $data['bluewonpct'],
		'title1'=> $cms->trans("Red Wins") . " (" . $data['redwonpct'] . "%)",
		'title2'=> $cms->trans("Blue Wins") . " (" . $data['bluewonpct'] . "%)",
		'color1'=> 'cc0000',
		'color2'=> '0000cc',
	));
*/
	return $bar;
}

} // end of ps::halflife::tf2

?>
