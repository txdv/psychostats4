<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Players extends MY_Controller {

	function Players()
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
			'sort'		=> 'rank',
			'order'		=> 'asc',
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


		// non game specific stats		
		$stats = array(
			// basic player information
			'plr.plrid, plr.uniqueid, plr.activity, ' . 
			'plr.skill, plr.skill_prev, plr.rank, plr.rank_prev, ' .
			'plr.clanid, name, icon, cc',

			// static stats
			'kills, deaths, headshot_kills, online_time',

			// calculated stats
			'ROUND(IFNULL(kills / deaths, 0), 2) AS kills_per_death',
			'ROUND(IFNULL(kills / (online_time/60), 0), 2) AS kills_per_minute', 
			'ROUND(IFNULL(headshot_kills / kills * 100, 0), 0) AS headshot_kills_pct',
		);

		// allow game::mod specific stats to be added
		if ($meth = $this->ps->load_overloaded_method('get_players_sql', $this->get['gametype'], $this->get['modtype'])) {
			$meth->execute($stats);
		}
		
		$criteria = array(
			'select' => implode(',', $stats),
			'limit' => $this->get['limit'],
			'start' => $this->get['start'],
			'order'	=> $this->get['sort'] . ' ' . $this->get['order'] . ', kills desc',
		);
		$players = $this->ps->get_players($criteria, $this->get['gametype'], $this->get['modtype']);

		$table = $this->psychotable->create()
			->set_template('table_open', '<table class="neat">')
			->set_sort($this->get['sort'], $this->get['order'], array($this, '_sort_header_callback'))
			->column('rank', 		trans('Rank'), 		array($this, '_cb_plr_rank'))
			->column('name',		trans('Player'), 	array($this, '_cb_plr_name'))
			->column('kills',		trans('Kills'), 	'number_format')
			->column('deaths',		trans('Deaths'), 	'number_format')
			->column('kills_per_death',	trans('KpD'), 		'')
			->column('headshot_kills', 	trans('HS'),	 	'number_format')
			->column('headshot_kills_pct', 	trans('HS%'),	 	array($this, '_cb_plr_hs'))
			->column('online_time',		trans('Online'), 	'compact_time')
			->column('kills_per_minute',	trans('KpM'), 		'')
			->column('activity',		trans('Activity'), 	array($this, '_cb_activity_bar'))
			->column('skill',		trans('Skill'), 	array($this, '_cb_plr_skill'))
			->data_attr('rank', 'class', 'rank')
			->data_attr('name', 'class', 'link')
			->data_attr('skill', 'class', 'skill')
			->header_attr('kills_per_death', 	array( 'tooltip' => trans('Kills per Death') ))
			->header_attr('kills_per_minute', 	array( 'tooltip' => trans('Kills per Minute') ))
			->header_attr('headshot_kills', 	array( 'tooltip' => trans('Headshot Kills') ))
			->header_attr('headshot_kills_pct', 	array( 'tooltip' => trans('Headshot Kills Percentage') ))
			;
		$this->ps->mod_table($table, 'players', $this->get['gametype'], $this->get['modtype']);

		// define pager
		$this->pager->initialize(array(
			'base_url'	=> page_url(build_query_string($this->get, $this->get_defaults, 'start')),
			'total' 	=> $total_ranked,
			'start'		=> $this->get['start'],
		));
		$pager = $this->pager->create_links();
		
		$data = array(
			'title'		=> trans('Player Statistics'),
			'page_title' 	=> trans('Player Statistics'),
			'page_subtitle' => trans('<strong>%s</strong> players out of <strong>%s</strong> total are ranked <small>(%0.0f%%)</small>',
						 number_format($total_ranked),
						 number_format($total_players),
						 $total_ranked / $total_players * 100),
			'players' 	=> &$players,
			'table'		=> $table->render($players),
			'total_players' => $total_players,
			'total_ranked' 	=> $total_ranked,
			'pager'		=> $pager,
		);

		define('PAGE', strtolower(get_class()));
		define('TEMPLATE', PAGE);
		
		$this->load->view('full_page', array('params' => $data));
	}

	function _cb_plr_hs($name, $val, $plr, $td, $table) {
		return $val . '<small>%</small>';
	}


	function _cb_activity_bar($name, $val, $plr, $td, $table) {
		return pct_bar($val);
	}
}

?>