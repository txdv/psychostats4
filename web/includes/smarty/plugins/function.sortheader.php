<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {mapimg} function plugin
 *
 * Type:     function<br>
 * Name:     sortheader<br>
 * Purpose:  returns an <a href> link for header columns in a table to allow the table to be sorted.
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_sortheader($args, &$smarty)
{
  global $ps;
  static $altorder = array('asc' => 'desc', 'desc' => 'asc');
  static $baseurl = "";
  static $urltail = "";
  static $prefix = "";
  static $cursort = "";
  static $order = "";
  static $ordervar = "order";
  static $sortvar = "sort";
  $image = "themes/" . $ps->conf['main']['theme'] . "/images/sort_arrow_%s.gif";
  $args += array(
	'var'		=> '',
	'baseurl' 	=> '',
	'urltail'	=> '',
	'order'		=> 'desc',
	'ordervar'	=> '',
	'sort'		=> '',
	'sortvar'	=> '',
	'cursort'	=> '',
	'image'		=> '',
	'prefix'	=> '',
	'label'		=> '',
	'title'		=> '',
	'style'		=> '',
	'class'		=> '',
  );
  if ($args['image']) $image = $args['image'];
  if ($args['title']) $args['title'] = " title='{$args['title']}'";
  if ($args['style']) $args['style'] = " style='{$args['style']}'";
  if ($args['class']) $args['class'] = " class='{$args['class']}'";
  if ($args['baseurl']) $baseurl = $args['baseurl'];
  if ($args['prefix']) $prefix = $args['prefix'];
  if ($args['urltail']) $urltail = $args['urltail'];
  if ($args['cursort']) $cursort = $args['cursort'];
  if ($args['order']) $order = $args['order'];
  if ($args['ordervar']) $ordervar = $args['ordervar'];
  if ($args['sortvar']) $sortvar = $args['sortvar'];
  $urlsep = (strpos($baseurl, '?') === FALSE) ? "?" : "&";
  $neworder = ($args['sort'] == $cursort) ? $altorder[ strtolower($order) ] : $order;
//  $href = "<a href='$baseurl{$urlsep}{$prefix}{$sortvar}={$args['sort']}&{$prefix}{$ordervar}=$neworder$urltail'{$args['class']}>";
  $href = "<a href='" . ps_url_wrapper(array(
	'_base' 		=> $baseurl,
	'_anchor'		=> $urltail,
	"$prefix$sortvar" 	=> $args['sort'],
	"$prefix$ordervar"	=> $neworder
  )) . "'{$args['class']}>";
  if ($args['sort'] == $cursort) $href .= " <img src='" . sprintf($image, $order) . "' border='0'>";
#  $href = "<a onmouseover='getobj(\"orderimg\").src=\"" . sprintf($image, $altorder[$order]) . "\"' onmouseout='getobj(\"orderimg\").src=\"" . sprintf($image, $order) . "\"' href='$baseurl{$urlsep}{$prefix}{$sortvar}={$args['sort']}&{$prefix}{$ordervar}=$neworder$urltail'{$args['title']}>{$args['label']}";
#  if ($args['sort'] == $cursort) $href .= " <img id='orderimg' src='" . sprintf($image, $order) . "' border='0'>";
  if ($args['title']) {
    $href .= "<acronym {$args['title']}>{$args['label']}</acronym>";
  } else {
    $href .= $args['label'];
  }
  $href .= "</a>";

  if (!$args['var']) return $href;
  $smarty->assign($args['var'], $href);
}

?>
