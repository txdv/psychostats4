<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {iconimg} function plugin
 *
 * Type:     function<br>
 * Name:     iconimg<br>
 * Purpose:  returns the <img> tag for icon file given,
		if no image is found a spacer image is returned to fill in the space.
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_iconimg($args, &$smarty)
{
  global $ps;
  $args += array(
	'var'		=> '',
	'icon'		=> '',
	'height'	=> '15',
	'width'		=> '15',
	'forcesize'	=> 0,
	'align'		=> 'absmiddle',
	'valign'	=> '',
	'style'		=> '',
	'id'		=> '',
	'noimg'		=> NULL,
  );
  $img = "";
  $file = "";
  if (!empty($args['icon'])) {
    $icon = $args['icon'];
    $file = catfile($ps->conf['theme']['rooticonsdir'], $icon);
    $url = $ps->conf['theme']['rooticonsurl'] . $icon;
  }
  if (!$file or !@file_exists($file) or !$icon) {
      $icon = 'spacer.gif';
      $file = catfile($smarty->get_template_vars('imagesdir'), $icon);
      $url = $smarty->get_template_vars('imagesurl') . $icon;
  }

  if (@file_exists($file)) {
    $attrs = " ";
    if ($icon != 'spacer.gif' and !$args['forcesize']) {
      $size = getimagesize($file);
      $args['width'] = $size[0];
      $args['height'] = $size[1];
    }
    if (is_numeric($args['width'])) $attrs .= "width='" . $args['width'] . "' ";
    if (is_numeric($args['height'])) $attrs .= "height='" . $args['height'] . "' ";
    if (!empty($args['align'])) $attrs .= "align='" . $args['align'] . "' ";
    if (!empty($args['valign'])) $attrs .= "valign='" . $args['valign'] . "' ";
    if (!empty($args['style'])) $attrs .= "style='" . $args['style'] . "' ";
    if (!empty($args['id'])) $attrs .= "id='" . $args['id'] . "' ";
    $img = "<img src='$url' $attrs border='0' />";
  } else {
    $img = $args['noimg'] !== NULL ? $args['noimg'] : '';
  }

  if (!$args['var']) return $img;
  $smarty->assign($args['var'], $img);
}

?>
