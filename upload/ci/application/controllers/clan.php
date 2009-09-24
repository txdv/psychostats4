<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Clan extends MY_Controller {
	protected $clan_stats;
	protected $clan_weapons;
	protected $clan_weapons_pager;
	protected $clan_weapons_table;
	protected $clan_weapons_total;
	protected $clan_weapons_chart;
	protected $clan_maps;
	protected $clan_maps_pager;
	protected $clan_maps_table;
	protected $clan_maps_total;
	protected $clan_maps_chart;
	protected $clan_map_wins_chart;
	protected $clan_roles;
	protected $clan_roles_pager;
	protected $clan_roles_table;
	protected $clan_roles_total;
	protected $clan_roles_chart;
	//protected $clan_victims;
	//protected $clan_victims_pager;
	//protected $clan_victims_table;
	//protected $clan_victims_total;
	//protected $clan_victims_chart;
	
	protected $base_url;
	protected $get_defaults;
	protected $get;
	protected $clan;
	protected $default_table;
	protected $default_pager;
	protected $blocks;
	protected $controller;
	
	function Clan()
	{
		parent::MY_Controller();
	}
	
	function view($id = null)
	{
		list($id) = parent::view(func_get_args());
		
		$this->per_page_limit = 10;
		$min_members = 2;

		$this->load->library('charts');
		$this->load->library('psychotable');
		$this->load->helper('get');
		//$config =& get_config();

		// assign ourself to the PS object so overloaded methods
		// within PS will be able to use callbacks in the MY_Controller
		// object.
		$this->ps->controller = $this;

		$this->base_url = "$id/"; 	// used by build_query_string()
		$this->get_defaults = array(
			'ps'	=> 'session_start',	// sort
			'ws'	=> 'kills',
			'ms'	=> 'kills',
			'rs'	=> 'kills',
			'ps'	=> 'kills',
			'us'	=> 'kills',
			'so'	=> 'desc',		// order
			'wo'	=> 'desc',
			'mo'	=> 'desc',
			'ro'	=> 'desc',
			'po'	=> 'desc',
			'uo'	=> 'desc',
			'sst'	=> 0,			// start
			'wst'	=> 0,
			'mst'	=> 0,
			'rst'	=> 0,
			'pst'	=> 0,
			'ust'	=> 0,
			'js'	=> '',			// ajax call
			'v'	=> '',			// view
		);
		$this->get = get_url_params($this->get_defaults, 1);
		//echo build_query_string($this->get, $this->get_defaults);
		
		$ajax = $this->get['js'];

		// Load the clan record ... If it doesn't exist then silently
		// redirect to the clans list ...
		if ($id and is_numeric($id)) {
			$this->clan = $this->ps->get_clan($id);
		}
		if (!$this->clan) {
			if ($ajax) {
				echo 'Bad request.';
				exit;
			} else{
				redirect_previous('clans');
			}
		}

		// set the default game/mod so we don't have to pass it around
		// to every clan function below...
		$this->ps->set_gametype($this->clan['gametype'], $this->clan['modtype']);

		// define a base table that all other tables will inherit from
		$this->default_table = $this->psychotable->create();

		// define a base pager that all other pagers will inherit from
		$this->default_pager = $this->pager->create(array(
			'per_page' => $this->per_page_limit,
			'force_prev_next' => true,
			'base_url' => page_url($this->base_url .
					       build_query_string($this->get,
								  $this->get_defaults,
								  array('js'))),
		));
		
		// basic clan stats ...		
		if (!$ajax) {
			$this->clan_stats = $this->ps->get_clan_stats($id);
			//$this->_load_skill_chart($id);
		}

		// players stats ...
		if (!$ajax or $ajax == 'players') {
			$this->_load_clan_players($id);
		}
		
		// unranked players ...
		if (!$ajax or $ajax == 'unranked') {
			$this->_load_clan_unranked($id);
		}

		// weapons stats ...
		if (!$ajax or $ajax == 'weapons') {
			$this->_load_clan_weapons($id);
		}

		// maps stats ...
		if (!$ajax or $ajax == 'maps') {
			$this->_load_clan_maps($id);
		}

		// roles stats ...
		if (!$ajax or $ajax == 'roles') {
			$this->_load_clan_roles($id);
		}
		
		// victims stats ...
		//if (!$ajax or $ajax == 'victims') {
		//	$this->_load_clan_victims($id);
		//}

		// If an ajax call was made we only want to update the specified
		// table and return a JSON record.
		if (in_array($ajax, array('players','unranked','weapons','maps','roles','victims'))) {
			// soft reference variables
			$t = 'clan_' . $ajax . '_table';
			$p = 'clan_' . $ajax . '_pager';

			header("Content-Type: application/x-javascript", true);

			if ($this->$t) { // soft ref
				$json = array(
					'status' => true,
					'pager' => $this->$p ? $this->$p->render() : '',
					'table' => $this->$t->render()
				);
			} else {
				$json = array( 'status' => false );
			}

			echo json_encode($json);
			exit;
		} else {
			// a normal page display will include all data ...

			//$total_clans_ranked = $this->ps->get_total_clans(array('min_members' => $min_members, 'is_ranked' => true));
			$total_unranked = $this->ps->get_clan_total(
				array(
					'id' => $id,
					'is_ranked' => false,
					'where' => array('rank IS NULL')
				),
				'players'
			);

			$title = trans('Clan "%s"', $this->clan['clantag']);
			$page_subtitle = '';
			if ($total_unranked) {
				$total = $this->clan_stats['total_members'] + $total_unranked;
				$page_subtitle = trans('Clan has <strong>%s</strong> ranked and <strong>%s</strong> unranked members <small>(%0.0f%%)</small>',
					number_format($this->clan_stats['total_members']),
					number_format($total_unranked),
					$this->clan_stats['total_members'] / $total * 100
				);
			} else {
				$page_subtitle = trans('Clan has <strong>%s</strong> ranked members <small>(%0.0f%%)</small>',
					number_format($this->clan_stats['total_members']),
					100
				);
			}
			
			// load blocks of data for the side nav area
			$this->nav_blocks = array();
			$this->nav_blocks['clan_vitals'] = array(
				'title' => trans('Clan Vitals'),
				'rows' => array(
					'rank' => array(
						'row_class' => 'hdr',
						'label' => trans('Rank'),
						'value' => $this->clan_stats['rank'] . ' ' . rank_change($this->clan_stats),
					),
					'rank_prev' => array(
						'row_class' => 'sub',
						'label' => trans('Previous'),
						'value' => $this->clan_stats['rank_prev'],
					),
					'skill' => array(
						'row_class' => 'hdr',
						'label' => trans('Skill'),
						//'value' => $this->clan_stats['skill'] . ' ' . skill_change($this->clan_stats),
						'value' => sprintf('<div class="pct-text%s">%s%s%%</div>',
								   $this->clan_stats['skill_change_pct'] >= 0.0 ? ' pct-text-up' : ' pct-text-down',
								   $this->clan_stats['skill_change_pct'] > 0.0 ? '+' : '', 
								   $this->clan_stats['skill_change_pct']
								   )
								. $this->clan_stats['skill'] . ' ' . skill_change($this->clan_stats),
					),
					'skill_prev' => array(
						'row_class' => 'sub',
						'label' => trans('Previous'),
						'value' => $this->clan_stats['skill_prev'],
					),
					//'activity' => array(
					//	'label' => trans('Activity'),
					//	'value' => sprintf('<div class="pct-stat">%s</div>%s%%', pct_bar($this->clan['activity']), $this->clan['activity']),
					//),
					'online_time' => array(
						'label' => trans('Online Time'),
						'value' => compact_time($this->clan_stats['online_time']),
					),
					'firstseen' => array(
						'label' => trans('First Seen'),
						'value' => strftime('%b %e, %Y @ %R', $this->clan['firstseen']),
					),
					//'lastseen' => array(
					//	'label' => trans('Last Seen'),
					//	'value' => strftime('%b %e, %Y @ %R', $this->clan['lastseen']),
					//),
					'games' => array(
						'label' => trans('Games'),
						'value' => number_format($this->clan_stats['games']),
					),
					'rounds' => array(
						'label' => trans('Rounds'),
						'value' => number_format($this->clan_stats['rounds']),
					),
				),
			);
			
			// allow game specific updates to the blocks ...
			// load method if available
			$method = $this->ps->load_overloaded_method('nav_blocks_clan', $this->clan['gametype'], $this->clan['modtype']);
			$nav_block_html = $this->smarty->render_blocks(
				$method, $this->nav_blocks, 
				array(&$this->clan, &$this->clan_stats)
			);

			$data = array(
				'title'		=> $title,
				'page_title' 	=> $title,
				'page_subtitle' => $page_subtitle,
				'plr'		=> &$this->clan,
				'stats'		=> &$this->clan_stats,
				'nav_block_html'=> &$nav_block_html,

				'players'	=> &$this->clan_players,
				'players_total'	=> $this->clan_players_total,
				'players_table'	=> $this->clan_players_table->render(),
				'players_pager'	=> $this->clan_players_pager->render(),

				'unranked'	=> &$this->clan_unranked,
				'unranked_total'=> $this->clan_unranked_total,
				'unranked_table'=> $this->clan_unranked_table->render(),
				'unranked_pager'=> $this->clan_unranked_pager->render(),

				'weapons'	=> &$this->clan_weapons,
				'weapons_total'	=> $this->clan_weapons_total,
				'weapons_table'	=> $this->clan_weapons_table->render(),
				'weapons_pager'	=> $this->clan_weapons_pager->render(),
				'weapons_chart'	=> $this->clan_weapons_chart,

				'maps'		=> &$this->clan_maps,
				'maps_total'	=> $this->clan_maps_total,
				'maps_table'	=> $this->clan_maps_table->render(),
				'maps_pager'	=> $this->clan_maps_pager->render(),
				'maps_chart'	=> $this->clan_maps_chart,
				'map_wins_chart'=> $this->clan_map_wins_chart,

				'roles'		=> &$this->clan_roles,
				'roles_total'	=> $this->clan_roles_total,
				'roles_table'	=> $this->clan_roles_table->render(),
				'roles_pager'	=> $this->clan_roles_pager->render(),
				'roles_chart'	=> $this->clan_roles_chart,

				//'victims'	=> &$this->clan_victims,
				//'victims_total'	=> $this->clan_victims_total,
				//'victims_table'	=> $this->clan_victims_table->render(),
				//'victims_pager'	=> $this->clan_victims_pager->render(),
				//'victims_chart'	=> $this->clan_victims_chart,
			);
		}

		define('BODY_LAYOUT', $this->smarty->page_layout('300left'));
		define('PAGE', strtolower(get_class()));
		define('TEMPLATE', PAGE);
		
		$this->load->view('full_page', array('params' => $data));
	}

	function _load_clan_weapons($id) {
		$this->clan_weapons = $this->ps->get_clan_weapons(array(
			'id' => $id,
			'sort'	=> $this->get['ws'],
			'order' => $this->get['wo'],
			'start' => $this->get['wst'],
			'limit'	=> $this->per_page_limit,
		));
		
		$this->clan_weapons_total = $this->ps->get_clan_total($id, 'weapons');
		
		$this->clan_weapons_table = $this->default_table->create()
			->set_data($this->clan_weapons)
			->set_sort($this->get['ws'], $this->get['wo'], array($this, '_sort_header_callback'))
			->set_names(array('sort' => 'ws', 'order' => 'wo', 'start' => 'wst'))
			->column('img',			trans('Weapon'), 	array($this, '_cb_weapon_img'))
			->column('name',		trans('Name'), 		array($this, '_cb_name_link'))
			->column('kills_scaled_pct',	trans('Kill%'), 	array($this, '_cb_kills_pct'))
			->column('kills',		trans('Kills'), 	'number_format')
			->column('deaths',		trans('Deaths'), 	'number_format')
			->column('headshot_kills', 	trans('HS'),	 	'number_format')
			//->column('headshot_kills_pct', 	trans('HS%'),	 	'cb:plr_hs')
			//->column('damage',		trans('Dmg'), 		'number_format')
			->data_attr('img', 'class', 'img')
			->data_attr('name', 'class', 'name')
			->header_attr('img', 'nosort', true)
			->header_attr('kills_scaled_pct', 	array( 'tooltip' => trans('Kill Percentage') ))
			->header_attr('headshot_kills', 	array( 'tooltip' => trans('Headshot Kills') ))
			//->header_attr('damage', 		array( 'tooltip' => trans('Damage') ))
			//->header_attr('headshot_kills_pct', 	array( 'tooltip' => trans('Headshot Kills Percentage') ))
			;

		$this->ps->mod_table($this->clan_weapons_table, 'clan_weapons',
				     $this->clan['gametype'], $this->clan['modtype']);
		
		$this->clan_weapons_pager = $this->default_pager->create(array(
			'total'	=> $this->clan_weapons_total,
			'start'	=> $this->get['wst'],
			'start_var' => 'wst',
			'urltail' => '#weapons'
		));


		// build a PIE chart for the top 10 clan weapons
		if (!$this->get['js']) {
			$by = 'kills';
			$by_trans = trans('kills');

			$list =& $this->clan_weapons;
			if ($this->get['ws'] != $by or
			    $this->get['wo'] != 'desc' or
			    $this->get['wst'] != 0) {
				// get a new list if our current sort isn't the
				// top 10 based on kills.
				$list = $this->ps->get_clan_weapons(array(
					'id' => $id,
					'sort'	=> $by,
					'order' => 'desc',
					'limit'	=> $this->per_page_limit,
				));
			}

			$params = array(
				'caption'		=> trans('Top %d Weapons (by %s)', count($list), $by_trans),
				'animation'		=> 0,
				'formatNumberScale'	=> 0,
				'decimalPrecision'	=> 0,
				'shownames'		=> 1,
				'showvalues'		=> 0,
				'bgColor'		=> 'F2F5F7',
			
			);
			$fc = $this->charts->create('pie2d', 500, 300, $params);

			$i = 0;
			foreach ($list as $d) {
				$settings = sprintf('name=%s;showName=1;isSliced=%d;link=%s',
					$d['name'],
					$i++==0 ? 1 : 0,
					ps_site_url('wpn', $d['name'])
				);
				$fc->addChartData($d[$by], $settings);
				if ($i>=$this->per_page_limit) break;
			}
			
			$this->clan_weapons_chart = $fc->renderChart(false,false);
		}
	}

	function _load_clan_maps($id) {
		$this->clan_maps = $this->ps->get_clan_maps(array(
			'id' => $id,
			'sort'	=> $this->get['ms'],
			'order' => $this->get['mo'],
			'start' => $this->get['mst'],
			'limit' => $this->per_page_limit,
		));

		$this->clan_maps_total = $this->ps->get_clan_total($id, 'maps');

		$this->clan_maps_table = $this->default_table->create()
			->set_data($this->clan_maps)
			->set_sort($this->get['ms'], $this->get['mo'], array($this, '_sort_header_callback'))
			->set_names(array('sort' => 'ms', 'order' => 'mo', 'start' => 'mst'))
			->column('img',			trans('Map'),	 	array($this, '_cb_map_img'))
			->column('name',		trans('Name'),	 	array($this, '_cb_name_link'))
			->column('kills_scaled_pct',	trans('Kill%'), 	array($this, '_cb_kills_pct'))
			->column('kills',		trans('Kills'), 	'number_format')
			->column('deaths',		trans('Deaths'), 	'number_format')
			->column('kills_per_death',	trans('KpD'),	 	'')
			//->column('kills_per_minute',	trans('KpM'),	 	'')
			->data_attr('img', 'class', 'img map')
			->data_attr('name', 'class', 'name')
			->header_attr('img', 'nosort', true)
			->header_attr('kills_scaled_pct', 	array( 'tooltip' => trans('Kill Percentage') ))
			->header_attr('kills_per_death', 	array( 'tooltip' => trans('Kills per Death') ))
			//->header_attr('kills_per_minute', 	array( 'tooltip' => trans('Kills per Minute') ))
			;

		$this->ps->mod_table($this->clan_maps_table, 'clan_maps',
				     $this->clan['gametype'], $this->clan['modtype']);

		$this->clan_maps_pager = $this->default_pager->create(array(
			'total'	=> $this->clan_maps_total,
			'start'	=> $this->get['mst'],
			'start_var' => 'mst',
			'urltail' => '#maps'
		));

		// build a PIE chart for the top 10 maps
		if (!$this->get['js']) {
			$by = 'kills';
			$by_trans = trans('kills');

			$list =& $this->clan_maps;
			if ($this->get['ms'] != $by or
			    $this->get['mo'] != 'desc' or
			    $this->get['mst'] != 0) {
				// get a new list if our current sort isn't the
				// top 10 based on kills.
				$list = $this->ps->get_clan_maps(array(
					'id' => $id,
					'sort'	=> $by,
					'order' => 'desc',
					'limit'	=> $this->per_page_limit,
				));
			}

			$params = array(
				'caption'		=> trans('Top %d Maps (by %s)', count($list), $by_trans),
				'animation'		=> 0,
				'formatNumberScale'	=> 0,
				'decimalPrecision'	=> 0,
				'shownames'		=> 1,
				'showvalues'		=> 0,
				'bgColor'		=> 'F2F5F7',
			
			);

			$fc = $this->charts->create('pie2d', 300, 200, $params);

			$i = 0;
			foreach ($list as $d) {
				if (!$d['kills']) continue;
				$settings = sprintf('name=%s;showName=1;isSliced=%d;link=%s',
					$d['name'],
					$i++==0 ? 1 : 0,
					ps_site_url('map', $d['name'])
				);
				$fc->addChartData($d['kills'], $settings);
				if ($i>=$this->per_page_limit) break;
			}
			
			$this->clan_maps_chart = $fc->renderChart(false,false);

			// create a column chart with the win ratio for the plr
			// but only if 'wins' is a column in the database.
			if (array_key_exists('wins', $this->clan_stats)) {
				$params = array(
					'caption'		=> trans('Win Ratio'),
					'yAxisMinValue'		=> 0,
					'yAxisMaxValue'		=> 100,
					'numberSuffix'		=> '%',
				) + $params;
				
				$fc = $this->charts->create('column3d', 300, 200, $params);

				$wins = $this->clan_stats['wins'];
				$losses = $this->clan_stats['losses'];
				
				$fc->addChartData($wins / ($wins + $losses) * 100,
						  sprintf("name=%s;hoverText=%s;color=00aa00",
							  trans('Wins'),
							  trans('%d wins', $wins)));
				$fc->addChartData($losses / ($wins + $losses) * 100,
						  sprintf("name=%s;hoverText=%s;color=aa0000",
							  trans('Losses'),
							  trans('%d losses', $losses)));

				$this->clan_map_wins_chart = $fc->renderChart(false,false);
			}
		}
		
	}

	function _load_clan_roles($id) {
		$this->clan_roles = $this->ps->get_clan_roles(array(
			'id' => $id,
			'sort'	=> $this->get['rs'],
			'order' => $this->get['ro'],
			'start' => $this->get['rst'],
			'limit'	=> $this->per_page_limit,
		));
		
		$this->clan_roles_total = $this->ps->get_clan_total($id, 'roles');
		
		$this->clan_roles_table = $this->default_table->create()
			->set_data($this->clan_roles)
			->set_sort($this->get['rs'], $this->get['ro'], array($this, '_sort_header_callback'))
			->set_names(array('sort' => 'rs', 'order' => 'ro', 'start' => 'rst'))
			->column('name',		trans('Role'),	 	array($this, '_cb_name_link'))
			->column('kills_scaled_pct',	trans('Kill%'), 	array($this, '_cb_kills_pct'))
			->column('kills',		trans('Kills'), 	'number_format')
			->column('deaths',		trans('Deaths'), 	'number_format')
			->column('kills_per_death',	trans('KpD'),	 	'')
			->column('headshot_kills', 	trans('HS'),	 	'number_format')
			//->column('headshot_kills_pct', 	trans('HS%'),	 	array($this, '_cb_pct'))
			->data_attr('name', 'class', 'name')
			->header_attr('kills_scaled_pct', 	array( 'tooltip' => trans('Kill Percentage') ))
			->header_attr('kills_per_death', 	array( 'tooltip' => trans('Kills per Death') ))
			->header_attr('headshot_kills', 	array( 'tooltip' => trans('Headshot Kills') ))
			//->header_attr('headshot_kills_pct', 	array( 'tooltip' => trans('Headshot Kills Percentage') ))
			;

		$this->ps->mod_table($this->clan_roles_table, 'clan_roles',
				     $this->clan['gametype'], $this->clan['modtype']);
		
		$this->clan_roles_pager = $this->default_pager->create(array(
			'total'	=> $this->clan_roles_total,
			'start'	=> $this->get['rst'],
			'start_var' => 'rst',
			'urltail' => '#roles'
		));


		// build a PIE chart for the top 10 clan roles
		if (!$this->get['js']) {
			$by = 'kills';
			$by_trans = trans('kills');

			$list =& $this->clan_roles;
			if ($this->get['rs'] != $by or
			    $this->get['ro'] != 'desc' or
			    $this->get['rst'] != 0) {
				// get a new list if our current sort isn't the
				// top 10 based on kills.
				$list = $this->ps->get_clan_roles(array(
					'id' => $id,
					'sort'	=> $by,
					'order' => 'desc',
					'limit'	=> $this->per_page_limit,
				));
			}

			$params = array(
				'caption'		=> trans('Top %d Roles (by %s)', count($list), $by_trans),
				'animation'		=> 0,
				'formatNumberScale'	=> 0,
				'decimalPrecision'	=> 0,
				'shownames'		=> 1,
				'showvalues'		=> 0,
				'bgColor'		=> 'F2F5F7',
			
			);
			$fc = $this->charts->create('pie2d', 500, 300, $params);

			$i = 0;
			foreach ($list as $d) {
				$settings = sprintf('name=%s;showName=1;isSliced=%d;link=%s',
					$d['name'],
					$i++==0 ? 1 : 0,
					ps_site_url('role', $d['name'])
				);
				$fc->addChartData($d[$by], $settings);
				if ($i>=$this->per_page_limit) break;
			}
			
			$this->clan_roles_chart = $fc->renderChart(false,false);
		}
	}

	function _load_clan_victims($id) {
		$this->clan_victims = $this->ps->get_clan_victims(array(
			'id' => $id,
			'sort'	=> $this->get['vs'],
			'order' => $this->get['vo'],
			'start' => $this->get['vst'],
			'limit' => $this->per_page_limit,
		));
		
		$this->clan_victims_total = $this->ps->get_clan_total($id, 'victims');
		
		$this->clan_victims_table = $this->default_table->create()
			->set_data($this->clan_victims)
			->set_sort($this->get['vs'], $this->get['vo'], array($this, '_sort_header_callback'))
			->set_names(array('sort' => 'vs', 'order' => 'vo', 'start' => 'vst'))
			->column('rank', 		trans('Rank'), 		array($this, '_cb_plr_rank'))
			->column('name',		trans('Victim'), 	array($this, '_cb_name_link'))
			->column('kills_scaled_pct',	trans('Kill%'), 	array($this, '_cb_kills_pct'))
			->column('kills',		trans('Kills'), 	'number_format')
			->column('deaths',		trans('Deaths'), 	'number_format')
			->column('kills_per_death',	trans('KpD'),	 	'')
			->column('headshot_kills', 	trans('HS'),	 	'number_format')
			->column('skill',		trans('Skill'), 	array($this, '_cb_plr_skill'))
			->data_attr('rank', 'class', 'rank')
			->data_attr('name', 'class', 'name')
			->data_attr('skill', 'class', 'skill')
			->header_attr('kills', 			array( 'tooltip' => trans('You killed them') ))
			->header_attr('deaths', 		array( 'tooltip' => trans('They killed you') ))
			->header_attr('kills_scaled_pct', 	array( 'tooltip' => trans('Kill Percentage') ))
			->header_attr('kills_per_death', 	array( 'tooltip' => trans('Kills per Death') ))
			->header_attr('headshot_kills', 	array( 'tooltip' => trans('Headshot Kills') ))
			;

		$this->ps->mod_table($this->clan_victims_table, 'clan_victims',
				     $this->clan['gametype'], $this->clan['modtype']);

		$this->clan_victims_pager = $this->default_pager->create(array(
			'total'	=> $this->clan_victims_total,
			'start'	=> $this->get['vst'],
			'start_var' => 'vst',
			'urltail' => '#victims'
		));

		// build a PIE chart for the top 10 victims
		if (!$this->get['js']) {
			$by = 'kills';
			$by_trans = trans('kills');

			$list =& $this->clan_victims;
			if ($this->get['vs'] != $by or
			    $this->get['vo'] != 'desc' or
			    $this->get['vst'] != 0) {
				// get a new list if our current sort isn't the
				// top 10 based on kills.
				$list = $this->ps->get_clan_victims(array(
					'id' => $id,
					'sort'	=> $by,
					'order' => 'desc',
					'limit'	=> $this->per_page_limit,
				));
			}
			
			$params = array(
				'caption'		=> trans('Top %d Victims (by %s)', count($list), $by_trans),
				'animation'		=> 0,
				'formatNumberScale'	=> 0,
				'decimalPrecision'	=> 0,
				'shownames'		=> 1,
				'showvalues'		=> 0,
				'bgColor'		=> 'F2F5F7',
			
			);

			$fc = $this->charts->create('pie2d', 500, 300, $params);

			$i = 0;
			foreach ($list as $d) {
				$settings = sprintf('name=%s;showName=1;isSliced=%d;link=%s',
					$d['name'],
					$i++==0 ? 1 : 0,
					ps_site_url('plr', $d['victimid'])
				);
				$fc->addChartData($d[$by], $settings);
				if ($i>=$this->per_page_limit) break;
			}
			
			$this->clan_victims_chart = $fc->renderChart(false,false);
		}
	}

	function _load_clan_players($id) {
		$this->clan_players = $this->ps->get_clan_players(array(
			'id' => $id,
			'sort'	=> $this->get['ps'],
			'order' => $this->get['po'],
			'start' => $this->get['pst'],
			'limit' => $this->per_page_limit,
		));

		$this->clan_players_total = $this->ps->get_clan_total($id, 'players');

		$this->clan_players_table = $this->default_table->create()
			->set_data($this->clan_players)
			->set_sort($this->get['ps'], $this->get['po'], array($this, '_sort_header_callback'))
			->set_names(array('sort' => 'ps', 'order' => 'po', 'start' => 'pst'))
			->column('rank', 		trans('Rank'), 		array($this, '_cb_plr_rank'))
			->column('name',		trans('Player'), 	array($this, '_cb_name_link'))
			->column('kills',		trans('Kills'), 	'number_format')
			->column('deaths',		trans('Deaths'), 	'number_format')
			->column('kills_per_death',	trans('KpD'),	 	'')
			->column('headshot_kills', 	trans('HS'),	 	'number_format')
			->column('activity',		trans('Activity'), 	array($this, '_cb_pct_bar'))
			->column('skill',		trans('Skill'), 	array($this, '_cb_plr_skill'))
			->data_attr('rank', 'class', 'rank')
			->data_attr('name', 'class', 'name')
			->data_attr('skill', 'class', 'skill')
			->header_attr('kills_per_death', 	array( 'tooltip' => trans('Kills per Death') ))
			->header_attr('headshot_kills', 	array( 'tooltip' => trans('Headshot Kills') ))
			;

		$this->ps->mod_table($this->clan_players_table, 'clan_players',
				     $this->clan['gametype'], $this->clan['modtype']);

		$this->clan_players_pager = $this->default_pager->create(array(
			'total'	=> $this->clan_players_total,
			'start'	=> $this->get['pst'],
			'start_var' => 'pst',
			'urltail' => '#players'
		));
	}

	function _load_clan_unranked($id) {
		$this->clan_unranked = $this->ps->get_clan_players(array(
			'id' => $id,
			'sort'	=> $this->get['us'],
			'order' => $this->get['uo'],
			'start' => $this->get['ust'],
			'limit' => $this->per_page_limit,
			'is_ranked' => false,			// this will return all members...
			'where' => array('rank IS NULL')	// this will only return UNRANKED players.
		));
		
		$this->clan_unranked_total = $this->ps->get_clan_total(
			array(
				'id' => $id,
				'is_ranked' => false,
				'where' => array('rank IS NULL')
			),
			'players'
		);

		$this->clan_unranked_table = $this->default_table->create()
			->set_data($this->clan_unranked)
			->set_sort($this->get['us'], $this->get['uo'], array($this, '_sort_header_callback'))
			->set_names(array('sort' => 'us', 'order' => 'uo', 'start' => 'ust'))
			->set_no_data(trans('No players found'))
			->column('rank', 		trans('Rank'), 		array($this, '_cb_plr_rank'))
			->column('name',		trans('Player'), 	array($this, '_cb_name_link'))
			->column('kills',		trans('Kills'), 	'number_format')
			->column('deaths',		trans('Deaths'), 	'number_format')
			->column('kills_per_death',	trans('KpD'),	 	'')
			->column('headshot_kills', 	trans('HS'),	 	'number_format')
			->column('activity',		trans('Activity'), 	array($this, '_cb_pct_bar'))
			->column('skill',		trans('Skill'), 	array($this, '_cb_plr_skill'))
			->data_attr('rank', 'class', 'rank')
			->data_attr('name', 'class', 'name')
			->data_attr('skill', 'class', 'skill')
			->header_attr('kills_per_death', 	array( 'tooltip' => trans('Kills per Death') ))
			->header_attr('headshot_kills', 	array( 'tooltip' => trans('Headshot Kills') ))
			;

			;

		$this->ps->mod_table($this->clan_unranked_table, 'clan_players',
				     $this->clan['gametype'], $this->clan['modtype']);

		$this->clan_unranked_pager = $this->default_pager->create(array(
			'total'	=> $this->clan_unranked_total,
			'start'	=> $this->get['ust'],
			'start_var' => 'ust',
			'urltail' => '#unranked'
		));
		
	}

}


?>