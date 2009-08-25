<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Psychopager {
	protected $opts = array();
	
	public function __construct($opts = array()) {
		$this->initialize($opts);
	}

	public function initialize($opts = array()) {
		// set new opts + current opts + defaults
		$this->opts = $opts + $this->opts + array(
			'base_url'		=> null,
			'per_page'		=> 100,
			'per_group'		=> 3,
			'total'			=> 0,
			'start'			=> 0,
			'start_var'		=> 'start',
			'start_regex'		=> '',
			'force_prev_next'	=> false,
			'urltail'		=> '',
			'prefix'		=> '',
			'next'			=> 'Next',
			'prev'			=> 'Previous',
			'first'			=> 'First',
			'last'			=> 'Last',
			'separator'		=> ', ',
			'middle_separator'	=> ' ... ',
		);
		if (empty($this->opts['start_regex'])) {
			$this->opts['start_regex'] = '|/?' . $this->opts['start_var'] . '/\d+|';
		}
		// remove trailing slash if its present.
		if (substr($this->opts['base_url'], -1) == '/') {
			$this->opts['base_url'] = substr($this->opts['base_url'], 0, -1);
		}
		return $this;
	}

	/**
	 * Creates a new pager instance using the options specified. If no
	 * options are specified then the options from the current pager are
	 * used instead.
	 *
	 * @param array $opts	Options to define the new pager instance.
	 */
	public function create($opts = array()) {
		if (!is_array($opts)) {
			$opts = array();
		}
		$orig = $this->opts;
		unset($orig['start_regex']);
		$opts = array_merge($orig, $opts);
		$p = new Psychopager($opts);
		return $p;
	}

	/**
	 * Sets one or more options for the pager.
	 *
	 * @param mixed $opt	Option name to set.
	 * @param mixed $val	Value for the option.
	 */
	public function set_opt($opt, $val = null) {
		if (is_array($opt)) {
			foreach ($opt as $a => $v) {
				$this->set_opt($a, $v);
			}
		} else {
			// only allow arguments that already exist to be set.
			if (array_key_exists($opt, $this->opts)) {
				$this->opts[$opt] = $val;
			}
		}
		return $this;
	}

	/**
	 * Alias for create_links()
	 */
	public function render() {
		return $this->create_links();
	}

	public function create_links() {
		$total = ceil($this->opts['total'] / $this->opts['per_page']);		// total pages needed
		$current = floor($this->opts['start'] / $this->opts['per_page']) + 1;	// current page
		if ($total <= 1) return '';						// nothing to output
		if ($this->opts['per_group'] < 3) $this->opts['per_group'] = 3;		// per_group can not be lower than 3
		if ($this->opts['per_group'] % 2 == 0) $this->opts['per_group']++;	// per_group is EVEN so we add 1 to make it ODD
		$maxlinks = $this->opts['per_group'] * 3 + 1;				// maximum links needed
		$halfrange = floor($this->opts['per_group'] / 2);			// 1/2 way through pages
		$minrange = $current - $halfrange;					// current min/max ranges based on $current page
		$maxrange = $current + $halfrange;
		$out = '';

		$url = '';
		if (!isset($this->opts['base_url'])) {
			$url = current_url();
		} else {
			$url = $this->opts['base_url'];
		}
		if ($this->opts['start_regex']) {
			// remove the start_var based on the start_regex
			$url = preg_replace($this->opts['start_regex'], '', $url);
		}
		
		if ($total > $maxlinks) {
			// create first group of links ...
			$list = array();
			for ($i=1; $i <= $this->opts['per_group']; $i++) {
				if ($i == $current) {
					$list[] = "<span class='pager-current'>$i</span>";
				} else {
					$list[] = sprintf("<a href='%s' class='pager-goto' rel='nofollow'>%d</a>", 
						$url . '/' . $this->opts['start_var'] .	'/' .
						(($i-1)*$this->opts['per_page']) .
						($this->opts['urltail'] ? $this->opts['urltail'] : ''),
						$i
					);
				}
			}
			$out .= implode($this->opts['separator'] . "\n", $list);
	
			// create middle group of links ...
			if ($maxrange > $this->opts['per_group']) {
				$out .= ($minrange > $this->opts['per_group']+1) ? $this->opts['middle_separator'] : $this->opts['separator'];
				$out .= "\n";
				$min = ($minrange > $this->opts['per_group']+1) ? $minrange : $this->opts['per_group'] + 1;
				$max = ($maxrange < $total - $this->opts['per_group']) ? $maxrange : $total - $this->opts['per_group'];
	
				$list = array();
				for ($i=$min; $i <= $max; $i++) {
					if ($i == $current) {
						$list[] = "<span class='pager-current'>$i</span>";
					} else {
						$list[] = sprintf("<a href='%s' class='pager-goto' rel='nofollow'>%d</a>", 
							$url . '/' . $this->opts['start_var'] .	'/' .
							(($i-1)*$this->opts['per_page']) .
							($this->opts['urltail'] ? $this->opts['urltail'] : ''),
							$i
						);
					}
				}
				$out .= implode($this->opts['separator'] . "\n", $list);
				$out .= ($maxrange < $total - $this->opts['per_group']) ? $this->opts['middle_separator'] : $this->opts['separator'];
				$out .= "\n"; // adds proper gap between middle and last group
			} else {
				$out .= $this->opts['middle_separator'] . "\n";
			}
	
			// create last group of links ...
			$list = array();
			for ($i=$total-$this->opts['per_group']+1; $i <= $total; $i++) {
				if ($i == $current) {
					$list[] = "<span class='pager-current'>$i</span>";
				} else {
					$list[] = sprintf("<a href='%s' class='pager-goto' rel='nofollow'>%d</a>", 
						$url . '/' . $this->opts['start_var'] .	'/' .
						(($i-1)*$this->opts['per_page']) .
						($this->opts['urltail'] ? $this->opts['urltail'] : ''),
						$i
					);
				}
			}
			$out .= implode($this->opts['separator'] . "\n", $list);
	
		} else {
			$list = array();
			for ($i=1; $i <= $total; $i++) {
				if ($i == $current) {
					$list[] = "<span class='pager-current'>$i</span>";
				} else {
					$list[] = sprintf("<a href='%s' class='pager-goto' rel='nofollow'>%d</a>", 
						$url . '/' . $this->opts['start_var'] .	'/' .
						(($i-1)*$this->opts['per_page']) .
						($this->opts['urltail'] ? $this->opts['urltail'] : ''),
						$i
					);
				}
			}
			$out .= implode($this->opts['separator'] . "\n", $list);
		}
	
		// create 'Prev/Next' links
		if (($this->opts['force_prev_next'] and $total) or $current > 1) {
			if ($current > 1) {
				$out = sprintf("<a href='%s' class='pager-prev' rel='nofollow'>%s</a>\n", 
					$url . '/' . $this->opts['start_var'] .	'/' .
					(($current-2)*$this->opts['per_page']) .
					($this->opts['urltail'] ? $this->opts['urltail'] : ''),
					$this->opts['prev']
				) . $out;
			} else {
				$out = "<span class='pager-prev'>" . $this->opts['prev'] . "</span>\n" . $out;
			}
		}
		if (($this->opts['force_prev_next'] and $total) or $current < $total) {
			if ($current < $total) {
				$out .= sprintf("\n<a href='%s' class='pager-next' rel='nofollow'>%s</a>\n", 
					$url . '/' . $this->opts['start_var'] .	'/' .
					($current*$this->opts['per_page']) .
					($this->opts['urltail'] ? $this->opts['urltail'] : ''),
					$this->opts['next']
				);
			} else {
				$out .= "\n<span class='pager-next'>" . $this->opts['next'] . "</span>\n";
			}
		}
	
		if ($this->opts['prefix'] != '' and !empty($out)) {
			$out = $this->opts['prefix'] . $out;
		}

		$out = "<span class='pager'>\n$out</span>\n";
		//$out = str_replace("\n","", $out);
		return $out;
	}
} // End of class Psychopager

?>