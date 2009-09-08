<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Clans extends MY_Controller {

	function Clans()
	{
		parent::MY_Controller();
	}
	
	function index()
	{
		$min_members = 2;
		
		$this->load->library('psychotable');
		$this->load->helper('get');
		$config =& get_config();

		$this->get_defaults = array(
			'gametype'	=> $config['default_gametype'],
			'modtype'	=> $config['default_modtype'],
			'sort'		=> 'total_members',
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
		
		// determine the total players available
		$total_clans = $this->ps->get_total_clans(array('min_members' => $min_members));
		$total_ranked  = $this->ps->get_total_clans(array('min_members' => $min_members, 'ranked_only' => true));

		$criteria = array(
			//'select'=> $stats,
			'limit' => $this->get['limit'],
			'start' => $this->get['start'],
			'sort'	=> $this->get['sort'],
			'order'	=> $this->get['order'],
			'is_ranked' => true,
			'is_plr_ranked' => true,
			'min_members' => $min_members,
			'where' => null
		);
		$clans = $this->ps->get_clans($criteria);
		
		$table = $this->psychotable->create()
			->set_template('table_open', '<table class="neat">')
			->set_sort($this->get['sort'], $this->get['order'], array($this, '_sort_header_callback'))
			->column('+',			'#', 			$this->get['start'])
			->column('clantag',		trans('Clan Tag'),	array($this, '_cb_name_link'))
			->column('name',		trans('Clan Name'),	array($this, '_cb_clan_link'))
			->column('total_members',	trans('Members'), 	'number_format')
			->column('kills',		trans('Kills'), 	'number_format')
			->column('deaths',		trans('Deaths'), 	'number_format')
			->column('kills_per_death',	trans('KpD'), 		'')
			->column('headshot_kills', 	trans('HS'),	 	'number_format')
			->column('headshot_kills_pct', 	trans('HS%'),	 	array($this, '_cb_pct'))
			//->column('activity',		trans('Activity'), 	array($this, '_cb_pct_bar'))
			->column('skill',		trans('Skill'), 	'')
			->data_attr('clantag', 'class', 'link')
			->data_attr('name', 'class', 'link')
			->data_attr('skill', 'class', 'skill')
			//->header_attr('+', array( 'nosort' => true ))
			->header_attr('kills_per_death', 	array( 'tooltip' => trans('Kills per Death') ))
			->header_attr('headshot_kills', 	array( 'tooltip' => trans('Headshot Kills') ))
			->header_attr('headshot_kills_pct', 	array( 'tooltip' => trans('Headshot Kills Percentage') ))
			;
		$this->ps->mod_table($table, 'clans', $this->get['gametype'], $this->get['modtype']);

		// define pager
		$this->pager->initialize(array(
			'base_url'	=> page_url(build_query_string($this->get, $this->get_defaults, 'start')),
			'total' 	=> $total_ranked,
			'start'		=> $this->get['start'],
		));
		$pager = $this->pager->create_links();
		
		$data = array(
			'title'		=> trans('Clan Statistics'),
			'page_title' 	=> trans('Clan Statistics'),
			'page_subtitle' => trans('<strong>%s</strong> clans out of <strong>%s</strong> total are ranked <small>(%0.0f%%)</small>',
						 number_format($total_ranked),
						 number_format($total_clans),
						 $total_ranked / $total_clans * 100),
			'clans' 	=> &$clans,
			'table'		=> $table->render($clans),
			'total_clans' => $total_clans,
			'total_ranked' 	=> $total_ranked,
			'pager'		=> $pager,
		);

		define('PAGE', strtolower(get_class()));
		define('TEMPLATE', PAGE);
		
		$this->load->view('full_page', array('params' => $data));
	}
}

?>