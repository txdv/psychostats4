<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Home extends MY_Controller {

	function Home()
	{
		parent::MY_Controller();
	}
	
	function index()
	{
		$data = array(
			
		);

		// load 'default_view' from config to determine which page
		// should be loaded first (portal, players, awards, etc)
		// .... todo ....
		define('PAGE', 'home');
		//define('TEMPLATE', 'home');
		
		$this->load->view('full_page', array('params' => $data));
	}
}

?>