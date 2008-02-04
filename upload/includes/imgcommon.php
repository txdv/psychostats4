<?php
/*
	Common IMAGE routines. This file generally only contains simple setup 
	and configs for all images created within the context of PsychoStats.
*/
if (!defined("PSYCHOSTATS_PAGE")) die("Unauthorized access to " . basename(__FILE__));

if (defined("PSFILE_IMGCOMMON_PHP")) return 1;
define("PSFILE_IMGCOMMON_PHP", 1);

// some routines (PEAR::XML) are coded with relative paths so I need to explicitly update the include_path
ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . dirname(__FILE__));

require_once(dirname(__FILE__) . "/common.php");
require_once(dirname(__FILE__) . "/XML/Unserializer.php");
$cms->init_theme($ps->conf['main']['theme'], $ps->conf['theme']);
$ps->theme_setup($cms->theme);

// JPGRAPH config

// If true images are cached 
define("USE_CACHE", $ps->conf['theme']['images']['cache_enabled'] ? true : false);
define("READ_CACHE", true);

// # of minutes before a cached image is recreated
// 0=never timeout! This means images will only be created once! 
define("CACHE_TIMEOUT", $ps->conf['theme']['images']['cache_timeout']);	

// Path to store cached images. If left blank system defaults will be used
if (!empty($ps->conf['theme']['images']['cache_dir'])) {
	define("CACHE_DIR", catfile($ps->conf['theme']['images']['cache_dir']) . DIRECTORY_SEPARATOR);
} else {
	define("CACHE_DIR", catfile(get_temp_dir(), "ps_img_cache") . DIRECTORY_SEPARATOR);
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
		return ($diff < CACHE_TIMEOUT * 60);
	} 
	return false;
}

function stdImgFooter(&$graph,$left=true,$right=true) {
	global $ps, $imgconf;
	$i =& $imgconf;
	if ($left and imgdef($i['common']['footer']['show'],1)) {
		$graph->footer->left->Set(sprintf(imgdef($i['common']['footer']['left'], 'PsychoStats v%s'), $ps->version(true)));
		$graph->footer->left->SetColor(imgdef($i['common']['footer']['color'], 'black@0.5'));
		$graph->footer->left->SetFont(constant(imgdef($i['common']['footer']['font'],'FF_FONT0')),FS_NORMAL);
	}

	if ($right and imgdef($i['common']['footer']['show'],1)) {
		$graph->footer->right->Set(date(imgdef($i['common']['footer']['right'], 'Y-m-d @ H:i:s')));
		$graph->footer->right->SetColor(imgdef($i['common']['footer']['color'], 'black@0.5'));
		$graph->footer->right->SetFont(constant(imgdef($i['common']['footer']['font'],'FF_FONT0')),FS_NORMAL);
	}
}

function imgdef($var, $def = '') {
	if ($var != '') {
		return $var;
	}
	return $def;		
}

function load_img_conf() {
	global $ps, $cms;
	$xml = &new XML_Unserializer(array(
		XML_UNSERIALIZER_OPTION_ATTRIBUTES_PARSE    => true,
		XML_UNSERIALIZER_OPTION_ATTRIBUTES_ARRAYKEY => false
	));
	$status = $xml->unserialize($cms->theme->parse('images.xml'));
	if (PEAR::isError($status)) {
		die("Error reading $file: " . $status->getMessage());
	} else {
		$conf = $xml->getUnserializedData();
		if (!is_array($conf)) $conf = array();
		return $conf;
	}
}


?>
