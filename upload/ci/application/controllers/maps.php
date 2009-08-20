<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Maps extends MY_Controller {

	function Maps()
	{
		parent::MY_Controller();
	}
	
	function index()
	{
		$this->load->library('psychotable');
		$this->load->helper('get');
		$config =& get_config();

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
		
		// determine the total maps available
		$total_maps = $this->ps->get_total_maps(array(
			'gametype' => $this->get['gametype'],
			'modtype' => $this->get['modtype']
		));
		// determine the total players available
		$total_players = $this->ps->get_total_players();
		//$total_ranked  = $this->ps->get_total_players(array('is_ranked' => true));

		$c_maps = $this->ps->tbl('c_map_data', $this->get['gametype'], $this->get['modtype']);

		// non game specific stats		
		$stats = array(
			// basic information
			'map.name',

			// static stats
			'd.*',

			// calculated stats
			"IFNULL(d.online_time / (SELECT MAX(online_time) FROM $c_maps) * 100, 0) online_time_scaled_pct",
			"IFNULL(d.online_time / (SELECT SUM(online_time) FROM $c_maps) * 100, 0) online_time_pct",
			"IFNULL(d.kills / (SELECT MAX(kills) FROM $c_maps) * 100, 0) kills_scaled_pct",
			"IFNULL(d.kills / (SELECT SUM(kills) FROM $c_maps) * 100, 0) kills_pct",
		);
		
		$criteria = array(
			'select'=> $stats,
			'limit' => $this->get['limit'],
			'start' => $this->get['start'],
			'sort'	=> $this->get['sort'],
			'order'	=> $this->get['order'],
			'where' => null
		);
		$maps = $this->ps->get_maps($criteria);

		$table = $this->psychotable->create()
			->set_data($maps)
			->set_template('table_open', '<table class="neat">')
			->set_sort($this->get['sort'], $this->get['order'], array($this, '_sort_header_callback'))
			->column('img',			false,		 	array($this, '_cb_map_img'))
			->column('name',		trans('Map'),		array($this, '_cb_name_link'))
			->column('kills_scaled_pct',	trans('Kill%'), 	array($this, '_cb_kills_pct'))
			->column('kills',		trans('Kills'), 	'number_format')
			->column('games',		trans('Games'), 	'number_format')
			->column('rounds',		trans('Rounds'), 	'number_format')
			->column('online_time',		trans('Online'), 	'compact_time')
			->column('online_time_scaled_pct',trans('Online%'), 	array($this, '_cb_online_time_pct'))
			//->column('lastseen',		trans('Last Played'), 	'')
			//->column('Damage',		trans('Damage'), 	array($this, '_cb_abbrnum'))
			->data_attr('name', 'class', 'name')
			->data_attr('img', 'class', 'img map')
			->header_attr('img', 'nosort', true)
			->header_attr('name', 'colspan', 2)
			->header_attr('kills_scaled_pct', 	array( 'tooltip' => trans('Kill Percentage') ))
			->header_attr('headshot_kills', 	array( 'tooltip' => trans('Headshot Kills') ))
			->header_attr('headshot_kills_pct', 	array( 'tooltip' => trans('Headshot Kills Percentage') ))
			;
		$this->ps->mod_table($table, 'maps', $this->get['gametype'], $this->get['modtype']);

		// define pager
		$this->pager->initialize(array(
			'base_url'	=> page_url(build_query_string($this->get, $this->get_defaults, 'start')),
			'per_page'	=> $this->get['limit'],
			'total' 	=> $total_maps,
			'start'		=> $this->get['start'],
		));
		$pager = $this->pager->create_links();
		
		$page_subtitle = trans('<strong>%s</strong> maps have been played by <strong>%s</strong> players',
			number_format($total_maps),
			number_format($total_players)
		);
		$data = array(
			'title'		=> trans('Map Statistics'),
			'page_title' 	=> trans('Map Statistics'),
			'page_subtitle' => $page_subtitle,
			'maps' 		=> &$maps,
			'table'		=> $table->render(),
			'total_maps' 	=> $total_maps,
			'total_players' => $total_players,
			'pager'		=> $pager,
		);

		define('PAGE', strtolower(get_class()));
		define('TEMPLATE', PAGE);
		
		$this->load->view('full_page', array('params' => $data));
	}

}

?>