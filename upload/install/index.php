<?php
/*	
	The installation script avoides using any of the normal themes and functions.
*/

define("VALID_PAGE",1);
define("NOSESSION", 1);

error_reporting(E_ERROR | E_WARNING | E_PARSE);

include("../includes/undo_magic_quotes.php");
include("../includes/user_handler_normal.php");
include("functions.php");
include("forms.php");

include("../config.php");
if (defined('PSYCHOSTATS_INSTALLED') and $_REQUEST['step'] != 99) {
	print "PsychoStats v3.0 is already installed! -- For security reasons you should delete this directory!<br>";
	print "If you're trying to re-install you need to remove the 'PSYCHOSTATS_INSTALLED' declaration from the config.php<br>";	
	exit();
}

// this is automatically updated by the packaging script for new releases
$VERSION = '3.0.4b';

$validvars = array('step','submit');
globalize($validvars);
$PHP_SELF = $_SERVER['PHP_SELF'];

$is_windows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

if (!is_numeric($step)) $step = 1;

include("header.php");

$file = "step" . (int)$step . ".php";
if (file_exists($file)) {
	include($file);
} else {
	err("Invalid installation step specified! Aborting!");
}

include("footer.php");

function err($msg, $exit=1) {
	print "<div align='center'><span style='padding: 5px; border: 1px dashed darkred; background-color: #FFFFDD;'>$msg</span></div>";
	if ($exit) exit();
}

function savepost($ary=array()) {
	if (!$ary) $ary = &$_POST;
	$output = "";
	foreach ($ary as $key => $var) {
		if ($key == 'submit') continue;
		if ($key == 'step') continue;
		$output .= sprintf("<input type='hidden' name='%s' value='%s' />\n", htmlentities($key), htmlentities($var));
	}
	return $output;
}

// attempt to save the config.php file
function saveconf($file, $c, &$msg) {
	if (is_writable($file)) {
		if (!$h = fopen($file, 'w')) {
			$msg = "unable to open $file for writting!";
			return FALSE;
		} 
		if (fwrite($h, $c, strlen($c)) === FALSE) {
			$msg = "unable to write to $file";
			return FALSE;
		}
		fclose($h);
		$msg = "config saved successfully!";
	} else {
		$msg = "$file is not writable by the webserver!";
		return FALSE;
	}
	return TRUE;
}

if (!function_exists('ps_url_wrapper')) {
	function ps_url_wrapper($arg) {
		return url($arg);
	}
}

?>
