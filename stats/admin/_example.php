<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

// register the control so it will appear on the admin menu
if ($register_admin_controls) {
	return 0;	// returning zero here will disable the control
			// also, if the filename begins with an underscore '_' it is ignored

	// this is an example of how a control should register it's menu choices on the admin menu

	// First, get the menu object that you want to add your control to...
	$menu =& $PSAdminMenu->getSection( $ps_lang->trans("Configuration") );

	// Then add new options for your controls. 
	// 'control_ident' should be a unique string to identify your control. 1 word.
	// "Option Name" is the string that is displaed in the menu.
	$opt =& $menu->newOption( $ps_lang->trans("Option Name"), 'control_ident' );

	// Create the link for your control. Using ps_url_wrapper() is recommended so that when PS3 is embedded inside
	// other 3rd party software (like PostNuke or PHPBB2) the urls will be created correctly.
	// Each option in the array is an url parameter. See the theme docs on {url} for more information.
	// 'c' is almost always the basename of the current file name. Add any other options as needed.
	$opt->link(ps_url_wrapper(array('c' => '_example')));

	// always return a true value if your control initialization was successful
	return 1;
}

// set the PS_ADMIN_PAGE theme var to match the 'control_ident' actually being shown.
// This allows the admin menu to highlight the proper control link for the proper admin page when activated.
$data['PS_ADMIN_PAGE'] = 'control_ident';

// Normal page processing would be done here...

// DO NOT DEFINE ANY functions! Doing so will cause redefine errors.
// If you require functions. Create a 2nd file with the same name as your control
// with an underscore "_" prepending it, ie: _mycontrol.php and define functions there.

?>
