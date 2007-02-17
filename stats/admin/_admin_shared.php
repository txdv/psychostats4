<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

define("VAR_SEPARATOR", '^');

function show($a) {
	print "<pre>";
	print_r($a);
	print "</pre>";
}

function _callback_doesc($str) {
	global $ps_db;
	return "'" . $ps_db->escape($str) . "'";
}

$valid_update_types = array( 'weapons','clantags','plrbonuses','awards','plrbans' );
// returns the last update time of CSV file import files on psychostats.com.
// used on various 'import' controls in the ACP.
function ps_import_update_time($type) {
	global $ps, $ps_lang, $valid_update_types;
	$type = strtolower($type);
	if (!in_array($type, $valid_update_types)) {
		$type = 'index';
	}
	$url = sprintf("http://www.psychostats.com/updates/$type.php?v=%s&g=%s&m=%s",
		urlencode($ps->conf['info']['version']), 
		urlencode($ps->conf['main']['gametype']), 
		urlencode($ps->conf['main']['modtype'])
	);

	$lastmod = 0;
	ob_start();
	$fh = fopen("$url&l=1", 'r');
	if ($fh) {
		list($lastmod,$msg) = explode("\t", fgets($fh));
		fclose($fh);
	} else {
		$err = ob_get_contents();
		$msg = $ps_lang->trans("Error connecting to server");
	}
	ob_end_clean();
	$lastmod = (int)$lastmod;
	return array($lastmod,$msg);
}

// returns a file handle to read the CSV import data
function ps_import_open($type) {
	global $ps, $valid_update_types;
	$type = strtolower($type);
	if (!in_array($type, $valid_update_types)) {
		$type = 'index';
	}
	$url = sprintf("http://www.psychostats.com/updates/$type.php?v=%s&g=%s&m=%s",
		urlencode($ps->conf['info']['version']), 
		urlencode($ps->conf['main']['gametype']), 
		urlencode($ps->conf['main']['modtype'])
	);
	return fopen($url, 'r');
}

// returns true/false if the conf var given is allowed to have multiple duplicates
function confvarmulti($var) {
	global $conf_layout;

	$parts = explode(VAR_SEPARATOR, $var);
	array_pop($parts);
	$var = implode(VAR_SEPARATOR, $parts);
	return $conf_layout[$var]['multiple'] ? TRUE : FALSE;
}

function sortidx($a,$b) {
	if ($a['idx'] == $b['idx']) return 0;
	return ($a['idx'] < $b['idx']) ? -1 : 1;
}

// ------------------------------------------------------------------------------
class PSAdminMenu {

function __construct($level = 0) { $this->PSAdminMenu($level); }	// php5
function PSAdminMenu($level = 0) {					// php4
	$this->level = $level;
	$this->sections = array();
	$this->order = array();
}

function sort() {
	$this->order = array_keys($this->sections);
	sort($this->order);
	uksort($this->sections,'strcasecmp');
	foreach ($this->order as $title) {
		$this->sections[$title]->sort();
	}
}

function & getSection($section) {
	if (!array_key_exists($section, $this->sections)) {
		$this->order[] = $section;
		$this->sections[ $section ] = new PSMenuSection($section, $this->level + 1);
	}
	return $this->sections[ $section ];
}

} // end of class PSAdminMenu

class PSMenuSection {

function __construct  ($title, $level = 0) { $this->PSMenuSection($title, $level); }	// php5
function PSMenuSection($title, $level = 0) {						// php4
	$this->level 	= $level === NULL ? 0 : $level;
	$this->title 	= $title;
	$this->options	= array();
	$this->order	= array();
	$this->icon 	= 'folder-opened.png';
}

function sort() {
	$this->order = array_keys($this->options);
	sort($this->order);
	uksort($this->options,'strcasecmp');
}

function & newOption($title, $id) {
	$this->order[] = $title;
	$this->options[ $title ] = new PSMenuOption($title, $id, $this->level + 1);
	return $this->options[ $title ];
}

function label() {
	return $this->iconimg() . " " . htmlentities($this->title, ENT_COMPAT, "UTF-8");
}

function icon($selected = 0) {
	return $selected ? $this->icon_selected : $this->icon;
}

function iconimg($selected = 0) {
	$icon = THEME_URL . '/images/' . $this->icon($selected);
	return sprintf("<img src='%s' height='18' width='18' align='absmiddle' />", htmlentities($icon));
}

} // end of class PSMenuSection

class PSMenuOption {

function __construct ($title, $id, $level = 0) { $this->PSMenuOption($title, $id, $level); }	// php5
function PSMenuOption($title, $id, $level = 0) {						// php4
	$this->level 		= $level === NULL ? 0 : $level;
	$this->title 		= $title;
	$this->id		= $id;
	$this->icon 		= 'folder-closed.png';
	$this->icon_selected 	= 'folder-closed.png';
	$this->link 		= '';
}

function label($selected = 0) {
	$html = $this->iconimg($selected) . " " . htmlentities($this->title, ENT_COMPAT, "UTF-8");
	if ($this->link) {
		return sprintf("<a href='%s'>%s</a>", $this->link, $html);
	} else {
		return $html;
	}
}

function link($url) {
	$this->link = $url;
	return $this->link;
}

function icon($selected = 0) {
	return $selected ? $this->icon_selected : $this->icon;
}

function iconimg($selected = 0) {
	$icon = THEME_URL . '/images/' . $this->icon($selected);
	return sprintf("<img src='%s' height='18' width='18' align='absmiddle' />", htmlentities($icon));
}

} // end of class PSMenuOption

?>
