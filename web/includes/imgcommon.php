<?php
/*
	Common IMAGE routines. This file generally only contains simple setup 
	and configs for all images created within the context of PsychoStats.

	Most of the config in here are simple overrides from the jtp-config.php.
*/
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if (defined("PSFILE_IMGCOMMON_PHP")) return 1;
define("PSFILE_IMGCOMMON_PHP", 1);

define("NOTIMER", 1);
//define("NOTHEME", 1);
define("NOSESSION", 1);
define("NO_CONTENT_TYPE", 1);
include(dirname(__FILE__) . "/common.php");

// XML Image Config
$imgconf = XML_unserialize(implode("", file(THEME_DIR . "/config.xml")));
$imgconf = $imgconf['config'];
if (!is_array($imgconf)) $imgconf = array();

// JPGRAPH config

// If true images are cached 
define("USE_CACHE", $ps->conf['theme']['images']['cache_enabled'] ? true : false);
define("READ_CACHE", true);

// # of minutes before a cached image is recreated
// 0=never timeout! This means images will only be created once! 
define("CACHE_TIMEOUT", $ps->conf['theme']['images']['cache_timeout']);	

// Path to store cached images. If left blank system defaults will be used
if (!empty($ps->conf['theme']['images']['cache_dir'])) {
	define("CACHE_DIR", $ps->conf['theme']['images']['cache_dir']);
} else {
	define("CACHE_DIR", tmppath("jpgraph_cache"));
}

// Path to the TTF fonts. If left blank system defaults will be used
/*
if (!empty($ps->conf['theme']['images']['ttf_dir'])) {
	define("TTF_DIR", $ps->conf['theme']['images']['ttf_dir']);
}
#define("TTF_DIR", "/usr/share/fonts/truetype/msttcorefonts/");
*/

define("INSTALL_PHP_ERR_HANDLER", true);
define("CACHE_FILE_GROUP", "");

//define("BRAND_TIMING", true);	// if true all images will have a timing value on the left footer

// We must load the proper JPGRAPH version depending on our version of PHP
define("JPGRAPH_DIR", dirname(__FILE__) . '/jpg' . substr(PHP_VERSION,0,1));

define("CATCH_PHPERRMSG", false);

// all JPG constants MUST be defined BEFORE the jpgraph core routines are included
include(JPGRAPH_DIR . '/jpgraph.php');

// remove all pending output buffers
while (@ob_end_clean());

function isImgCached($file) {
	if (!USE_CACHE) return false;
	if ($file == 'auto') {
		$file = GenImgName();		// imported from jpgraph.php
	}

	$filename = catfile(CACHE_DIR, $file);
	if (file_exists($filename)) {
		if (CACHE_TIMEOUT == 0) return true;
		$diff = time() - filemtime($filename);
#		print "$diff < " . (CACHE_TIMEOUT * 60) . "<br>";
		return ($diff < CACHE_TIMEOUT * 60);
	} 
	return false;
}

function stdImgFooter(&$graph,$left=true,$right=true) {
	global $ps;
	if ($left and imgconf('images.footer attr.show',1)) {
		$graph->footer->left->Set(sprintf(imgconf('images.footer.left', 'PsychoStats v%s'), $ps->conf['info']['version']));
		$graph->footer->left->SetColor(imgconf('images.footer attr.color', 'black@0.5'));
		$graph->footer->left->SetFont(constant(imgconf('images.footer attr.font','FF_FONT0')),FS_NORMAL);
	}

	if ($right and imgconf('images.footer attr.show',1)) {
		$graph->footer->right->Set(date(imgconf('images.footer.right', 'Y-m-d @ H:i:s')));
		$graph->footer->right->SetColor(imgconf('images.footer attr.color', 'black@0.5'));
		$graph->footer->right->SetFont(constant(imgconf('images.footer attr.font','FF_FONT0')),FS_NORMAL);
	}
}

function imgconf($var, $default='') {
	global $imgconf;
	$keys = explode('.', $var);
	$a =& $imgconf;	
	foreach ($keys as $k) {
		if (!array_key_exists($k, $a)) return $default;
		$a =& $a[$k];
	}
	return $a != '' ? $a : $default;
}
?>
