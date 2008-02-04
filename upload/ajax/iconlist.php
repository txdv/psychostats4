<?php
/*
	AJAX REQUEST PAGE
	$Id$

	This ajax request simply returns a list of all available icons on the system either as
	a comma separated list (CSV), or a list of <img> tags.
*/
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_SUBPAGE", true);
include(dirname(__FILE__) . "/../includes/common.php");
include("./ajax_common.php");

// only allow logged in users to request a list?
/**/
if (!$cms->user->logged_in()) {
	header("X-Error: Not logged in");
	print "List not available, you are not logged in! Reload your browser window.";
	exit;
}
/**/

// collect url parameters ...
$t = strtolower($_GET['t']);
$idstr = $_GET['id'];
$idstr = str_replace(' ', '', urldecode($idstr));	// strip spaces and make sure it's not double url encoded
if ($idstr == '') $idstr = 'icon-';

if (!in_array($t, array('csv','xml','dom','img'))) $t = 'img';

$list = array();

// first build a list of icons from our local directory
$dir = $ps->conf['theme']['icons_dir'];
$url = $ps->conf['theme']['icons_url'];
if ($dh = @opendir($dir)) {
	while (($file = @readdir($dh)) !== false) {
		if (substr($file, 0, 1) == '.') continue;	// skip dot files
		$fullfile = catfile($dir, $file);
		if (is_dir($fullfile)) continue;		// skip directories
		if (is_link($fullfile)) continue;		// skip symlinks
		$info = getimagesize($fullfile);
		$size = @filesize($fullfile);
		$list[$file] = array(
			'filename'	=> rawurlencode($file),
			'url'		=> catfile($url, rawurlencode($file)),
			'desc'		=> ps_escape_html(sprintf("%s - %dx%d - %s", $file, $info[0], $info[1], abbrnum($size))),
			'size'		=> $size,
			'width'		=> $info[0],
			'height'	=> $info[1],
			'attr'		=> $info[3],
		);
	}
	@closedir($dh);
}
ksort($list);

$fields = array( 'filename', 'url', 'size', 'width', 'height' );

output_list($t, $list, $fields, $idstr);

?>
