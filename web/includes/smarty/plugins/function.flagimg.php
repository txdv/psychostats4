<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {flagimg} function plugin
 *
 * Type:     function<br>
 * Name:     flagimg<br>
 * Purpose:  returns the <img> tag for the CC (country code) name,
		if no image is found a spacer image is returned to fill in the space.
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_flagimg($args, &$smarty)
{
  global $ps;
  $args += array(
	'var'		=> '',
	'cc'		=> '',
	'cn'		=> '',
	'height'	=> '12',
	'width'		=> '18',
	'align'		=> '',
	'valign'	=> '',
	'style'		=> '',
	'id'		=> '',
	'ext'		=> '.png',
	'noimg'		=> NULL,
  );
  $img = '';
  if (empty($args['cc'])) $args['cc'] = '00';
  if (!empty($args['cc'])) {
    $cc = strtolower($args['cc']);
    $file = catfile($ps->conf['theme']['rootflagsdir'], $cc . $args['ext']);
    $url = $ps->conf['theme']['rootflagsurl'] . $cc . $args['ext'];
    $label = (!empty($args['cn'])) ? "({$args['cc']}) {$args['cn']}" : $args['cc']; 
    if (!@file_exists($file)) {
      $cc = $args['cc'] = '00';
      $args['cn'] = '';
      $label = "";
      $file = catfile($ps->conf['theme']['rootflagsdir'], $cc . $args['ext']);
      $url = $ps->conf['theme']['rootflagsurl'] . $cc . $args['ext'];
    }

    if (@file_exists($file)) {
      $attrs = " ";
      if (is_numeric($args['width'])) $attrs .= "width='" . $args['width'] . "' ";
      if (is_numeric($args['height'])) $attrs .= "height='" . $args['height'] . "' ";
      if (!empty($args['align'])) $attrs .= "align='" . $args['align'] . "' ";
      if (!empty($args['valign'])) $attrs .= "valign='" . $args['valign'] . "' ";
      if (!empty($args['style'])) $attrs .= "style='" . $args['style'] . "' ";
      if (!empty($args['id'])) $attrs .= "id='" . $args['id'] . "' ";
      $img = "<img src='$url' title='$label' alt='$label' border='0' $attrs/>";
    } else {
      $img = $args['noimg'] !== NULL ? $args['noimg'] : $label;
    }
  }

  if (!$args['var']) return $img;
  $smarty->assign($args['var'], $img);
}

?>
