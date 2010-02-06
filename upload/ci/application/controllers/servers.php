<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Servers extends MY_Controller {

	function Servers()
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
		
		// determine the total servers available
		$total_servers = $this->ps->get_total_servers();
		
		// determine the total players available
		//$total_players = $this->ps->get_total_players();

		$criteria = array(
			'limit' => $this->get['limit'],
			'start' => $this->get['start'],
			'sort'	=> $this->get['sort'],
			'order'	=> $this->get['order'],
		);
		$servers = $this->ps->get_servers($criteria);

		$table = $this->psychotable->create()
			->set_data($servers)
			->set_sort($this->get['sort'], $this->get['order'], array($this, '_sort_header_callback'))
			->column('img',			false,		 	array($this, '_cb_role_img'))
			->column('name',		trans('Name'),		array($this, '_cb_name_link'))
			->column('kills',		trans('Kills'), 	'number_format')
			->data_attr('name', 'class', 'name')
			->data_attr('img', 'class', 'img')
			->header_attr('img', 'nosort', true)
			->header_attr('name', 'colspan', 2)
			;
		$this->ps->mod_table($table, 'servers', $this->get['gametype'], $this->get['modtype']);

		// define pager
		$this->pager->initialize(array(
			'base_url'	=> page_url(build_query_string($this->get, $this->get_defaults, 'start')),
			'total' 	=> $total_servers,
			'start'		=> $this->get['start'],
		));
		$pager = $this->pager->create_links();

		$page_subtitle = trans('<strong>%s</strong> servers are available', number_format($total_servers));
		$data = array(
			'title'		=> trans('Servers'),
			'page_title' 	=> trans('Servers'),
			'page_subtitle' => $page_subtitle,
			'servers' 	=> &$servers,
			'table'		=> $table->render(),
			'total_servers' => $total_servers,
			'pager'		=> $pager,
		);

		define('PAGE', strtolower(get_class()));
		define('TEMPLATE', PAGE);
		
		$this->load->view('full_page', array('params' => $data));
	}

}

?>