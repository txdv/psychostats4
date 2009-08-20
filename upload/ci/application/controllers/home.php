<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 *
 *	----------------------------------------------------------
 *	HOME PAGE STATS
 *	----------------------------------------------------------
 *
 *	Games configured.
 *	Servers configured.
 *	Random 5 awards?
 *	Top 5 ranked players
 *	Last 5 maps player
 *	Live streaming chatbox?
 *	Most used weapon (+piechart)
 *	Most active role (+piechart)
 *
 */
class Home extends MY_Controller {

	function Home()
	{
		parent::MY_Controller();
	}
	
	function index()
	{
		// load 'default_view' from config to determine which page
		// should be loaded first (portal, players, awards, etc)
		$config =& get_config();
		$page = $config['default_view'];
		if ($page != 'home') {
			redirect( site_url($page) );
		}

		// Process home page.
		$this->load->library('psychotable');
		$this->load->helper('get');

		$this->get_defaults = array(
			'gametype'	=> $config['default_gametype'],
			'modtype'	=> $config['default_modtype'],
			'sort'		=> 'rank',
			'order'		=> 'asc',
			'limit' 	=> 100,
			'start'		=> 0,
		);
		$this->get = get_url_params($this->get_defaults);

		// set the default game/mod
		$this->ps->set_gametype($this->get['gametype'], $this->get['modtype']);

		// determine the total players available
		$total_players = $this->ps->get_total_players();
		//$total_ranked  = $this->ps->get_total_players(array('is_ranked' => true));

		$select = array(
			'plr.plrid, plr.skill, plr.skill_prev', 
			'plr.rank, plr.rank_prev', 
			'pp.name, pp.cc',
			'kills'
		);
		$criteria = array(
			'select' => $select,
			'select_overload_method' => null,
			'limit' => 5,
			'start' => 0,
			'sort' => 'rank asc, kills desc', 
		);
		$players = $this->ps->get_players($criteria);

		$players_table = $this->psychotable->create()
			->set_template('table_open', '<table class="neat">')
			->set_caption(sprintf('<a href="%s">%s</a>',
					      rel_site_url('players'),
					      trans("Top %d players out of %s total",
						    count($players),
						    number_format($total_players))))
			->set_data($players)
			->set_sort('rank', 'asc')
			->column('rank', 		'#', 			array($this, '_cb_plr_rank'))
			->column('name',		trans('Player'), 	array($this, '_cb_name_link_no_wrap'))
			->column('kills',		trans('Kills'), 	'number_format')
			->column('skill',		trans('Skill'), 	array($this, '_cb_plr_skill'))
			->data_attr('rank', 'class', 'rank')
			->data_attr('name', 'class', 'link')
			->data_attr('skill', 'class', 'skill')
			;
		$this->ps->mod_table($players_table, 'players_top5', $this->get['gametype'], $this->get['modtype']);

		$data = array(
			'total_players' => $total_players,
			//'total_ranked' => $total_ranked,
			'players_table'	=> $players_table->render(),
		);

		
		define('PAGE', strtolower(get_class()));
		define('TEMPLATE', PAGE);
		
		$this->load->view('full_page', array('params' => $data));
	}
}

?>