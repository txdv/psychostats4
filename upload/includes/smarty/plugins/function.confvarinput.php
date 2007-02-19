<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {confvarlayout} function plugin
 *
 * Type:     function<br>
 * Name:     confvarlayout<br>
 * Purpose:  PS3 method to display the statusText layout for the special config variable given
 * @version  1.0
 * @param array
 * @param Smarty
 */
function smarty_function_confvarinput($args, &$smarty)
{
	global $conf_layout, $conf_values;
	$args += array(
		'var'	=> '',
		'xhtml'	=> 1,
	);

	$fullvar = $args['var'];
	$parts = explode(VAR_SEPARATOR, $args['var']);
	array_pop($parts);
	$var = implode(VAR_SEPARATOR, $parts);

	$output = "";
	$options = explode(',', $conf_layout[$var]['options']);
	$type = strtolower($conf_layout[$var]['type']);
	switch ($type) {
		case 'none': 
//			$output = htmlentities($conf_values[$fullvar]);
			break;

		case 'checkbox':
			$output .= sprintf("<input name='opts[%s]' value='1' type='checkbox'%s onclick='web.conf_change(this)'%s>",
				htmlentities($args['var']), 
				$conf_values[$fullvar] ? ' checked ' : '',
				$args['xhtml'] ? ' /' : ''
			);
			break;

		case 'select':
			$output = sprintf("<select name='opts[%s]' class='field' onchange='web.conf_change(this)'>", 
				htmlentities($args['var'])
			);
			$labels = varinput_build_select($conf_layout[$var]['options']);
			foreach ($labels as $label => $value) {
				$output .= sprintf("<option value=\"%s\"%s>%s</option>\n",
					htmlentities($value),
					$value == $conf_values[$fullvar] ? ' selected ' : '',
					htmlentities($label, ENT_COMPAT, "UTF-8")
				);
			}
			$output .= "</select>";
			break;

		case 'boolean':
			$idx = 0;
			$labels = varinput_build_boolean($conf_layout[$var]['options']);
			foreach ($labels as $label => $value) {
				$for = str_replace(VAR_SEPARATOR, '-', $args['var']) . "-" . ++$idx;
				$output .= sprintf("<input id='$for' name='opts[%s]' value=\"%s\" type='radio'%s onchange='web.conf_change(this)'%s>&nbsp;<label for='$for'>%s</label>\n",
					htmlentities($args['var']),
					htmlentities($value),
					$value == $conf_values[$fullvar] ? ' checked ' : '',
					$args['xhtml'] ? ' /' : '',
					htmlentities($label, ENT_COMPAT, "UTF-8")
				);
			}
			break;

		case 'textarea': 
			$attr = varinput_build_attr($conf_layout[$var]['options']);
			$rows = $attr['rows'] ? $attr['rows'] : 3;
			$cols = $attr['cols'] ? $attr['cols'] : 40;
			$wrap = $attr['wrap'] ? $attr['wrap'] : 'virtual';
			$class = $attr['class'] ? $attr['class'] : 'field';
//			unset($attr['size'], $attr['class']);
			$output = sprintf("<textarea name=\"opts[%s]\" cols=\"%s\" rows=\"%s\" wrap=\"%s\" class=\"%s\" onblur='web.conf_change(this)'>%s</textarea>", 
				htmlentities($args['var']), 
				$cols,
				$rows,
				$wrap,
				$class,
				htmlentities($conf_values[$fullvar], ENT_COMPAT, "UTF-8")
			);
			break;

		case 'text':
		default:
			$attr = varinput_build_attr($conf_layout[$var]['options']);
			$size = $attr['size'] ? $attr['size'] : 40;
			$class = $attr['class'] ? $attr['class'] : 'field';
//			unset($attr['size'], $attr['class']);
			$output = sprintf("<input name=\"opts[%s]\" value=\"%s\" type=\"text\" size=\"%s\" class=\"%s\" onchange=\"web.conf_change(this)\"%s>", 
				htmlentities($args['var']), 
				htmlentities($conf_values[$fullvar], ENT_COMPAT, "UTF-8"),
				$size,
				$class,
				$args['xhtml'] ? ' /' : ''
			);
			break;
	};

	return $output;
}

function varinput_build_boolean($opts) {
	global $ps_lang;	// PS3: from class_theme ($ps_lang defined in common.php)
	$ary = array();
	if (trim($opts)) {
		$ary = explode(',', $opts);
	} else {
		$ary = array('Yes:1','No:0');
	}
	$l = array();
	foreach ($ary as $item) {
		list($label, $val) = explode(':', $item);
		if (!$val) {
			$x = strtolower($label);
			$val = ($x == 'yes' or $x == 'true' or $x == '1') ? 1 : 0;
		}
		$l[$ps_lang->trans($label)] = $val;		// this has to be manually added to languages/.../global.lng
	}
	return $l;
}

function varinput_build_select($opts) {
	global $ps_lang;	// PS3: from class_theme ($ps_lang defined in common.php)
	$ary = array();
	$opts = trim($opts);
	if ($opts) {
//		$ary = explode(',', $opts);
		$ary = preg_split('/[,\r?\n]+/', $opts, -1, PREG_SPLIT_NO_EMPTY);
	} else {
		$ary = array();
	}
	$l = array();
	foreach ($ary as $item) {
		list($label, $val) = explode(':', $item);
		$label = trim($label);
		$val = trim($val);
		if ($val == '') {
			$val = $label;
		}
//		$l[$ps_lang->trans($label)] = $val;		// this has to be manually added to languages/.../global.lng
		$l[$val] = $ps_lang->trans($label);
	}
	return $l;
}

function varinput_build_attr($opts) {
	$ary = array();
	if (trim($opts)) {
		$ary = explode(',', $opts);
	} else {
		$ary = array();
	}
	$l = array();
	foreach ($ary as $item) {
		list($var, $val) = explode('=', $item);
		$var = trim($var);
		$val = trim($val);
		if (!$val) {		// ignore attribs that do not have values
			return;
		}
		$l[$var] = $val;
	}
	return $l;
}

?>
