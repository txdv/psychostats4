<?php
/**
 * Smarty plugin	-- Stormtrooper at psychostats dot com
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty PS3 skillchange function plugin
 *
 * Type:     function<br>
 * Name:     skillchange<br>
 * Purpose:  outputs the proper img tag for the change in skill
 * @param string
 * @return string
 */
function smarty_function_skillchange($args, &$smarty)
{
	$args += array(
		'var'		=> "",
		'plr'		=> NULL,
		'skill'		=> 0,
		'prevskill'	=> 0,
		'imgfmt'	=> "skill_%s.gif",
		'difffmt'	=> "%.02f",
		'attr'		=> "",
		'acronym'	=> 1,
	);

	$output = "no change";
	$skill = $prevskill = 0;
	if (is_array($args['plr'])) {
		$skill = $args['plr']['skill'];
		$prevskill = $args['plr']['prevskill'];
	} else {
		$skill = $args['skill'];
		$prevskill = $args['prevskill'];
	}

	$alt = "no change";
	$dir = "same";
	$diff = sprintf($args['difffmt'], $skill - $prevskill);
	if ($diff > 0) {
		$dir = "up";
		$alt = "Diff: +$diff";
	} elseif ($diff < 0) {
		$dir = "down";
		$alt = "Diff: $diff";
	}
	$output = sprintf("<img src='%s' alt='%s' title='%s' border='0' align='absmiddle' {$args['attr']}>", 
		$smarty->get_template_vars('imagesurl') . sprintf($args['imgfmt'], $dir),
		$alt, $alt
	);

	if ($args['acronym']) {
		$output = "<acronym title='$alt'>$output</acronym>";
	}

	if (!$args['var']) return $output;
	$smarty->assign($args['var'], $output);
}

?>
