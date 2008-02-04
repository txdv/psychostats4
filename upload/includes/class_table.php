<?php
/*
	HTML Table class by Jason Morriss / 2007-04-18
	$Id$

	This table class is for dynamic rendering of HTML tables in PsychoStats.
	Use of this class was not originally intended outside of PsychoStats but
	it would be possible for it to work. However, Some of the defaults and
	methods are geared towards PsychoStats.

	PsychoTable is a lot more complex then a table class really needs to be
	but the functionality is built so that Psychostats plugins can override
	needed features or add more.

	PHP4 compatible.	
*/

if (defined("CLASS_PSYCHOTABLE_PHP")) return 1; 
define("CLASS_PSYCHOTABLE_PHP", 1); 

class PsychoTable extends PsychoTableCommon {
	var $data = array();	// array of data to display
	var $stripe = true;	// automatically stripe rows?
	var $stripe_row = 0;	// 1=odd, 0=even rows
	var $sortable = true;
	var $columns = array();
	var $column_attr = array();
	var $header_attr = array();
	var $header_format 	= '<p><span>%s</span></p>';
	var $header_sort_format = '<p><a href="%2$s"><span class="%3$s">%1$s</span></a></p>';	// PS3.1 theme specific
	var $sort_baseurl = array();
	var $sort_active_class = 'active';
	var $if_no_data = '';
	var $type = 'html';

	var $start = 0;
	var $sort = '';
	var $order = '';

function PsychoTable(&$data) {
	$this->data($data);
}

// render the table. $type can be 'html', 'csv', 'tab'.
// only 'html' is implemented at this time.
function render($type = 'html') {
	$this->type = $type;
	$table = $this->start_table();
	$table .= $this->sortable() ? $this->header_sort() : $this->header();
	$table .= $this->rows();
	$table .= $this->end_table();
	return $table;
}

// returns a simple table header row (no sorting)
function header() {
	$row = new PsychoRow($this->type);
//	$row->attr($this->header_attr);	// wrong
	foreach ($this->columns as $key => $cell) {
		$label = is_array($cell) ? $cell['label'] : $cell;
		$str = sprintf($this->header_format, $label);
		$row->th($str, $this->header_attr[$key]);
	}
	return $row->render();
}

// renders the table header row that can be clicked to change the current sort
function header_sort() {
	$row = new PsychoRow($this->type);
//	$row->attr($this->header_attr); // wrong
	foreach ($this->columns as $key => $cell) {
		$hdr_attr = $this->header_attr[$key];
		$label = is_array($cell) ? $cell['label'] : $cell;
		if ($cell['tooltip']) {
			$label = sprintf("<acronym title='%s'>%s</acronym>", $cell['tooltip'], $label);
		}
		if ($key and !$cell['nosort']) {
			$url = array_merge($this->sort_baseurl, array( 
				$this->prefix . 'sort' => $key, 
				$this->prefix . 'order' => $this->order() 
			));
			if ($key == $this->sort()) {	// alternate the order
				$url[$this->prefix . 'order'] = $this->order() != 'asc' ? 'asc' : 'desc';
				$hdr_attr['class'] = trim($hdr_attr['class'] . ' ' . $this->sort_active_class);
			}
			$str = sprintf($this->header_sort_format, $label, ps_url_wrapper($url), $this->order());
		} else {
			$str = sprintf($this->header_format, $label);
		}
		$row->th($str, $hdr_attr);
//		$row->th($str);
	}
	return $row->render();
}

// renders all the data rows of the table
function rows() {
	$rows = '';
	$i = 0;
	$stripe_attr = array( 'class' => $this->stripe_row == 0 ? 'even' : 'odd' );
	foreach ($this->data as $data) {
		$row = new PsychoRow();
		if (++$i % 2 == $this->stripe_row) $row->attr($stripe_attr);
		foreach ($this->columns as $key => $cell) {
			if ($key != '+') {
				$html = $data[$key];

				// is there a modifier?
				if ($cell['modifier']) {
					$html = $this->callback($html, $cell['modifier']);
				}
				// is there a callback?
				if ($cell['callback']) {
					$html = $this->callback($html, $cell['callback'], $data);
				}

				$row->td($html, $this->column_attr[$key]);
			} elseif ($key == '+') {		// special auto-increment row
				$row->td($i + $this->start);
			} else {

			}
		}
		$rows .= $row->render();
	}

	// if no rows were output display the 'not found' message instead
	if ($i == 0 and !empty($this->if_no_data)) {
		$row = new PsychoRow();
		$row->td($this->if_no_data, array( 'class' => 'no-data', 'colspan' => count($this->columns) ));
		$rows .= $row->render();
	}

	return $rows;
}

function callback($html, $callback, $params = array()) {
	$ret = false;
	if (function_exists($callback) or is_array($callback)) {
		$ret = call_user_func_array($callback, $params ? array($html, $params) : $html);
	} elseif (strpos($callback, '%') !== false) {
		$ret = sprintf($callback, $html);
	}
	if ($ret !== false) $html = $ret;
/*
	list($obj, $func) = is_array($callback) ? $callback : array(null, $callback);
	if ($obj and is_object($obj) and method_exists($obj, $func)) {
		$html = $obj->$func($html);
	} elseif (function_exists($func)) {
		$html = $func($html);
	}
*/
	return $html;
}

// returns the table head
function start_table() {
	return $this->with_attr("<table%s>\n");
}

// returns the table tail
function end_table() {
	return "</table>\n";
}

function data(&$data, $merge = false) {
	if (!is_array($data)) return;	// do nothing if it's not an array
	if (!$merge) {
		$this->data = $data;
	} else {
		$this->data = array_merge($this->data, $data);
	}
}

// sets up custom columns. each 'key' in the columns array maps to a key in the data array.
// the value of each key is the label to display in the header.
function columns($columns) {
	foreach ($columns as $key => $col) {
		$this->columns[$key] = is_array($col) ? $col : array( 'label' => $col );
	}
}

// inserts a new column before or after the key specified 
function insert_columns($col, $key = null, $after = false) {
	if ($key !== null and array_key_exists($key, $this->columns)) {
		$newcols = array();
		$curcols = array_values($this->columns);
		$cols = array_keys($this->columns);
		$c = array_pop(array_keys($cols, $key));	// which column index matches the $key 
		if ($after) $c++;
		for ($i=0; $i < $c; $i++) {			// keep preceeding columns
			$newcols[ $cols[$i] ] = $curcols[$i];
		}
		$newcols = array_merge($newcols, $col);		// insert new columns
		while ($i < count($curcols)) {			// add remaining columns
			$newcols[ $cols[$i] ] = $curcols[$i];
			$i++;
		}
		$this->columns = $newcols;
#		print_r($this->columns);
	} else {
		// add new columns to the end
		$this->columns = array_merge($this->columns, $col);
	}
}

function remove_columns($key) {
	foreach ((array)$key as $k) {
		if (array_key_exists($k, $this->columns)) {
			unset($this->columns[$k]);
			unset($this->column_attr[$k]);
		}
	}
}

// sets up the table columns based on the keys in the first data row
function auto_columns() {
	$this->columns = array();
	// ... TODO ...
}

// sets an option on a column definition
function column_opt($col, $key, $value) {
	if (!is_array($this->columns[$col])) {
		$this->columns[$col] = array( 'label' => $this->columns[$col] );
	}
	$this->columns[$col][$key] = $value;
}

// sets attribute(s) on a single keyed column
function column_attr($col, $attr, $value = null) {
	if (!is_array($this->column_attr[$col])) $this->column_attr[$col] = array( );
	if (is_array($attr)) {
		$this->column_attr[$col] = $value === null ? array_merge($this->column_attr[$col], $attr) : $attr;
	} else {
		$this->column_attr[$col][$attr] = $value;
	}
}

// sets attributes on header cells.
function header_attr($col, $attr, $value = null) {
	if (!is_array($this->header_attr[$col])) $this->header_attr[$col] = array( );
	if (is_array($attr)) {
		$this->header_attr[$col] = $value === null ? array_merge($this->header_attr[$col], $attr) : $attr;
	} else {
		$this->header_attr[$col][$attr] = $value;
	}
}

function column_callback($col, $func) {
	$this->column_opt($col, 'callback', $func);
}

function column_modifier($col, $func) {
	$this->column_opt($col, 'modifier', $func);
}

function header_format($fmt = null) {
	if ($fmt === null) return $this->header_format;
	$this->header_format = $fmt;
}

function header_sort_format($fmt = null) {
	if ($fmt === null) return $this->header_sort_format;
	$this->header_sort_format = $fmt;
}

function sort_baseurl($url = null) {
	if ($url === null) return $this->sort_baseurl;
	$this->sort_baseurl = is_array($url) ? $url : array( '_base' => $url );
}

function start_and_sort($start, $sort, $order = null, $prefix = '') {
	$this->start = $start;
	$this->sort = $sort;
	if ($order !== null) $this->order = $order;
	$this->prefix = $prefix;
}

// string prefix for sort and order url paramaters
function prefix($prefix = null) { 
	if ($prefix === null) return $this->prefix;
	$this->prefix = $prefix;
}

function start($start = null) { 
	if ($start === null) return $this->start;
	$this->start = $start;
}

function sort($sort = null) {
	if ($sort === null) return $this->sort;
	$this->sort = $sort;
}

function sortable($sort = null) {
	if ($sort === null) return $this->sortable;
	$this->sortable = $sort;
}

function order($order = null) { 
	if ($order === null) return $this->order;
	$this->order = $order;
}

function if_no_data($msg = null) {
	if ($msg === null) return $this->if_no_data;
	$this->if_no_data = $msg;
}

} // end of PsychoTable

class PsychoRow extends PsychoTableCommon {
	var $cells = array();

function PsychoRow($type = 'html') {
	$this->type = $type;
}

function render($type = 'html') {
	$this->type = $type;
	$tr = $this->with_attr("<tr%s>\n");
	foreach ($this->cells as $col) {
		$tr .= $this->with_attr("\t<" . $col['type'] . "%s>", $col['attr'], true);
		$tr .= sprintf("%s</%s>\n",
			$col['html'],
			$col['type']
		);
	}
	$tr .= "</tr>\n";
	return $tr;
}

function cell($html, $attr = null, $type='td') {
	$this->cells[] = array(
		'html'	=> $html,
		'attr'	=> $attr,
		'type'	=> $type,
	);
}

// ->td
function td($html, $attr = null) {
	return $this->cell($html, $attr, 'td');
}

// ->th
function th($html, $attr = null) {
	return $this->cell($html, $attr, 'th');
}

} // end of PsychoRow

// common methods for all table elements
class PsychoTableCommon {
	var $attr = array();

// sets an attribute on the element if a value is given
// returns the current attribute if no value is given
function attr($var, $value = null, $_attr = null) {
	if ($_attr === null) {
		$attr =& $this->attr;
	} else {
		$attr =& $_attr;
	}

	if (is_array($var)) {
		$attr = $var;
	} else {
		if ($value !== null) {
			$attr[$var] = $value;
		} else {
			return array_key_exists($var, $attr) ? $attr[$var] : null;
		}
	}
	return $attr;
}

// returns a string formatted with the elements current attributes
function with_attr($fmt, $attr = null, $force_attr = false) {
	if ($attr == null and !$force_attr) $attr = $this->attr;
	$str = '';
	if ($attr) {
		foreach ($attr as $key => $val) {
			$q = strpos($val, '\'') !== false ? '"' : '\'';
			$str .= " $key=" . $q . $val . $q;
		}
	}
	return sprintf($fmt, $str);
}

} // end of PsychoTableCommon

?>
