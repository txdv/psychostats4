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
		$config = get_config();
		$page = $config['default_view'];
		if ($page != 'home') {
			redirect( site_url($page) );
		}

		// Process home page.
		$this->load->library('psychotable');
		$this->load->helper('get');
		$config =& get_config();

		$this->get_defaults = array(
			'gametype'	=> $config['default_gametype'],
			'modtype'	=> $config['default_modtype'],
			'sort'		=> 'rank',
			'order'		=> 'asc',
			'limit' 	=> 100,
			'start'		=> 0,
		);
		$this->get = get_url_params($this->get_defaults);

		// determine the total players available
		$total_players = $this->ps->get_total_players(array(
			'gametype' => $this->get['gametype'],
			'modtype' => $this->get['modtype']
		));
		$total_ranked  = $this->ps->get_total_players(array(
			'gametype' => $this->get['gametype'],
			'modtype' => $this->get['modtype'],
			'rank IS NOT NULL AND rank <> 0' => null
		));

		$select =
			// basic player information
			'plr.plrid, plr.skill, plr.skill_prev, ' .
			'plr.rank, plr.rank_prev, ' .
			'name, icon, cc,' .
			'kills'
			;
		$criteria = array(
			'select' => $select,
			'limit' => 5,
			'start' => 0,
			'order'	=> 'rank asc, kills desc',
		);
		$players = $this->ps->get_players($criteria, $this->get['gametype'], $this->get['modtype']);

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
			->column('name',		trans('Player'), 	array($this, '_cb_plr_name_no_wrap'))
			->column('kills',		trans('Kills'), 	'number_format')
			->column('skill',		trans('Skill'), 	array($this, '_cb_plr_skill'))
			->data_attr('rank', 'class', 'rank')
			->data_attr('name', 'class', 'link')
			->data_attr('skill', 'class', 'skill')
			;
		$this->ps->mod_table($players_table, 'top5players', $this->get['gametype'], $this->get['modtype']);

		$data = array(
			'total_players'	=> $total_players,
			'total_ranked'	=> $total_ranked,
			'players_table'	=> $players_table->render(),
		);

		
		define('PAGE', strtolower(get_class()));
		define('TEMPLATE', PAGE);
		
		$this->load->view('full_page', array('params' => $data));
	}
}

?>