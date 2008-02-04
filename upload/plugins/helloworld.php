<?php
/***
	"Hello World" plugin.

	This is not a useful plugin. It's simply for educational purposes only. 
	Use this as a baseline when creating your own plugins for PsychoStats.

***/

// the class name must be the same as the plugin directory or filename.
// all plugins must inherhit PsychoPlugin
class helloworld extends PsychoPlugin {
	var $version = '1.0';
	var $errstr = '';

// called when the plugin is loaded. This is called on every page request.
// You'll want to register all your hooks here.
function load(&$cms) {
	// an example of registering a hook. In this case we register a filter
	// on the 'overall_header' hook. Our class needs a 'filter_overall_header' method
	// that will be called automatically when the hook triggers.
	$cms->register_filter($this, 'overall_header');
/*
	If loading fails, a plugin should set the error string $errstr and return false
	if ('something broke' and false) {
		$this->errstr = "Error loading plugin";
		return false;
	}
*/

	// return true if everything is loaded ok
	return true;
}

function install(&$cms) {
	$info = array();
	$info['version'] = $this->version;
	$info['description'] = "This is an example plugin that does nothing useful. View the plugin code to see how to make your own plugins!";
	return $info;
}

// our filter hook. This is called automatically when the 'overall_header' hook is triggered. 
// This is a filter which means we're given a reference to a string (or other object). 
// Any changes to the $output will be permanent.
function filter_overall_header(&$output, &$cms, $args = array()) {
//	$output = strtoupper($output);
	$output .= "<b>$this</b> updated the overall header!<br>";
}

} // end of helloworld


?>
