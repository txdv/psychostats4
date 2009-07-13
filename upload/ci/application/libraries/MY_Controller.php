<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/*
	Extend the core controller to autoload certain objects for
	PsychoStats. We don't use the built in 'autoload' config within CI
	due to some customizations that are required for some pages and other
	'ease of use' scenarios.
*/

class MY_Controller extends Controller {
	
	function MY_Controller() {
		parent::Controller();
		$this->_psychostats_init();
	}

	function _psychostats_init() {
		// Most pages will have a DB connection. Some exceptions would
		// be the initial installer setup and possibly more...
		if (!defined('PS_NO_DATABASE')) {
			$this->load->database();
		}
		
		// Most pages will want the psychostats model and config.
		// DB must be loaded for these to work.
		if (!defined('PS_NO_DATABASE') and !defined('PS_NO_PSYCHOSTATS')) {
			$this->load->model('psychostats', 'ps');
			
			// load the PS config
			$this->ps_conf = $this->ps->get_ps_config(array('main','theme'));
			$this->ps_conf['main']['gametypes'] = config_item('gametypes');

			// Load the user
			if (!defined('PS_NO_USER')) {
				$this->load->model('psychostats_user', 'ps_user');
			}
		}

		// Most pages will have a user session.
		// Here we'll attempt to setup the main ps_user object by
		// loading the session and re-logging in the user if they were
		// not already and they have a 'remember me' cookie set.
		if (!defined('PS_NO_SESSION')) {
			if (defined('PS_NO_DATABASE')) {
				// don't save sessions in database
				$this->config->set_item('sess_use_database', false);
			}
			$this->load->library('session');
			
			if (!defined('PS_NO_DATABASE')) {
				if ($this->session->userdata('user_id')) {
					// load the user record for this session
					if (!$this->ps_user->load($this->session->userdata['user_id'])) {
						// invalid user found! reset the session
						$this->session->sess_destroy();
						$this->session->sess_create();
					}
				} else {
					// re-login the user from the 'remember me' cookie
					$remember = $this->input->cookie($this->session->sess_cookie_name . '_remember');
					if ($remember) {
						list($id, $pw) = explode('/', trim($remember));
						if ($this->ps_user->auth_remembered($id, $pw)) {
							// set the user_id in the session
							$this->ps_user->load($id);
							$this->session->set_userdata('user_id', $id);
						} else {
							$this->load->helper('cookie');
							// remove the 'remember' cookie
							// since it was invalid
							delete_cookie($this->session->sess_cookie_name . '_remember');
							
							//// clear the user's session_salt
							//$user = new Psychostats_user;
							//if ($user->load($id)) {
							//	$user->save(array(
							//		'session_salt' => null
							//	));
							//}
							//unset($user);
						}
					}
				}
			}
		}

		// Most pages will have smarty templates except for certain
		// output like images or flash.
		if (!defined('PS_NO_SMARTY')) {
			$this->load->library('psychosmarty', null, 'smarty');
			if (isset($this->ps_conf)) {
				// assign the PS config to all smarty templates
				$this->smarty->assign_by_ref('config', $this->ps_conf);
			}

			// make sure the compile_dir is valid and create it if needed
			if (!$this->smarty->verify_compile_dir()) {
				if (!$this->smarty->create_compile_dir()) {
					trigger_error(
						'Compile directory "<strong>' .
						$this->smarty->compile_dir .
						'</strong>" ' . 
						'does not exist or is not writable by the webserver. ' .
						'<br />' .
						'I tried to create the directory but failed.' . 
						'<br />' .
						'You must create and fix permissions on that directory ' .
						'or change the "<strong>compile_dir</strong>" ' .
						'setting in your config.php to point to another directory ' .
						'that will work.',
						E_USER_ERROR
					);
					// no sense in continuing ... 
					exit;
				}
			}

			$config =& get_config();
			// assign some common variables to all themes.
			$this->smarty->assign(array(
				'site_name'	=> $config['site_name'],
				'site_url' 	=> site_url(),
				'base_url'	=> base_url(),
				'page_url'	=> page_url(),
				'current_url'	=> current_url(),
				'SELF'		=> current_url(),
				'uri_string'	=> uri_string(),
				'index_page'	=> index_page(),
				'user'		=> &$this->ps_user,
			));
		}
		
		// Most pages have some sort of "Pager" so we create a default
		// pager here so we can set the defaults that all pages use.
		if (!defined('PS_NO_PAGER')) {
			$this->load->library('psychopager', null, 'pager');
			$this->pager->initialize(array(
				'next'			=> trans('Next'),
				'prev'			=> trans('Prev'),
				'first'			=> trans('First'),
				'last'			=> trans('Last'),
				'separator' 		=> '',
				'middle_separator' 	=> ' ... ',
				'per_page'		=> 100,
				'per_group'		=> 5
			));
		}
	}

	/**
	 * Generic callback for Psychotable() header sorting. Should work for
	 * most pages that have sorting tables.
	 */
	function _sort_header_callback($name, $col, $th, $table) {
		$ss  = $table->names['sort'];
		$so  = $table->names['order'];
		$sst = $table->names['start'];
		$sl  = $table->names['limit'];
		$order = array_key_exists($so,  $this->get) ? $this->get[$so] : 'asc';
		$start = array_key_exists($sst, $this->get) ? $this->get[$sst] : 0;
		$limit = array_key_exists($sl,  $this->get) ? $this->get[$sl] : 0;
		$params = array(
			$ss	=> $name,
			$so	=> ($order == 'asc') ? 'desc' : 'asc',
			$sst	=> $start,
			$sl 	=> $limit,
		);

		$label = $col['label'];
		if (isset($col['attr']['header']['tooltip'])) {
			$label = sprintf('<acronym title="%s">%s</acronym>',
				$col['attr']['header']['tooltip'],
				$col['label']
			);
		}

		if (isset($col['attr']['header']['nosort'])) {
			return sprintf('<p><a><span>%s</span></a></p>', $label);
		} else {
			// SEO: rel="nofollow" is used on header links to help avoid
			// search engines from ranking the sorted links (which
			// would artificially help increase your pageRank, etc).
			// Each search engine uses the 'rel' tag differently.
			if ($name == $table->sort) {
				$query = build_query_string($params, $this->get_defaults);
				if (isset($this->base_url)) {
					$query = $this->base_url . $query;
				}
				return sprintf('<p><a href="%s"%s><span class="%s">%s</span></a></p>',
					       page_url($query),
					       $query ? ' rel="nofollow"' : '',
					       $order,
					       $label);
			} else {
				$params[$so] = $order;
				$query = build_query_string($params, $this->get_defaults);
				if (isset($this->base_url)) {
					$query = $this->base_url . $query;
				}
				return sprintf('<p><a href="%s"%s><span>%s</span></a></p>',
					       page_url($query),
					       $query ? ' rel="nofollow"' : '',
					       $label);
			}
		}
	}
}

?>