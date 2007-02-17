<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {hitboxswfurl} function plugin
 *
 * Type:     function<br>
 * Name:     hitboxswfurl<br>
 * Purpose:  returns the query string to be used on the hitbox flash object
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_hitboxswfurl($args, &$smarty)
{
  $args += array(
	'var'		=> '',
	'weapon'	=> array(),
  );
  $url = "";
  $weapon = $args['weapon'];

  if (is_array($weapon)) {
    $label = $weapon['desc'] ? $weapon['desc'] : $weapon['name'];
    $url .= "wname=" . urlencode($label). "&";
    $url .= "head={$weapon['shot_head']}&";
    $url .= "leftarm={$weapon['shot_leftarm']}&";
    $url .= "rightarm={$weapon['shot_rightarm']}&";
    $url .= "chest={$weapon['shot_chest']}&";
    $url .= "stomach={$weapon['shot_stomach']}&";
    $url .= "leftleg={$weapon['shot_leftleg']}&";
    $url .= "rightleg={$weapon['shot_rightleg']}";
  }

  if (!$args['var']) return $url;
  $smarty->assign($args['var'], $url);
}

?>
