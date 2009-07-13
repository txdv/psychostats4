<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Login extends MY_Controller {
	// @var string Tracks a global form error
	var $form_error = '';
	var $data = array();
	
	function Login()
	{
		parent::MY_Controller();
	}
	
	// login page
	function index()
	{
		if ($this->input->get_post('cancel')) {
			redirect_previous();
		}
		
		$this->load->library('form_validation');
		$this->load->helper('form');
		
		$data = array(
			'title'		=> 'User Login',
			'page_title' 	=> 'Please Login',
			'form_error'	=> &$this->form_error,
			//'page_subtitle'	=> 'Sub Title',
		);

		$this->form_validation->set_message('required', trans('%s is required.'));
		$this->form_validation->set_rules('un', 'Username', 'trim|required');
		$this->form_validation->set_rules('pw', 'Password', 'trim|required|callback__auth[page_show]');
		$this->form_validation->set_rules('remember', '', '');

		define('PAGE', 'login');
		define('TEMPLATE', PAGE);

		if ($this->form_validation->run() == FALSE) {
			$this->load->view('full_page', array('params' => $data));
		} else {
			// success! Setup the session
			$un = $this->form_validation->set_value('un');
			$pw = $this->form_validation->set_value('pw');
			$remember = $this->form_validation->set_value('remember') ? true : false;

			//$user->last_login();

			try {
				$user = new Psychostats_user($un, 'username');
			} catch (Exception $e) {
				show_error('Error initializing user session: ' . $e->getMessage());
			}

			$set = array();

			// generate new salt for the password and update the user.
			if ($this->config->item('login_regen_password_salt')) {
				$set['password_salt'] = $user->generate_salt();
				$set['password'] = $user->hash($pw, $set['password_salt']);
			}

			// If "remember me" is checked set a special cookie that
			// will re-login the user the next time they visit. This
			// cookie contains their id and a specially hashed copy
			// of their password (which is different from their
			// normal password).
			if ($remember) {
				$set['session_salt'] = $user->generate_salt();
				set_cookie(array(
					'name' => $this->session->sess_cookie_name . '_remember',
					'value' => $user->id() . '/' . $user->hash($set['password'], $set['session_salt']), 
					'expire' => 60*60*24*30		// 30 days
				));
			}

			// save any updates to the user record now...
			if ($set) {
				if (!$user->save($set)) {
					show_error('Error processing user login.');
				}
			}

			// update the session so it points to the user record.
			// this counts as our 'is logged in' flag too.
			$this->session->set_userdata(array( 'user_id' => $user->id() ));
		
			// redirect to where we were ...
			//$this->load->view('full_page', array('params' => $data)); // testing
			redirect_previous();
		}

	}

	// password reset page
	function reset($id = null, $token = null) {
		if ($this->input->get_post('cancel')) {
			redirect_previous();
		}
		
		$this->load->library('form_validation');
		$this->load->library('email');
		$this->load->helper('form');

		$this->email->initialize(array('mailtype' => 'html'));

		$this->form_validation->set_message('required', trans('%s is required.'));
		$this->form_validation->set_message('valid_email', trans('You must enter a valid %s.'));
		
		$this->data = array(
			'title'		=> 'Password Reset',
			'page_title' 	=> 'Password Reset',
			'form_error'	=> &$this->form_error,
			'valid_request'	=> true,
			//'page_subtitle'	=> 'Sub Title',
		);

		if ($id and $token) {
			$this->_process_reset($id, $token);
		} else {
			$this->_request_reset();
		}

		define('PAGE', 'login_reset');
		define('TEMPLATE', PAGE);
		$this->load->view('full_page', array('params' => $this->data));
	}

	function _process_reset($id, $token) {
		$this->form_validation->set_rules('pw1', 'Password', 'trim|required');
		$this->form_validation->set_rules('pw2', 'Repeat password', 'trim|required|matches[pw1]');
		
		$this->form_validation->set_message('matches', trans('Both passwords do not match!'));
		$this->data['process_reset'] = true;
		
		// first make sure the $id is valid
		$user = new Psychostats_user;
		if (!$user->load($id)) {
			$this->data['valid_request'] = false;
			return;
		}

		// now make sure the token matches the user session_salt
		if ($user->session_salt != $token) {
			$this->data['valid_request'] = false;
			return;
		}
		
		$this->data['who'] = $user;

		// at this point the user ID and token are valid.
		// process the form when its submitted to set the new password
		// for the user.

		if ($this->form_validation->run() == FALSE) {
			// ...
		} else {
			// success! do it!
			$pw = $this->form_validation->set_value('pw1');
			$salt = $user->generate_salt();
			$user->save(array(
				'password' => $user->hash($pw, $salt),
				'password_salt' => $salt,
				'session_salt' => null
			));
			$this->data['email_sent'] = true;
			$this->data['password_reset'] = true;
			$this->data['request_reset'] = false;

			$config =& get_config();

			$email = $user->data('email');
			$from = isset($config['domain_name'])
				? 'noreply@' .$config['domain_name']
				: $email;
			
			// send another email explaining the change...
			$this->email->clear();
			$this->email->from($from);
			$this->email->to($email);
			$this->email->subject(trans('Password reset for %s', $user->username));
	
			$data = array(
				'who'	=> $user,
				'to'    => $email,
				'from'	=> $from,
				'reset_url' => site_url('login/reset')
			);
			$message = $this->smarty->view('email/email_login_reset_successful.html', $data, null, true);		
			
			$this->email->message($message);
			$this->email->send();
		}
	}

	function _request_reset() {
		$this->form_validation->set_rules('un', 'Username', 'trim|required');
		$this->form_validation->set_rules('email', 'email address', 'trim|required|valid_email|callback__setup_reset');

		$this->data['request_reset'] = true;

		if ($this->form_validation->run() == FALSE) {
			// ...
		} else {
			// success!
			$this->data['username'] = $this->form_validation->set_value('un');
			$this->data['email'] = $this->form_validation->set_value('email');
			$this->data['email_sent'] = true;
			$this->data['request_reset'] = false;
		}
	}

	// callback for the 'email' field to setup a reset
	function _setup_reset($email) {
		$this->form_validation->set_message('_setup_reset', '');
		$un = $this->form_validation->set_value('un');
		$email = $this->form_validation->set_value('email');
		$q = $this->db->get_where('user', array(
			'username'	=> $un,
			'email'		=> $email
		));

		if ($q->num_rows() == 0) {
			$this->form_error = trans('The email address does not belong to the username entered.');
			return false;
		}
		
		// setup the user record with a new salt token and send an email
		// to the user with instructions.
		$user = new Psychostats_user;
		if (!$user->load($un, 'username')) {
			show_error('Error loading user for password reset!');
		}
		$salt = $user->generate_salt(16);
		$token = $salt;
		$user->save(array( 'session_salt' => $salt ));

		$config =& get_config();
		
		$from = isset($config['domain_name'])
			? 'noreply@' .$config['domain_name']
			: $email;

		$this->email->clear();
		$this->email->from($from);
		$this->email->to($email);
		$this->email->subject(trans('Password reset for %s', $un));

		$data = array(
			'who'	=> $user,
			'token'	=> $token,
			'to'    => $email,
			'from'	=> $from,
			'reset_url' => site_url('login/reset') . '/' . $user->id() . '/' . $token
		);
		$message = $this->smarty->view('email/email_login_reset.html', $data, null, true);		
		
		$this->email->message($message);
		$this->email->send();
		
		return true;
	}

	// callback for the 'pw' password field to check if user/pass is valid.
	function _auth($pw, $val) {
		$user = new Psychostats_user;
		if (!$user->auth($this->form_validation->set_value('un'), $pw)) {
			// don't want an error to surround the password field.
			$this->form_validation->set_message('_auth', '');
			// set the overall form error instead.
			$this->form_error = 'Invalid username or password';
			return false;
		}
		return true;
	}
}

?>