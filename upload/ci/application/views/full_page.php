<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 *	Generic 'full page' view that contains the following heirarchy:
 *	HEADER (with menu)
 *	CONTENT
 *	FOOTER
 */

if (!defined('BODY_LAYOUT')) {
	define('BODY_LAYOUT', $this->smarty->page_layout('full'));
}
if (!defined('TEMPLATE')) {
	define('TEMPLATE', 'base_full_page');
}

//$page = $params['page'] ? $params['page'] : 'index';
$this->smarty->view(TEMPLATE, $params);

?>
