<?php

class Theme_asset extends MY_Controller {

	function Theme_asset()
	{
		// we do not need Sessions or Users.
		define('PS_NO_SESSION', true);
		define('PS_NO_USER', true);

		// theme assets should always work, regardless if the
		// compile_dir is valid or not.
		define('PS_NO_SMARTY_VERIFY', true);

		parent::MY_Controller();
		$this->load->helper('file');
	}
	
	function index() 
	{
		$config =& get_config();

		// get full segment path ...
		$path = $this->uri->segment_array();
		
		// remove the first 'asset' segment
		array_shift($path);
		
		// there has to be at least 2 more parts to the path
		if (count($path) < 2) {
			show_404();
		}
		
		// remove theme from path
		$theme = array_shift($path);
		
		// combine remaining segments into a relative file name
		$file = implode(DIRECTORY_SEPARATOR, $path);
		
		// try to determine the content-type of the file
		$mime = get_mime_by_extension($file);
		if (!$mime) {
			$mime = 'octet/stream';
		}

		$theme_data = $this->ps->get_ps_config();
		
		header("Content-Type: $mime; charset=" . $config['charset'], true);
		$this->smarty->view($file, $theme_data['theme'], $theme);
	}
}

// not used yet.
function setModifiedDate($contentDate) {
    $ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? stripslashes($_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;
    if ($ifModifiedSince && strtotime($ifModifiedSince) >= $contentDate) {
        header('HTTP/1.0 304 Not Modified');
        die; // stop processing
    }
    $lastModified = gmdate('D, d M Y H:i:s', $contentDate) . ' GMT';
    header('Last-Modified: ' . $lastModified);
}

?>
