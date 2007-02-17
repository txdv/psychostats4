<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if ($register_admin_controls) {
	$menu =& $PSAdminMenu->getSection( $ps_lang->trans("Configuration") );

	$opt =& $menu->newOption( $ps_lang->trans("Icons"), 'icons' );
	$opt->link(ps_url_wrapper(array('c' => 'icons')));

	return 1;
}

$data['PS_ADMIN_PAGE'] = "icons";

include(dirname(dirname(__FILE__)) . "/uploadicon.php");

// we must exit() here since the uploadicon.php takes care of all page display.
// we do not want the main admin.php to try and output anything here.
exit();

?>
