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
			//'q'		=> '',
			//'search'	=> '',
		);
		$this->get = get_url_params($this->get_defaults);
		
		// PERFORM SEARCH; IF REQUESTED
		$search = array();
		if ($this->get['q']) {
			// start a new search
			$search = $this->ps->search_players($this->get['q']);
		} elseif ($this->get['search']) {
			// use previous search
			$search = $this->ps->get_search($this->get['search']);
			$this->ps->touch_search($search['search_id']);
		}
		if ($search) {
			// set GET variable so the search_id is added to the
			// pager, etc...
			$this->get['search'] = $search['search_id'];
		}

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
		$total_players = $this->ps->get_total_players();
		$total_ranked  = $this->ps->get_total_players(array('is_ranked' => true));

		// determine the total players based on the search criteria
		$total_players_matched = $total_players;
		$total_ranked_matched  = $total_ranked;
		if ($search) {
			$total_players_matched = $this->ps->get_total_players(array('search' => $search));
			$total_ranked_matched  = $this->ps->get_total_players(array('search' => $search, 'is_ranked' => true));

			// if there is only 1 RANKED player match then redirect
			// to that plr page.
			if ($total_ranked_matched == 1) {
				redirect(ps_page_name('plr') . '/' . $search['results'][0]);
				exit;
			}

		}

		$criteria = array(
			//'select'=> $stats,
			'limit' => $this->get['limit'],
			'start' => $this->get['start'],
			'sort'	=> $this->get['sort'] . ($this->get['sort'] != 'kills' ? ', kills desc' : ''),
			'order'	=> $this->get['order'],
			'is_ranked' => true,
			'where' => null,
			'search' => $search
		);
		$players = $this->ps->get_players($criteria);
		
		$table = $this->psychotable->create()
			->set_sort($this->get['sort'], $this->get['order'], array($this, '_sort_header_callback'))
			->column('rank', 		trans('Rank'), 		array($this, '_cb_plr_rank'))
			->column('name',		trans('Player'), 	array($this, '_cb_name_link'))
			->column('kills',		trans('Kills'), 	'number_format')
			->column('deaths',		trans('Deaths'), 	'number_format')
			->column('kills_per_death',	trans('KpD'), 		'')
			->column('headshot_kills', 	trans('HS'),	 	'number_format')
			->column('headshot_kills_pct', 	trans('HS%'),	 	array($this, '_cb_pct'))
			->column('online_time',		trans('Online'), 	'compact_time')
			->column('kills_per_minute',	trans('KpM'), 		'')
			->column('activity',		trans('Activity'), 	array($this, '_cb_pct_bar'))
			//->column('skill_history',	trans('SH'), 		'<span class="sparkline">%s</span>')
			->column('skill',		trans('Skill'), 	array($this, '_cb_plr_skill'))
			->data_attr('rank', 'class', 'rank')
			->data_attr('name', 'class', 'link')
			->data_attr('skill', 'class', 'skill')
			->header_attr('kills_per_death', 	array( 'tooltip' => trans('Kills per Death') ))
			->header_attr('kills_per_minute', 	array( 'tooltip' => trans('Kills per Minute') ))
			->header_attr('headshot_kills', 	array( 'tooltip' => trans('Headshot Kills') ))
			->header_attr('headshot_kills_pct', 	array( 'tooltip' => trans('Headshot Kills Percentage') ))
			//->header_attr('skill_history', 		array( 'tooltip' => trans('Skill History') ))
		;
		$this->ps->mod_table($table, 'players', $this->get['gametype'], $this->get['modtype']);

		// define pager
		$this->pager->initialize(array(
			'base_url'	=> page_url(build_query_string($this->get, $this->get_defaults, array('start','q'))),
			'total' 	=> $total_ranked_matched,
			'start'		=> $this->get['start'],
		));
		$pager = $this->pager->create_links();
		
		$page_subtitle = trans('<strong>%s</strong> players out of <strong>%s</strong> are ranked <small>(%0.0f%%)</small>.',
			number_format($total_ranked),
			number_format($total_players),
			$total_ranked / $total_players * 100
		);
		if ($search) {
			$page_subtitle .= "\n" . trans('Your search "<strong>%s</strong>" matched <strong>%s</strong> ranked players out of <strong>%s</strong>.',
				htmlentities($search['phrase'], ENT_NOQUOTES, 'UTF-8'),
				number_format($total_ranked_matched),
				number_format($total_players_matched)
			);
			$page_subtitle .= "\n" . '[<a href="' . rel_site_url(strtolower(get_class())) . '">' . trans('reset') . '</a>]';
		}
		
		$data = array(
			'title'		=> trans('Player Statistics'),
			'page_title' 	=> trans('Player Statistics'),
			'page_subtitle' => $page_subtitle,
			'players' 	=> &$players,
			'table'		=> $table->render($players),
			'total_players' => $total_players,
			'total_ranked' 	=> $total_ranked,
			'total_players_matched' => $total_players_matched,
			'total_ranked_matched' => $total_ranked_matched,
			'pager'		=> $pager,
			'results'	=> &$search,
			'search'	=> $search ? $search['search_id'] : '',
			'q'		=> $search ? $search['phrase'] : $this->get['q'],
		);

		define('PAGE', strtolower(get_class()));
		define('TEMPLATE', PAGE);
		
		$this->load->view('full_page', array('params' => $data));
	}
}

?>