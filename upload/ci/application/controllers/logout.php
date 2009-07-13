<?php

class Logout extends MY_Controller {

	function Logout()
	{
		parent::MY_Controller();
	}
	
	function index()
	{
		define('PAGE', 'logout');
		define('TEMPLATE', PAGE);

		$data = array(
			'title'		=> trans('User Logged Out'),
			'was_logged_in' => $this->ps_user->logged_in(),
			'who'		=> clone $this->ps_user
		);

		if ($this->ps_user->logged_in()) {
			// clear the user_id from the session
			$this->session->unset_userdata('user_id');

			// delete the 'remember' cookie if its present
			delete_cookie($this->session->sess_cookie_name . '_remember');

			// remove any session_salt in the user record
			$this->ps_user->save(array( 'session_salt' => null ));
		} else {
			//redirect($this->input->get_post('ref'));
		}
		$this->load->view('full_page', array('params' => $data));
	}
}

?>