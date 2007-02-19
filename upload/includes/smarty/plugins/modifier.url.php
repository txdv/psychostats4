<?php

/*
	Allows a third party module to process any url within the themes w/o having to modify the
	themes directly (Assuming the author of the theme uses the url modifier everywhere.

	This is useful for wrapping PS into a CMS site like PostNuke, etc...
*/


function smarty_modifier_url($url) {
	// do something here to transform the url if needed ...
	return $url;
}

?>
