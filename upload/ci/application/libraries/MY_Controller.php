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

	// allow any 'view' controller pages to fix their parameters.
	function view($args, $func = 'view') {
		if (is_array($args) && count($args) && $args[0] == $func) {
			array_shift($args);
		}
		return $args;
	}

	function _psychostats_init() {
		// enable sane error reporting for all pages.
		error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE);
		//error_reporting(E_ALL);
		
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
			$this->ps_conf = $this->ps->get_ps_config();

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
				$this->smarty->assignByRef('config', $this->ps_conf);
			}

			// make sure the compile_dir is valid and create it if needed
			if (!defined('PS_NO_SMARTY_VERIFY') and !$this->smarty->verify_compile_dir()) {
				if (!$this->smarty->create_compile_dir()) {
					trigger_error(
						'Compile directory "<strong>' .
						$this->smarty->compile_dir .
						'</strong>" ' . 
						'does not exist or is not writable by the webserver. ' .
						'<br /><br />' .
						'I tried to create the directory but failed.' . 
						'<br /><br />' .
						'You must create and fix permissions on that directory ' .
						'or change the "<strong>compile_dir</strong>" ' .
						'setting in your config to point to another directory.',
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
		$se  = $table->names['search'];
		$order  = array_key_exists($so,  $this->get) ? $this->get[$so] : 'asc';
		$start  = array_key_exists($sst, $this->get) ? $this->get[$sst] : 0;
		$limit  = array_key_exists($sl,  $this->get) ? $this->get[$sl] : 0;
		$search = array_key_exists($se,  $this->get) ? $this->get[$se] : '';
		$params = array(
			$ss	=> $name,
			$so	=> ($order == 'asc') ? 'desc' : 'asc',
			$sst	=> $start,
			$sl 	=> $limit,
		);

		if ($search) {
			$params[$se] = $search;
		}

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
	
	// COMMON callbacks for tables ...
	
	function _cb_datetime($name, $val, $data, $td, $table) {
		return date('Y-m-d H:i', $val);
	}
	
	function _cb_pct($name, $val, $plr, $td, $table) {
		return $val != '' ? $val . '<small>%</small>' : '-';
	}

	function _cb_pct0($name, $val, $plr, $td, $table) {
		return $val != '' ? ceil($val) . '<small>%</small>' : '-';
	}

	function _cb_pct2($name, $val, $plr, $td, $table) {
		return $val != '' ? abbrnum($val, 2, 1000) . '<small>%</small>' : '-';
	}

	function _cb_pct_bar($name, $val, $plr, $td, $table) {
		return pct_bar($val);
	}

	function _cb_abbrnum($name, $val, $plr, $td, $table) {
		return abbrnum($val, 2, 1000);
	}

	function _cb_plr_skill($name, $val, $plr, $td, $table) {
		return $val . ' ' . skill_change($plr);
	}

	function _cb_plr_rank($name, $val, $plr, $td, $table) {
		if ($val) {
			return rank_change($plr) . ' ' . $val;
		} else {
			$td->set_attr('class', 'no-rank', true);
			return '-';
		}
	}
	
	// modifies the value to be an <a> link to the record based on the type
	// of data being displayed in the table (plr, map, role, wpn).
	function _cb_name_link($name, $val, $data, $td, $table) {
		$page = '';
		$text = htmlentities($val, ENT_NOQUOTES, 'UTF-8');
		$path = $text;
		
		if (isset($data['mapid'])) {
			$page = 'map';
			//$path = $data['mapid'];
		} elseif (isset($data['roleid'])) {
			$page = 'role';
			$path = $data['roleid'];
		} elseif (isset($data['victimid'])) {
			$page = 'plr';
			$path = $data['victimid'];
		} elseif (isset($data['plrid'])) {
			$page = 'plr';
			$path = $data['plrid'];
		} elseif (isset($data['clanid'])) {
			$page = 'clan';
			$path = $data['clanid'];
		} elseif (isset($data['weaponid'])) {
			$page = 'wpn';
			$path = $data['weaponid'];
		} elseif (isset($data['srvid'])) {
			$page = 'srv';
			$path = $data['srvid'];
		}

		return sprintf('<a href="%s" title="%s">%s</a>',
			       ps_site_url($page, $path),
			       $text,
			       $text
		);
	}

	function _cb_clan_link($name, $val, $data, $td, $table) {
		return $this->_cb_name_link($name, $val == '' ? '-' : $val, $data, $td, $table);
	}

	// same as cb_plr_name except the name will not wrap in the table cell.
	function _cb_name_link_no_wrap($name, $val, $plr, $td, $table) {
		$link = $this->_cb_name_link($name, $val, $plr, $td, $table);
		$link = sprintf('<table class="inner"><tr><td>%s</td></tr></table>', $link);
		return $link;
	}

	function _cb_weapon_img($name, $val, $data, $td, $table) {
		$text = htmlentities($data['full_name'] ? $data['full_name'] : $data['name'], ENT_NOQUOTES, 'UTF-8');
		$img = img_url('weapons', $data['name'], $this->ps->gametype(), $this->ps->modtype());
		if ($img) {
			$img = sprintf("<img src='%s' alt='%s' title='%s'/>",
				$img, $text, $text
			);
			return sprintf('<a href="%s">%s</a>', ps_site_url('wpn', $data['name']), $img ? $img : $text);
		}
		return '';
	}

	function _cb_map_img($name, $val, $data, $td, $table) {
		$text = htmlentities($data['name'], ENT_NOQUOTES, 'UTF-8');
		$url = img_url('maps', $data['name'], $this->ps->gametype(), $this->ps->modtype());
		$img = false;
		if ($url) {
			$img = sprintf('<img class="map-img" src="%s" alt="%s" title="%s" />',
				$url, $text, $text
			);
		} else {
			return '';
		}
		return sprintf('<a href="%s">%s</a>', ps_site_url('map', $data['name']), $img ? $img : $text);
	}

	function _cb_kills_pct($name, $val, $data, $td, $table) {
		return pct_bar(array(
			'pct'	=> $val, // scaled percentage
			'title'	=> sprintf('%0.02f%%', $data['kills_pct'])
		));
	}

	function _cb_online_time_pct($name, $val, $data, $td, $table) {
		return pct_bar(array(
			'pct'	=> $val, // scaled percentage
			'title'	=> sprintf('%0.02f%%', $data['online_time_pct'])
		));
	}

}

?>