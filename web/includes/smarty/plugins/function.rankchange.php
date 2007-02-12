<?php
/**
 * Smarty plugin	-- Stormtrooper at psychostats dot com
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty PS3 rankchange function plugin
 *
 * Type:     function<br>
 * Name:     rankchange<br>
 * Purpose:  outputs the proper img tag for the change in rank
 * @param string
 * @return string
 */
function smarty_function_rankchange($args, &$smarty)
{
	$args += array(
		'var'		=> "",
		'plr'		=> NULL,
		'rank'		=> 0,
		'prevrank'	=> 0,
		'imgfmt'	=> "rank_%s.gif",
		'difffmt'	=> "%d",
		'attr'		=> "",
		'acronym'	=> 1,
		'textonly'	=> 0,
	);

	$output = "";
	$rank = $prevrank = 0;
	if (is_array($args['plr'])) {
		$rank = $args['plr']['rank'];
		$prevrank = $args['plr']['prevrank'];
	} else {
		$rank = $args['rank'];
		$prevrank = $args['prevrank'];
	}

	$alt = "no change";
	$dir = "same";
	$diff = sprintf($args['difffmt'], $prevrank - $rank);	# note: LESS is better. Opposite of 'skill'.

	if ($prevrank == 0) {
		# no change
	} elseif ($diff > 0) {
		$dir = "up";
		$alt = "Diff: +$diff";
	} elseif ($diff < 0) {
		$dir = "down";
		$alt = "Diff: $diff";
	}

	if ($args['textonly']) {
		$output = sprintf("<span class='rank%s'>%s%s</span>",
			$dir,
			$diff > 0 ? '+' : '',
			$prevrank == 0 ? '' : $diff
		);
	} else {
		$output = sprintf("<img src='%s' alt='%s' title='%s' border='0' align='absmiddle' {$args['attr']}>", 
			$smarty->get_template_vars('imagesurl') . sprintf($args['imgfmt'], $dir),
			$alt, $alt
		);
		if ($args['acronym']) {
			$output = "<acronym title='$alt'>$output</acronym>";
		}
	}

	if (!$args['var']) return $output;
	$smarty->assign($args['var'], $output);
}

?>
