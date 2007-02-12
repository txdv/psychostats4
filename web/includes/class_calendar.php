<?php
/*
	Calendar class by Jason Morriss / 2006-03-17

	For timing, these routines are based on epoch timestamps.
	For most coloring/formatting/styling CSS classes are used.

	The javascript used is assumed to have been included elsewhere in the HTML.
	I use some routines from my 'webcore.js' javascript class.

*/

if (defined("CLASS_CALENDAR_PHP")) return 1; 
define("CLASS_CALENDAR_PHP", 1); 

class Calendar {
	var $dotw = array("Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat");	// sun - sat
//	var $dotw = array("Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun");	// mon - sun
	var $conf = array();
	var $time;
	var $year;
	var $month;
	var $day;
	var $data;
	var $table;

// PHP5 constructor
function __construct($year = NULL, $month = NULL, $day = NULL) {
	return $this->Calendar($year, $month, $day);
}

// PHP4 constructor
function Calendar($year = NULL, $month = NULL, $day = NULL) {
	if (!$year and !$month and !$day) {
		$this->time = time();
	} elseif ($year and $month) {
		if (!$day) $day = 1;
		$this->time = mktime(0,0,0,$month,$day,$year); 
	} elseif ($year) {
		if (preg_match('/^\\d\\d\\d\\d-\\d\\d-\\d\\d$/', $year)) {
			$this->time = $this->ymd2time($year);
		} else {
			$this->time = $year;
		}
	}
	list($this->year,$this->month,$this->day) = split('-', date("Y-m-d", $this->time));
	$this->start = mktime(0,0,0,$this->month, 1, $this->year);
	$this->end = mktime(0,0,0,$this->month, $this->daysinmonth($this->year, $this->month), $this->year);

	$this->data = array();
	$this->set_conf(array(
		'id'		=> '',
		'class'		=> 'calendar',
		'width'		=> '250',
		'border'	=> 0,
		'cellspacing'	=> 0,
		'cellpadding'	=> 1,
		'cellwidth'	=> '12%', // (int)(250/8) . '',

		'timevar'	=> 'time',
		'show_timeurl'	=> TRUE,	// TRUE=timeurl is shown; FALSE=timeurl is hidden
		'timeurl'	=> '',
		'timeurl_callback' => null,
	));

	$this->built = FALSE;
//	$this->build();
}

// initialize the basic table properties for the calendar layout
function set_conf($c = array()) {
	if (!is_array($this->conf)) $this->conf = array();
	$this->conf = array_merge($this->conf, $c);
#	print_r($this->conf); print "<br>";
}

// Sets what day is the start of the week (generally monday or sunday)
// $day is 0..6: where 0 is sunday and 6 is saturday
// This must be called before any data is added to the calender or drawn
function startofweek($day) {
	$dotw = array("Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat");
	if (!is_numeric($day) or $day < 0 or $day > 6) return;
	$this->dotw = array_merge(array_splice($dotw, $day), $dotw);
//	$this->dotw = array_merge($this->dotw, $dotw);
}

// builds the arrays of dates that will be used in the calendar output
function build() {
	$this->days = array();
	$this->built = TRUE;

	$this->firstday = date("D", $this->start);
	// determine starting offset for firstday of month
	$this->startoffset = 0;
	while ($this->dotw[$this->startoffset] != $this->firstday) $this->startoffset++;

	// prev month data
	if ($this->startoffset) {
		// determine total days in the previous month. 
		$numdayslast = ($this->month == 1) 
			? $this->daysinmonth($this->year - 1, 12)
			: $this->daysinmonth($this->year, $this->month - 1);
		for($i = $this->startoffset; $i >= 1; $i--) {
			$ymd = date('Y-m-d', $this->start - 60*60*24*$i);
			$this->days[$ymd] = $this->_init_day($this->start - 60*60*24*$i);
		} 
	}

	// current month data
	for($i = 1; $i <= $this->daysinmonth($this->start); $i++) {
		$ymd = date('Y-m-d', $this->start + 60*60*24*($i-1));
		$this->days[$ymd] = $this->_init_day($this->start + 60*60*24*($i-1));
	} 

	// it's possible to have 6 weeks (42 days) on a single month (ex: April 2006).
	// if we currently have less than 5 weeks (35 days) then we know we won't go over.
	// but if we are already > 35 days before the loop below, then we'll need to add a 6th week.
	$max = count($this->days) <= 35 ? 35 : 42;
	// next month data
	if (count($this->days) < $max) {
		$lastday = mktime(0,0,0,$this->month, $this->daysinmonth($this->year, $this->month), $this->year);
		$i = 1;
		while(count($this->days) < $max) {
			$ymd = date('Y-m-d', $lastday + 60*60*24*$i);
			$this->days[$ymd] = $this->_init_day($lastday + 60*60*24*$i);
			$i++;
		}
	}
#	print "<pre>"; print_r($this->days); print "</pre>";
}

function _init_day($time) {
	return array(
		'time'	=> $time,
		'date'	=> date("Y-m-d", $time),
		'data'	=> array(),
	);
}

function day($date, $ary) {
	if (!$this->built) $this->build();
	// do not add any data to days that are not shown on the calendar
	if (!array_key_exists($date, $this->days)) return;
	$this->days[$date]['data'] = $ary;
}

// draw the calendar
function draw($print=FALSE) {
	static $id = 0;		// unique ID for the calendar (useful for css ID tags)
	$now = $this->time;
	$ymdthen = date('Y-m-d', $now);
	$ymdtoday = date('Y-m-d');
	list($year,$month,$day,$numdays,$monthname,$week) = explode(',', date('Y,m,d,t,F,W', $now));
	$lastday 	= date("D", mktime(0,0,0,$month,$numdays,$year));
	$prevyear 	= mktime(0,0,0,$month,$day,$year-1);
	$nextyear 	= mktime(0,0,0,$month,$day,$year+1);
	$prevmonth 	= mktime(0,0,0,$month,-1,$year);
	$nextmonth 	= mktime(0,0,0,$month,$numdays+1,$year);
	$numdayslast	= 0;

	if (!$this->built) $this->build();

	$id++;

	$width = $this->conf['cellwidth'];
	$hover = "onmouseover='addClassName(this, \"calendar-hover\")' onmouseout='removeClassName(this, \"calendar-hover\")'";

	// build headers
	$output .= $this->table_begin($id);
	$output .= "<tr align='center' class='calendar-hdr'><td>";
	$output .= ($this->conf['show_timeurl']) ? sprintf("<a href='%s'>&lt;&lt;</a>", $this->timeurl($prevmonth)) : '&nbsp;';
	$output .= sprintf("</td><td colspan='6'>%s %s</td><td>", $monthname, $year);
	$output .= ($this->conf['show_timeurl']) ? sprintf("<a href='%s'>&gt;&gt;</a>", $this->timeurl($nextmonth)) : '&nbsp;';
	$output .= "</td></tr>\n";

	$output .= "<tr class='calendar-hdr'><td width='$width' class='calendar-hdr-week'><acronym title='Week #'>Wk</acronym></td>";
	foreach ($this->dotw as $col) {
		$class = strtolower($col);
		$output .= "<td width='$width' class='calendar-hdr-$class'>$col</td>";
	}
	$output .= "</tr>\n";

	// build 5 calendar rows
	$i = 0;
	foreach ($this->days as $d) {
		if ($i == 0) {		// start of a new row
			$output .= sprintf("<tr><td width='%s' class='calendar-week' %s>%02d</td>", 
				$width, 
				$hover,
				date('W', $d['time'])
			);
			$i++;
		}

		// output dotw column
		$link = $d['data']['link'] ? sprintf("<a href='%s'>%s</a>", $d['data']['link'],date('d', $d['time'])) : '';
		$classes = sprintf('calendar-cell calendar-cell-%s', strtolower($this->dotw[$i-1]));
		$isoverflow = (substr($d['date'],0,7) != substr($ymdthen,0,7));
		if ($link and $isoverflow) {
			$classes .= ' calendar-cell-overflowdata';
		} else {
			if ($link) $classes .= ' calendar-cell-hasdata';
			if ($isoverflow) $classes .= " calendar-cell-overflow";
		}
		if ($link and $ymdthen == $d['date']) $classes .= ' calendar-cell-selected';
		if ($d['date'] == $ymdtoday) $classes .= ' calendar-cell-today';

		$output .= sprintf("<td width='%s' class='$classes' %s%s>%s</td>", 
			$width,
			$hover,
			$link ? " onclick=\"window.location.href='" . $d['data']['link'] . "'\"" : '',
			$link ? $link : date('d', $d['time'])
		);

		if ($i == 7) {		// end of current row
			$output .= "</tr>\n";
			$i = 0;
		} else {
			$i++;
		}
	}

	$output .= "</table>";

	if ($print) {
#		print nl2br(htmlentities($output))."\n";
		print $output;
	}
	return $output;
}

function timeurl($time) {
	$PHP_SELF = $_SERVER['PHP_SELF'];
	if (!$this->conf['show_timeurl']) return '';
	$url = '';
	if ($this->conf['timeurl_callback'] and function_exists($this->conf['timeurl_callback'])) {
		$func = $this->conf['timeurl_callback'];
		$url = $func($this, $time);
	} else {
		$url = sprintf("$PHP_SELF?%s=%d", $this->conf['timevar'], $time);
		if ($this->conf['timeurl']) $url .= "&amp;" . $this->conf['timeurl'];
	}
	return $url;
}

function table_begin($id) {
	static $attr = array('border','class','width','cellspacing','cellpadding','style');
	$output = "<table id='calendar$id'";
	foreach ($attr as $a) {
		if (!array_key_exists($a, $this->conf)) continue;
		if ((string)$this->conf[$a] != '') $output .= sprintf(" %s='%s'", $a, $this->conf[$a]);
	}
	$output .= ">\n";
	return $output;
}

function daysinmonth($year,$month=NULL) {
	static $dim  = array(31,28,31,30,31,30,31,31,30,31,30,31);
	static $mdim = array(31,29,31,30,31,30,31,31,30,31,30,31);

	if ($month == NULL) {
		$t = localtime($year, TRUE);
		$year = $t['tm_year'] + 1900;
		$month = $t['tm_mon'];
	} else {
		$month--;
	}
	return $this->isleapyear($year) ? $mdim[$month] : $dim[$month];
}

function isleapyear($year) {
	if ($year % 4 != 0) return FALSE;
	if ($year % 100 != 0) return TRUE;
	if ($year % 400 != 0) return FALSE;
	return TRUE;
}

function dayofyear($year = NULL,$month = NULL,$day = NULL) {
	static $days = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334, 365);
	$time = 0;
	if (!$year and !$month and !$day) {
		$time = time();
	} elseif ($year and $month) {
		if (!$day) $day = 1;
		$time = mktime(0,0,0,$month,$day,$year); 
	} elseif ($year) {	// assume $year is an epoch timestamp
		$time = $year;
	}
	list($year,$month,$day) = split('-', date("Y-m-d", $time));
	$leapyear = ($month > 2 and $this->isleapyear($year)) ? 1 : 0;
	return $days[$month-1] + $day + $leapyear;
}

function ymd2time($date, $char='-') {
	list($y,$m,$d) = split($char, $date);
	return mktime(0,0,0,$m,$d,$y);
}

function time2ymd($time, $char='-') {
	return date(implode($char, array('Y','m','d')), $time);
}


} // end of Calendar class
?>
