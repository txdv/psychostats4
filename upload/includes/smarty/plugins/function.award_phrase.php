<?php

function smarty_function_award_phrase($params, &$smarty) {
	global $ps;
	$award = $params['award'];			// combined array of the award and player data
	$phrase = $award['phrase'];;
	$award['class'] = $params['class'];

	$award['value'] = $ps->award_format($award['topplrvalue'], $award['format']);
	$award['link'] = ps_table_plr_link($award['name'], $award);
	$tokens = array(
		'player' 	=> &$award,
		'weapon'	=> &$award,
		'award' 	=> &$award,
	);
	return simple_interpolate($phrase, $tokens);
}
?>
