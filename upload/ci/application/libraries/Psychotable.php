<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 *	Psychostats "Table" library
 *	
 *	$Id$
 *	
 */
class Psychotable {
	// @var array Table column definitions.
	protected $columns 	= array();
	protected $headers 	= array();
	protected $rows 	= array();
	
	// @var array Internal array that holds the tabular data for the table.
	protected $data;
	
	protected $no_data_str = '';

	// @var array The internal template to use for table rendering.
	protected $template;

	// @var string The caption to display above the table.
	// <caption> tags are not widly used.
	protected $caption = '';
	// @var boolean Should the header row be wrapped in a tfoot tag?
	protected $use_thead = true;
	// @var boolean Should the table body be wrapped in a TBODY tag?
	protected $use_tbody = true;
	
	public $names = array('sort'  => 'sort',
			      'order' => 'order',
			      'start' => 'start',
			      'limit' => 'limit');
	public $sort = null;
	public $order = 'asc';
	
	// @var mixed Callback for header rows to allow different sorting.
	public $sort_callback = ''; 
	// @var string String to use when a cell is empty ''.
	public $empty_cell = '&nbsp;';
	// @var string Indention character(s) for table html structure.
	public $indent  = "  ";
	// @var string The newline character(s) to use between table elements.
	public $newline = "\n";
	
	/**
	 * @param array $data Optional data array to populate the table with.
	 */
	public function __construct($data = array()) {
		if (is_array($data) and count($data) > 0) {
			$this->set_data($data);
		}
		$this->default_template();
	}

	/**
	 * Extra 'factory' function to return a new table object. This is useful
	 * for integration into frameworks that load the main object first and
	 * want to create multiple table instances on a single page.
	 * 
	 * @param array $data Optional data array to populate the table with.
	 */
	public function create($data = array()) {
		static $vars = array();
		$t = new Psychotable($data);
		// 'clone' all vars, except this->data
		$t->columns 	= $this->columns;
		$t->headers 	= $this->headers;
		$t->rows	= $this->rows;
		$t->template	= $this->template;
		$t->caption	= $this->caption;
		$t->use_thead	= $this->use_thead;
		$t->use_tbody	= $this->use_tbody;
		$t->names	= $this->names;
		$t->sort 	= $this->sort;
		$t->order	= $this->order;
		$t->sort_callback = $this->sort_callback;
		$t->empty_cell	= $this->empty_cell;
		$t->indent	= $this->indent;
		$t->newline	= $this->newline;
		return $t;
	}

	/**
	 * Define a new column. Alias for column_last().
	 * 
	 * @see column_last()
	 * @param string $name The name of the column.
	 * @param string $label The label to use in the header row.
	 * @param string $modifiers Optional modifiers/callbacks to perform on the cell data.
	 * @param 
	 */
	public function column($name, $label = null, $modifiers = null, $attr = null) {
		if (is_array($name)) {
			// allow a quick list of columns to be added.
			// this does not allow labels/mods/attrs to be assigned.
			foreach ($name as $n) {
				$this->column_last($n);
			}
			return $this;
		} else {
			return $this->column_last($name, $label, $modifiers, $attr);
		}
	}

	/**
	 * Define a new column. The column is added to the end of the list. If
	 * the column $name already exists it will be over written.
	 * 
	 * @param string $name The name of the column.
	 * @param string $label The label to use in the header row.
	 * @param string $modifiers Optional modifiers/callbacks to perform on the cell data.
	 */
	public function column_last($name, $label = null, $modifiers = null, $attr = null) {
		if (array_key_exists($name, $this->columns)) {
			$this->column_remove($name);
		}
		$col = $this->_new_column($name, $label, $modifiers, $attr, 'header');
		$this->columns += array($name => $col);
		
		if ($name == '+') {
			// force no sort for special increment column
			$this->header_attr('+', array('nosort' => true));
		}
		return $this;
	}

	/**
	 * Define a new column. The column is added to the beginning of the list.
	 * 
	 * @param string $name The name of the column.
	 * @param string $label The label to use in the header row.
	 * @param string $modifiers Optional modifiers/callbacks to perform on the cell data.
	 */
	public function column_first($name, $label = null, $modifiers = null, $attr = null) {
		if (array_key_exists($name, $this->columns)) {
			$this->column_remove($name);
		}
		$col = $this->_new_column($name, $label, $modifiers, $attr, 'header');
		$this->columns = array($name => $col) + $this->columns;

		if ($name == '+') {
			// force no sort for special increment column
			$this->header_attr('+', array('nosort' => true));
		}
		return $this;
	}

	/**
	 * Define a new column. The column is added before the named column.
	 * 
	 * @param string $before The existing column to add the new column before.
	 * @param string $name The name of the column.
	 * @param string $label The label to use in the header row.
	 * @param string $modifiers Optional modifiers/callbacks to perform on the cell data.
	 */
	public function column_before($before, $name, $label = null, $modifiers = null, $attr = null) {
		$idx = $this->_column_index($before);
		if (!$idx) { // false or zero
			$this->column_first($name, $label, $modifiers, $attr);
		} else {
			$col = $this->_new_column($name, $label, $modifiers, $attr, 'header');
			$new = array();
			foreach ($this->columns as $cur_name => $cur_col) {
				if ($cur_name == $before) {
					// insert column here
					$new[$name] = $col;
				}
				$new[$cur_name] = $cur_col;
			}
			$this->columns = $new;
		}

		if ($name == '+') {
			// force no sort for special increment column
			$this->header_attr('+', array('nosort' => true));
		}
		return $this;
	}

	/**
	 * Define a new column. The column is added after the named column.
	 * 
	 * @param string $after The existing column to add the new column after.
	 * @param string $name The name of the column.
	 * @param string $label The label to use in the header row.
	 * @param string $modifiers Optional modifiers/callbacks to perform on the cell data.
	 */
	public function column_after($after, $name, $label = null, $modifiers = null, $attr = null) {
		$idx = $this->_column_index($after);
		if ($idx === false or $idx >= count($this->columns)) {
			$this->column_last($name, $label, $modifiers, $attr);
		} else {
			$col = $this->_new_column($name, $label, $modifiers, $attr, 'header');
			$new = array();
			foreach ($this->columns as $cur_name => $cur_col) {
				$new[$cur_name] = $cur_col;
				if ($cur_name == $after) {
					// insert column here
					$new[$name] = $col;
				}
			}
			$this->columns = $new;
		}

		if ($name == '+') {
			// force no sort for special increment column
			$this->header_attr('+', array('nosort' => true));
		}
		return $this;
	}

	/**
	 * Removes the named column. Multiple names can be passed in.
	 * 
	 * @param mixed $name Name of the column(s) to remove.
	 */
	public function column_remove($name) {
		$names = array();
		$list = func_get_args();
		// flatten the list of arguments
		foreach ($list as $name) {
			if (is_array($name)) {
				$names = array_merge($list, $name);
			} else {
				$names[] = $name;
			}
		}
		foreach ($names as $name) {
			if (isset($this->columns[$name])) {
				unset($this->columns[$name]);
			}
		}
		return $this;
	}

	/**
	 * Returns the index of the named column. Returns false if the column
	 * does not exist.
	 * 
	 * @param string $name The name of the column.
	 * @access protected
	 */
	protected function _column_index($match) {
		if (!$this->columns) {
			return false;
		}

		$idx = 0;
		foreach ($this->columns as $name => $col) {
			if ($name == $match) {
				return $idx;
			}
			++$idx;
		}
		return false;
	}

	public function _new_column($name, $label = null, $modifiers = null, $attr = null, $which = 'header') {
		$defaults = array(
			'label'	=> isset($label) ? $label : $name,
			'mods'	=> $modifiers,
			'attr'	=> (isset($attr) and is_array($attr)) ? array( $which => $attr ) : null,
		);

		$col = array();
		if (is_array($label)) {
			// $label is actually a configuration array
			$col = $label;
		}
		$col = array_merge($defaults, $col);

		return $col;
	}

	/**
	 * Set one or more attributes on a header column.
	 *
	 * @param string $name		Name of the column.
	 * @param mixed	 $attr		Attribute name or array.
	 * @param string $value		If $attr is a string then this is the value.
	 */
	public function column_attr($name, $attr, $value = null, $which = 'header') {
		if (isset($this->columns[$name])) {
			if (is_array($attr)) {
				foreach ($attr as $key => $value) {
					$this->column_attr($name, $key, $value, $which);
				}
			} else {
				$this->columns[$name]['attr'][$which][$attr] = $value;
			}
		}
		return $this;
	}

	/**
	 * Shortcut for data column attributes.
	 * 
	 */
	public function data_attr($name, $attr, $value = null) {
		$this->column_attr($name, $attr, $value, 'data');
		return $this;
	}

	/**
	 * Shortcut for header column attributes.
	 * 
	 */
	public function header_attr($name, $attr, $value = null) {
		$this->column_attr($name, $attr, $value, 'header');
		return $this;
	}

	/**
	 * Render (draw) the completed table with all available data.
	 * 
	 * @param array $data Optional data array to populate the table with. 
	 */
	public function render($data = null) {
		// set the data if its passed in...
		if (is_null($data) and !is_null($this->data)) {
			$data =& $this->data;
		}

		$out = '';

		// open table ...		
		$out .= $this->template_part('table_open');

		// output caption, if set
		if ($this->caption !== '') {
			// caption_open {content} caption_close
			$out .= $this->template_part('caption', 1, $this->caption);
		}
		
		// add <thead> if enabled
		if ($this->use_thead) {
			$out .= $this->template_part('thead_open', 1);
		}

		// auto generate basic columns based on the data keys.
		if (!$this->columns and count($data)) {
			$labels = reset($data);
			$first = reset($labels);
			$key = key($labels);
			if (is_numeric($key)) {
				// remove the first row of data since the
				// keys seem to be numerical.
				array_shift($data);
			}

			foreach ($labels as $key => $val) {
				// skip keys with a leading underscore
				if (substr($key,0,1) == '_') {
					continue;
				}
				// uppercase words, and replace underscores with spaces
				$label = ucwords(str_replace('_', ' ', is_numeric($key) ? $val : $key));
				$this->column($key, $label);
			}
		}
		
		$has_callback = is_callable($this->sort_callback);
		$tr = new Psychotable_Tag($this->template['header_row_tag']);

		// output header columns
		$row = '';
		foreach ($this->columns as $name => $col) {
			// ignore columns that have a false label.
			// Useful for previous columns that use colspan.
			if ($col['label'] === false) {
				continue;
			}

			// are there any attributes set for this header column?
			$attr = isset($col['attr']['header']) ? $col['attr']['header'] : array();

			// determine which template part to use based on sort.
			$part = ($name == $this->sort)
				? 'header_cell_sorted_tag' : 'header_cell_tag';

			// create header tag
			$th = new Psychotable_Tag($this->template[$part], $attr);

			// set content for cell, use callback if available
			if ($has_callback) {
				$th->set_content(call_user_func_array(
					$this->sort_callback,
					array( $name, $col, $th, $this )
				));
			} else {
				$th->set_content($col['label']);
			}

			$row .= $this->indent(3) . $th . $this->newline;
		}
		$out .= $this->indent(2) .
			$tr->render($this->newline . $row . $this->indent(2)) .
			$this->newline;

		// close <thead> if enabled
		if ($this->use_thead) {
			$out .= $this->template_part('thead_close', 1);
		}

		// open <tbody> if enabled
		if ($this->use_tbody) {
			$out .= $this->template_part('tbody_open', 1);
		}

		// render the data rows
		if ($data) {
			$rows = '';
			$i = 0;
			foreach ($data as $record) {
				$even = (++$i % 2 == 0);
				$tr = new Psychotable_Tag($this->template[$even ? 'data_row_even_tag' : 'data_row_tag']);
				$row = '';
				foreach ($this->columns as $name => $col) {
					$part = sprintf('data_cell%s%s_tag',
						$name == $this->sort ? '_sorted' : '',
						$even ? '_even' : ''
					);
					$attr = isset($col['attr']['data']) ? $col['attr']['data'] : null;
					$td = new Psychotable_Tag($this->template[$part], $attr);

					if ($name == '+') {
						// auto-increment column
						$content = $i + $this->columns[$name]['mods'];
					} else {
						$value = isset($record[$name]) ? $record[$name] : null;
						
						// callback prototype:
						// func(name, value, record, TD, TABLE)
						$content = $this->modify(
							$name, $value, $record, $td
						);
					}

					if (!isset($content)) {
						$content = $this->empty_cell;
					}
					$td->set_content($content);
					
					$row .= $this->indent(3) . $td . $this->newline;
				}
				$rows .= $this->indent(2) .
					$tr->render($this->newline . $row . $this->indent(2)) .
					$this->newline;
			}
			$out .= $rows;
		} elseif (!empty($this->no_data_str)) {
			$tr = new Psychotable_Tag($this->template['data_row_tag']);
			$td = new Psychotable_Tag($this->template['data_cell_empty_tag'],
						  array( 'colspan' => count($this->columns) ));
			$out .= $this->indent(2) .
				$tr->render($td->render($this->no_data_str)) .
				$this->newline;
		}
		
		if ($this->use_tbody) {
			$out .= $this->template_part('tbody_close', 1);
		}
		$out .= $this->template_part('table_close');

		//echo $out; // debugging
		return $out;
	}

	/**
	 * Modifies the data given based on the modifiers configured for the
	 * named column.
	 *
	 * @param string $name		Column name to run modifiers from.
	 * @param string $data		Value to modify.
	 * @param mixed  ...		Extra parameters to pass to user callbacks.
	 *
	 */
	public function modify($name, $data = '') {
		$out = $data;
		if (!isset($this->columns[$name])) {
			return $out;
		}
		if (is_array($this->columns[$name]['mods'])) {
			// 'mods' is a single callback array (object callback)
			$funcs = array( $this->columns[$name]['mods'] );
		} else {
			$funcs = array_map('trim', explode('|', $this->columns[$name]['mods']));
		}
		if ($funcs) {
			foreach ($funcs as $func) {
				$user_func = false;
				if (is_array($func)) {
					// array (obj, method)
					$user_func = true;
				} elseif (substr($func,0,3) == 'cb:') {
					// string 'cb:name'
					$func = substr($func, 3);
					$user_func = true;
				}
				
				if (is_callable($func)) {
					if ($user_func) {
						$list = func_num_args() > 2 ? array_slice(func_get_args(), 2) : array();
						$args = array_merge(array( $name, $data ), $list, array( $this ));
					} else {
						$args = array( $data );
					}
					$out = call_user_func_array($func, $args);
				} elseif (is_string($func) and !empty($func)) {
					$out = sprintf($func, $data);
				} else {
					// trigger error? ... just silently ignore
				}
			}
		}
		return $out;
	}

	public function template_part($part, $indent = 0, $content = '') {
		$part_close = '';
		if (isset($this->template[$part . '_open'])) {
			// is the part actually an 'open' tag.
			$part_close = $part . '_close';
			$part .= '_open';
		} elseif (!isset($this->template[$part])) {
			// The template part does not exist.
			return '';
		}
		
		$out = $this->indent($indent) . $this->template[$part] . $content;
		if ($part_close) {
			$out .= $this->template[$part_close];
		}
		$out .= $this->newline;

		return $out;
	}

	/**
	 * Returns an indent string based on the depth given.
	 *
	 * @param integer $depth How many levels deep to indent.
	 */
	public function indent($depth = 1) {
		return str_repeat($this->indent, $depth);
	}

	/**
	 * Set the table <caption> tag.
	 * 
	 * @param string $caption The caption string.
	 */
	public function set_caption($caption) {
		$this->caption = $caption;
		return $this;
	}

	/**
	 * Set class used for even/odd rows
	 *
	 * @param string $which 'even' or 'odd'
	 * @param string $class The name of the class to use.
	 */
	public function set_row_class($which, $class) {
		if (in_array($which, array('odd', 'even', 'header'))) {
			$var = $which . '_class';
			$this->$var = $class;
		}
		return $this;
	}

	/**
	 * Set the data array for the table.
	 * 
	 * @param array $data Keyed array of tabular data to display.
	 */
	public function set_data($data) {
		$this->data = $data;		
		return $this;
	}

	/**
	 * Set the string to output if no data is available.
	 * 
	 * @param string $str String to display.
	 */
	public function set_no_data($str) {
		$this->no_data_str = $str;		
		return $this;
	}

	/**
	 * Set the current sort for the table.
	 * 
	 * @param string $sort Name of the column that matches the sort.
	 * @param string $order 'asc' or 'desc' specifing the sort order.
	 */
	public function set_sort($sort, $order = 'asc', $callback = null) {
		$this->sort = $sort;
		$this->order = $order;
		$this->sort_callback = $callback;
		return $this;
	}

	/**
	 * Set the names used for sorting
	 * 
	 * @param string $sort Name of the column that matches the sort.
	 * @param string $order 'asc' or 'desc' specifing the sort order.
	 */
	public function set_sort_names($key = array(), $val = null) {
		if (is_array($key)) {
			foreach ($key as $k => $v) {
				$this->set_sort_names($k, $v);
			}
			return $this;
		}
		$this->names[$key] = $val;
		return $this;
	}
	
	/**
	 * Enable/Disable the use of the <thead> tag for the header row.
	 * 
	 * @param boolean $yesno True/true to enable the use of a <thead> tag
	 */
	public function set_thead($yesno) {
		$this->use_thead = $yesno;
		return $this;
	}

	/**
	 * Enable/Disable the use of the <tbody> tag for all data rows.
	 * 
	 * @param boolean $yesno True/true to enable the use of a <tbody> tag
	 */
	public function set_tbody($yesno) {
		$this->use_tbody = $yesno;
		return $this;
	}
	
	/**
	 * Set the newline character(s). By default "\n" is used.
	 * 
	 * @param string $newline The newline char(s)
	 */
	public function set_newline($newline) {
		$this->newline = $newline;
		return $this;
	}

	/**
	 * Set the indent character(s). By default "  " is used.
	 * 
	 * @param string $indent The indent char(s)
	 */
	public function set_indent($indent) {
		$this->indent = $indent;
		return $this;
	}

	/**
	 * Initializes the table template for output.
	 * 
	 * @param array $template Array of template parts to change. Any parts
	 * not defined will be set to the default.
	 */
	public function set_template($template = null, $value = null) {
		// make sure we have defaults set first...
		if (empty($this->template)) {
			$this->default_template();
		}

		// if the $template var is an array we have a set of template
		// parts defined. Otherwise a single part => $value was given.
		if (is_array($template)) {
			foreach ($template as $key => $value) {
				$this->template[$key] = $value;
			}
			
		} elseif (!empty($template) and $value !== null) {
			// key => value pair
			$this->template[$template] = $value;
		}
		return $this;
	}

	/**
	 * Sets the current template back to the default.
	 */
	public function default_template() {
		$this->template = array(
			// open/close tags are used AS-IS and are not
			// filtered or processed in any way.
			'table_open'			=> '<table class="neat">',
			'table_close'			=> '</table>',
							
			'thead_open'			=> '<thead>',
			'thead_close'			=> '</thead>',
							
			'tfoot_open'			=> '<tfoot>',
			'tfoot_close'			=> '</tfoot>',
							
			'tbody_open'			=> '<tbody>',
			'tbody_close'			=> '</tbody>',
							
			'caption_open'			=> '<caption>',
			'caption_close'			=> '</caption>',

			// The tag definitions below only define the opening tag
			// (do not add the closing tag, like </tr>). The tags
			// tr,td,th are the only tags allowed (since nothing
			// else makes sense for tables). You can add any
			// attributes to the tags. Do not add any extra tags.
			// You will have to use callbacks or wrap your data with
			// the extra tags as needed.

			// header and data rows. <tr> is the only acceptable
			// tag allowed, but add any attributes you want to be
			// added to every row.
			'header_row_tag'		=> '<tr class="hdr">',
			'data_row_tag'			=> '<tr>',
			'data_row_even_tag'		=> '<tr class="even">',

			// head and data cells. <td> and <th> are the only
			// acceptable tags allowed, but add any attributes you
			// want to be added to every cell.
			'header_cell_tag'		=> '<th>',
			'header_cell_sorted_tag'	=> '<th class="sorted">',
			'data_cell_tag'			=> '<td>',
			'data_cell_empty_tag'		=> '<td class="nodata">',
			'data_cell_even_tag'		=> '<td>',
			'data_cell_sorted_tag'		=> '<td>',
			'data_cell_sorted_even_tag' 	=> '<td>',
		);
		return $this;
	}

	//public function __call($name, $args) {
	//	// catch all unknown calls and report a warning not a fatal error 
	//	//return $this;
	//	$caller = debug_backtrace();
	//	trigger_error('Call to undefined method '
	//		. get_class()
	//		. '::' . $name
	//		. '() in '
	//		. $caller[1]['file']
	//		. ' on line '
	//		. $caller[1]['line'],
	//		E_USER_ERROR
	//	);
	//	return $this;
	//}
} // end of Psychotable class

class Psychotable_Tag {
	protected $tag;
	protected $close;
	protected $content = '';
	protected $attrs = array();
	protected $valid_attrs = array('id', 'class', 'style', 'colspan', 'rowspan');

	/**
	 * @param string $str The name or html of the tag to create.
	 * @param array $attr Optional attribute array to initialize the tag.
	 * @param boolean $close If true the tag has a matching closing tag.
	 */
	public function __construct($str, $attrs = null, $close = true) {
		$tag = $this->tokenize_tag($str);
		if ($tag) {
			$this->tag = $tag['tag'];
			//echo "STR=$str\n"; print_r($tag);
			if ($tag['attributes']) {
				$this->set_attr($tag['attributes']);
			}
		} else {
			throw new Exception("Invalid tag syntax: $str");
		}
		$this->close = $close ? true : false;
		
		if (is_array($attrs)) {
			$this->set_attr($attrs);
		}
	}
	
	/**
	 * Tokenizes an html tag. This function is not perfect and will break
	 * on more complex attributes, like those that contain javascript or
	 * extra quotes. Only the opening tag should be passed in. 
	 *
	 * @param string $tag Tag name 'td', or html '<td ...>'
	 * @return mixed Returns an array for the tag and attributes, or false on failure.
	 */
	public function tokenize_tag($tag) {
		// this regex will match all the attributes within a single tag.
		// no regex is perfect when it comes to parsing html, but this
		// will work for my purposes.
		// index[1] = tag name
		// index[2] = attribute string
		static $regex = "/<\/?(\w+)((?:\s+(?:\w|\w[\w-]*\w)(?:\s*=\s*(?:\".*?\"|'.*?'|[^'\">\s]+))?)+\s*|\s*)\/?>/";

		$token = array( 'tag' => '', 'attributes' => array() );
		if (preg_match($regex, $tag, $match)) {
			$token['tag'] = $match[1];
			if (isset($match[2])) {
				// tokenize the attributes, but watch out for
				// quoted strings.
				$attr = strtok(trim($match[2]), " \t=");
				$key = '';
				$list = array();
				while ($attr !== false) {
					// the first attribute won't have an '='
					// on the end of it. The rest will.
					if (count($list) == 0 or substr($attr, -1) == '=') {
						if (substr($attr, -1) == '=') {
							$attr = substr($attr, 0, -1);
						}
						$key = trim($attr);
						$list[$key] = '';
					} elseif ($key !== null) {
						$list[$key] = $attr;
						$key = null;
					}
					//$list[] = $attr;

					// don't tokenize on white-space, this
					// way quoted strings will remain intact
					$attr = strtok("\"\'");
				}
				$token['attributes'] = $list;
			}
		} elseif (preg_match('/^[a-z]+$/', $tag)) {
			$token['tag'] = $tag;
		}
		
		return $token ? $token : false;
	}
	
	/**
	 * Returns the tag fully rendered.
	 * @param string $content Optional string of content to add to output.
	 * @see set_content()
	 */
	public function render($content = null) {
		$out = '<' . $this->tag;
		if ($this->attrs) {
			foreach ($this->attrs as $attr => $value) {
				if ($value != '') {
					$out .= sprintf(' %s="%s"', $attr, $value);
				}
			}
		}
		$out .= '>';
		$out .= is_null($content) ? $this->content : $content;
		if ($this->close) {
			$out .= '</' . $this->tag . '>';
		}
		return $out;
	}
	
	public function set_content($content) {
		$this->content = $content;
		return $this;
	}
	
	public function set_attr($attr, $value = null, $append = false) {
		if (!$attr) {
			return $this;
		}
		if (is_array($attr)) {
			foreach ($attr as $key => $value) {
				$this->set_attr($key, $value, $append);
			}
		} else {
			if (in_array($attr, $this->valid_attrs)) {
				if (is_null($value)) {
					// allow NULL values to reset the attribute
					unset($this->attrs[$attr]);
				} elseif ($value !== '') {
					if ($append) {
						$this->attrs[$attr] .= ' ' . $value;
					} else {
						$this->attrs[$attr] = $value;
					}
				}
			} else {
				//throw new Exception('Invalid tag attribute (' .  $attr . ')');
				// silently ignore ...
			}
		}
		return $this;
	}
	
	public function tag_name() {
		return $this->tag;
	}

	public function __get($name) {
		return $this->$name;
	}
	
	public function __toString() {
		return $this->render();
	}
} // end of class Psychotable_Tag

?>