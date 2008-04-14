<?php 
/**
 *	This file is part of PsychoStats.
 *
 *	Written by Jason Morriss <stormtrooper@psychostats.com>
 *	Copyright 2008 Jason Morriss
 *
 *	PsychoStats is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	PsychoStats is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with PsychoStats.  If not, see <http://www.gnu.org/licenses/>.
 *
 *	Version: $Id$
 */

if (defined("CLASS_THEMEINSTALLER_PHP")) return 1;
define("CLASS_THEMEINSTALLER_PHP", 1);

define("PSTHEME_ERR_NOTFOUND", 1);
define("PSTHEME_ERR_XML", 2);
define("PSTHEME_ERR_WRITE", 3);
define("PSTHEME_ERR_VALUE", 4);
define("PSTHEME_ERR_CONTENT", 5);

include_once(PS_ROOTDIR . "/includes/class_XML.php");


class PsychoThemeManager {
var $db = null;			// database handle
var $template_dir = null;	// where are the templates stored
var $xml = array();		// current theme XML from load_theme()
var $theme = array();		// current theme data from database
var $error = null;		// keeps track of the last error reported
var $code = null;
var $invalid = array();		// keeps track of each invalid xml variable

function PsychoThemeManager(&$db, $dir = null) {
	$this->db =& $db;
	$this->template_dir($dir);
}

// fetches the theme.xml from the location specified
function load_theme($url) {
	$this->error(false);
	ob_start();
	$xml = file_get_contents($url, false, null, 0, 4*1024);
	$err = ob_get_contents();
	if (!empty($err)) {	// cleanup the error a little bit so it's a little easier to tell what happened
		$err = str_replace(' [function.file-get-contents]', '', strip_tags($err));
	}
	ob_end_clean();

	$this->headers = $this->parse_headers($http_response_header);
	$this->xml = array();
	$data = array();
	if ($xml !== false) {
		// make sure the content-type returned is something reasonable (and not an image, etc).
		if ($this->is_xml()) {
			$data = XML_unserialize($xml);
			if ($data and is_array($data['theme'])) {
				$this->xml = $data['theme'];
				array_walk($this->xml, array(&$this, '_fix_xml_attrib'));
			} else {
				$this->error("Invalid theme XML format loaded from $url", PSTHEME_ERR_XML);
			}
		} else {
			$this->error("Invalid content-type returned for XML (" . $this->headers['content-type'] . ")", PSTHEME_ERR_CONTENT);
		}
	} else {
		$this->error($err, PSTHEME_ERR_NOTFOUND);
	}

	$ok = false;
	if (!$this->error() and $this->xml) {
		$ok = $this->validate_theme();
		if ($ok and file_exists(catfile($this->template_dir, $this->xml['name']))) {
			$this->xml['theme_exists'] = true;
		}
	}

	return $ok;
}

// attempts to install the current theme that was loaded
// this only downloads and unzips the archive, it does not insert a record into the database.
function install() {
	$ok = false;
	if (!$this->xml or $this->error()) {	// nothing to install
		return false;
	}

	// temporary file for download
	$localfile = tempnam(getcwd(),'ps3theme');
	$local = fopen($localfile,'wb');
	if (!$local) {
		$this->error("Error creating temporary file for download");
		return false;
	}

	// download the file
	$remote = fopen($this->xml_file(), "rb");
	if ($remote) {
		while (!feof($remote)) {
			$str = fread($remote, 8192);
			fwrite($local, $str);
		}
		fclose($remote);
		fclose($local);
	} else {
		$this->error("Error opening remote file for download");
		return false;
	}

	// try to read the downloaded file. It must be a zip file
	$ok = $this->open_zip($localfile);
	if ($ok) {
		$created = array();
		// loop through each file in the archive and save it to our local theme directory.
		// every file in the zip must have the theme 'name' as the root directory, or ignore it.
		while ($zip_entry = zip_read($this->zip)) {
			zip_entry_open($this->zip, $zip_entry);
			$name = zip_entry_name($zip_entry);
			if (strpos($name, $this->xml_name().'/') !== 0) {
				$this->error("Invalid directory structure in theme archive. ABORTING INSTALLATION");
				$ok = false;
				break;
			}
			if (substr($name, -1) == '/') {			// directory
				$dir = catfile($this->template_dir, substr($name,0,-1));
				if (!file_exists($dir)) {
					mkdir_recursive($dir);
					$created[] = $dir;
				}
			} else {					// file
				$file = catfile($this->template_dir, $name);
				$fh = fopen($file,'wb');
				if ($fh) {
					fwrite($fh, zip_entry_read($zip_entry, zip_entry_filesize($zip_entry)), zip_entry_filesize($zip_entry));
					fclose($fh);
					chmod($file, 0664);
					$created[] = $file;
				} else {
					$this->error("Error writing file $name from archive. File permissions are probably incorrect! ABORTING INSTALLATION");
					$ok = false;
					break;
				}
			}
			zip_entry_close($zip_entry);
		}
		$this->close_zip();

		// cleanup the installed theme if we failed!
		if (!$ok and $created) {
			foreach ($created as $file) { // we're not really concerned if this cleanup fails
				if (is_dir($file) and !is_link($file)) {
					@rmdir($file);
				} else {
					@unlink($file);
				}
			}
		}
	}

	// cleanup!
	@unlink($localfile);

	return $ok;
}

// attempts to open the file for reading.
function open_zip($file) {
	$res = false;
	if (!function_exists('zip_open')) {
		$this->error("Error processing downloaded file. ZIP support not fully enabled in your PHP installation.");
		return false;
	}
	$res = zip_open($file);
	$this->zip = $res;
	return $res ? true : false;
}

function close_zip() {
	if ($this->zip) {
		zip_close($this->zip);
	}
	$this->zip = null;
}

// returns true if we have an XML file according to the content-type header.
// text/xml, application/xml, plain/xml, text/plain, text/html
function is_xml($hdr = null) {
	if ($hdr === null) {
		$hdr =& $this->headers;
	}
	$ct = $hdr['content-type'];
	// if we don't have a content-type we assume the best and return true
	if (!$ct) return true;

	$ok = preg_match('@^\w+/xml|text/(plain|html)$@', $ct);

	return $ok;
	
}

// parses the response headers from a $http_response_header array
function parse_headers($fields) {
	$res = array( 'response' => 'Invalid Request', 'response_code' => '404' );
	if (!is_array($fields)) return $res;
	foreach( $fields as $field ) {
		if( preg_match('/([^:]+): (.+)/m', $field, $match) ) {
			$match[1] = preg_replace('/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower(trim($match[1])));
			$match[1] = strtolower($match[1]);
			if( isset($res[$match[1]]) ) {
				$res[$match[1]] = array($res[$match[1]], $match[2]);
			} else {
				$res[$match[1]] = trim($match[2]);
			}
		} else if (preg_match('/^HTTP\/\d.\d\s(\d+)/', $field, $match)) {
			$res['response'] = $field;
			$res['response_code'] = $match[1];
		}
        }
	return $res;
}

// replace XML @attributes with _attributes so the variables can be used in the theme output
function _fix_xml_attrib(&$ary, $key) {
//	print "$key = "; var_dump($ary); print "\n";
	if (substr($key,0,1) == '@') {
		$this->xml['_' . substr($key,1)] = $this->xml[$key];
		unset($this->xml[$key]);
	}
}

function validate_theme($url = null) {
	global $cms;
	if ($url !== null) {
		$this->load_theme($url);
	}
	if (!$this->xml) {
		return false;
	}
	$this->invalid(false);

	// make sure the name exists
	if ($this->xml_name() == '') {
		$this->error($cms->trans("No name defined"), PSTHEME_ERR_VALUE);
		$this->invalid('name', $cms->trans("A name must be defined"));
	}

	// make sure the name is valid
	if (!$this->re_match('/^[\w\d_\.-]+$/', $this->xml_name())) {
		$this->error($cms->trans("Invalid name defined"), PSTHEME_ERR_VALUE);
		$this->invalid('name', $cms->trans("Invalid characters found in name"));
	}

	// make sure the file exists
	if (!$this->xml_file()) {
		$this->error($cms->trans("No file defined"), PSTHEME_ERR_VALUE);
		$this->invalid('file', $cms->trans("A file location must be defined to download the theme from"));
	}

	// make sure the file exists on the remote server (but don't download it yet)
	if (!$this->test_file()) {
		$this->error($cms->trans("Theme download file not found or invalid type (" . $this->xml_file() . ")"), PSTHEME_ERR_VALUE);
		$this->invalid('file', $cms->trans("Unable to download theme file from " . $this->xml_file()));
	}

	// make sure the parent is valid
	if (!$this->re_match('/^[\w\d_\.-]+$/', $this->xml_parent())) {
		$this->error($cms->trans("Invalid parent defined"), PSTHEME_ERR_VALUE);
		$this->invalid('parent', $cms->trans("Invalid characters found in parent"));
	}

	// make sure the website is valid
	if (!$this->re_match('|^https?:/\/|', $this->xml_website())) {
		$this->error($cms->trans("Invalid website defined"), PSTHEME_ERR_VALUE);
		$this->invalid('website', $cms->trans("Website must start with http:// or https://"));
	}

	// make sure the source is valid
	if (!$this->xml_source()) {
		$this->error($cms->trans("No source defined"), PSTHEME_ERR_VALUE);
		$this->invalid('source', $cms->trans("A source location must be defined to download the theme from"));
	}

	// make sure the image is valid
	if (!$this->re_match('|^https?:/\/|', $this->xml_image())) {
		$this->error($cms->trans("Invalid image defined"), PSTHEME_ERR_VALUE);
		$this->invalid('image', $cms->trans("Image must start with http:// or https://"));
	}

	$err = $this->error();
	return empty($err);
}

// helper function to check a string against a regex patten.
// returns true if the str is in a valid format
function re_match($regex, $str) {
	if ($str == '') return true;
	return preg_match($regex, $str);
}

// readonly accessor functions for loaded XML theme values
function theme_xml() 		{ return $this->xml ? $this->xml : ''; }
function xml_name() 		{ return $this->xml ? trim($this->xml['name']) : ''; }
function xml_parent() 		{ return $this->xml ? trim($this->xml['parent']) : ''; }
function xml_website() 		{ return $this->xml ? trim($this->xml['website']) : ''; }
function xml_version() 		{ return $this->xml ? trim($this->xml['version']) : ''; }
function xml_title() 		{ return $this->xml ? trim($this->xml['title']) : ''; }
function xml_author() 		{ return $this->xml ? trim($this->xml['author']) : ''; }
function xml_source() 		{ return $this->xml ? trim($this->xml['source']) : ''; }
function xml_image() 		{ return $this->xml ? trim($this->xml['image']) : ''; }
function xml_file() 		{ return $this->xml ? trim($this->xml['file']) : ''; }
function xml_description() 	{ return $this->xml ? trim($this->xml['description']) : ''; }

function test_file($file = null) {
	if ($file === null) {
		$file = $this->xml_file();
	}

	ob_start();
	$fh = fopen($file, 'rb');
	$err = strip_tags(ob_get_contents());
	ob_end_clean();
	$hdr = $this->parse_headers($http_response_header);

	$ok = ($hdr['response_code'] == 200);
	if ($ok) {
		$this->xml['_file']['headers'] = $hdr;
		// if the content-length is returned keep track of it
		$this->xml['_file']['size'] = intval($hdr['content-length']);
		// try to determine the type of file. must be a ZIP
		// we can't rely on file extension since that will not always be available
		if ($hdr['content-type']) {
			$type = explode('/', $hdr['content-type']);
			$ct = array_pop($type);	// just look at the last part of 'application/zip'
			$this->xml['_file']['type'] = $ct;
			$ok = ($ct == 'zip'); // || $ct == 'rar');
		}
	}
	return $ok;
}

function template_dir($dir = null) {
	if ($dir === null) {
		$this->template_dir = catfile(PS_ROOTDIR, 'themes');
	} else {
		$this->template_dir = $dir;
	}
}

function invalid($key, $str = null) {
	if (empty($key)) {
		$this->invalid = array();
	} else {
		$this->invalid[$key] = $str;
	}
}
function invalid_list() {
	return $this->invalid;
}

function error($str = null, $code = null) {
	if ($str !== null) {
		$this->error = $str;
		$this->code($code);
	}
	return $this->error;
}

function code($code = null) {
	if ($code !== null) {
		$this->code = $code;
	}
	return $this->code;
}

} // end of PsychoThemeManager

?>
