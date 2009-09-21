<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Roles extends MY_Controller {

	function Roles()
	{
		parent::MY_Controller();
	}
	
	function index()
	{
		$this->load->library('psychotable');
		$this->load->helper('get');
		$config =& get_config();
		
		// assign ourself to the PS object so overloaded methods
		// within PS will be able to use callbacks in the MY_Controller
		// object.
		$this->ps->controller = $this;

		$this->get_defaults = array(
			'gametype'	=> $config['default_gametype'],
			'modtype'	=> $config['default_modtype'],
			'sort'		=> 'kills',
			'order'		=> 'desc',
			'limit' 	=> 100,
			'start'		=> 0,
		);
		$this->get = get_url_params($this->get_defaults);

		// make sure a valid gametype is specified
		if (!array_key_exists($this->get['gametype'], $config['gametypes'])) {
			show_error("Game ({$this->get['gametype']}) not found!");
		}

		// make sure the modtype is valid for this gametype
		if (!in_array($this->get['modtype'], $config['gametypes'][$this->get['gametype']])) {
			show_error("Game::Mod ($this->get['gametype']::{$this->get['modtype']}) not found!");
		}

		// set the default game/mod
		$this->ps->set_gametype($this->get['gametype'], $this->get['modtype']);
		
		// determine the total roles available
		$total_roles = $this->ps->get_total_roles();
		
		// determine the total players available
		$total_players = $this->ps->get_total_players();

		$criteria = array(
			'limit' => $this->get['limit'],
			'start' => $this->get['start'],
			'sort'	=> $this->get['sort'],
			'order'	=> $this->get['order'],
		);
		$roles = $this->ps->get_roles($criteria);

		$table = $this->psychotable->create()
			->set_data($roles)
			->set_sort($this->get['sort'], $this->get['order'], array($this, '_sort_header_callback'))
			->column('img',			false,		 	array($this, '_cb_role_img'))
			->column('name',		trans('Role'),		array($this, '_cb_name_link'))
			->column('kills_scaled_pct',	trans('Kill%'), 	array($this, '_cb_kills_pct'))
			->column('kills',		trans('Kills'), 	'number_format')
			->column('deaths',		trans('Deaths'), 	'number_format')
			->column('kills_per_death',	trans('KpD'),	 	'')
			->column('headshot_kills', 	trans('HS'),	 	'number_format')
			->column('headshot_kills_pct', 	trans('HS%'),	 	array($this, '_cb_pct'))
			//->column('accuracy', 		trans('Accuracy'), 	array($this, '_cb_pct2'))
			//->column('Damage',		trans('Damage'), 	array($this, '_cb_abbrnum'))
			->data_attr('name', 'class', 'name')
			->data_attr('img', 'class', 'img')
			->header_attr('img', 'nosort', true)
			->header_attr('name', 'colspan', 2)
			->header_attr('kills_scaled_pct', 	array( 'tooltip' => trans('Kill Percentage') ))
			->header_attr('kills_per_death', 	array( 'tooltip' => trans('Kills per Death') ))
			->header_attr('headshot_kills', 	array( 'tooltip' => trans('Headshot Kills') ))
			->header_attr('headshot_kills_pct', 	array( 'tooltip' => trans('Headshot Kills Percentage') ))
			;
		$this->ps->mod_table($table, 'roles', $this->get['gametype'], $this->get['modtype']);

		// define pager
		$this->pager->initialize(array(
			'base_url'	=> page_url(build_query_string($this->get, $this->get_defaults, 'start')),
			'total' 	=> $total_roles,
			'start'		=> $this->get['start'],
		));
		$pager = $this->pager->create_links();

		$page_subtitle = trans('<strong>%s</strong> roles have been played by <strong>%s</strong> players',
			number_format($total_roles),
			number_format($total_players)
		);
		$data = array(
			'title'		=> trans('Role Statistics'),
			'page_title' 	=> trans('Role Statistics'),
			'page_subtitle' => $page_subtitle,
			'roles' 	=> &$roles,
			'table'		=> $table->render(),
			'total_roles' => $total_roles,
			'total_players' => $total_players,
			'pager'		=> $pager,
		);

		define('PAGE', strtolower(get_class()));
		define('TEMPLATE', PAGE);
		
		$this->load->view('full_page', array('params' => $data));
	}

}

?>