<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Plr extends MY_Controller {
	protected $player_stats;
	protected $player_skill_chart;
	protected $player_sessions;
	protected $player_sessions_pager;
	protected $player_sessions_table;
	protected $player_sessions_total;
	protected $player_weapons;
	protected $player_weapons_pager;
	protected $player_weapons_table;
	protected $player_weapons_total;
	protected $player_weapons_chart;
	protected $player_maps;
	protected $player_maps_pager;
	protected $player_maps_table;
	protected $player_maps_total;
	protected $player_maps_chart;
	protected $player_map_wins_chart;
	protected $player_roles;
	protected $player_roles_pager;
	protected $player_roles_table;
	protected $player_roles_total;
	protected $player_roles_chart;
	protected $player_victims;
	protected $player_victims_pager;
	protected $player_victims_table;
	protected $player_victims_total;
	protected $player_victims_chart;
	
	protected $base_url;
	protected $get_defaults;
	protected $get;
	protected $plr;
	protected $default_table;
	protected $default_pager;
	
	function Plr()
	{
		parent::MY_Controller();
	}
	
	//function index() {	}
	
	function view($id = null)
	{
		$this->load->library('charts');
		$this->load->library('psychotable');
		$this->load->helper('get');
		//$config =& get_config();

		$this->base_url = "$id/"; 	// used by build_query_string()
		$this->get_defaults = array(
			'ss'	=> 'session_start',	// sort
			'ws'	=> 'kills',
			'ms'	=> 'kills',
			'rs'	=> 'kills',
			'vs'	=> 'kills',
			'so'	=> 'desc',		// order
			'wo'	=> 'desc',
			'mo'	=> 'desc',
			'ro'	=> 'desc',
			'vo'	=> 'desc',
			'sst'	=> 0,			// start
			'wst'	=> 0,
			'mst'	=> 0,
			'rst'	=> 0,
			'vst'	=> 0,
			'js'	=> '',			// ajax call
			'v'	=> '',			// view
		);
		$this->get = get_url_params($this->get_defaults, 1);
		//echo build_query_string($this->get, $this->get_defaults);
		
		$ajax = $this->get['js'];

		// Load the player record ... If it doesn't exist then silently
		// redirect to the players list ...
		if ($id and is_numeric($id)) {
			$this->plr = $this->ps->get_player($id);
		}
		if (!$this->plr) {
			if ($ajax) {
				echo 'Bad request.';
				exit;
			} else{
				redirect_previous('players');
			}
		}

		// set the default game/mod so we don't have to pass it around
		// to every player function below...
		$this->ps->set_gametype($this->plr['gametype'], $this->plr['modtype']);

		// define a base table that all other tables will inherit from
		$this->default_table = $this->psychotable->create()
			->set_template('table_open', '<table class="neat">')
			;

		// define a base pager that all other pagers will inherit from
		$this->default_pager = $this->pager->create(array(
			'per_page' => 10,
			'force_prev_next' => true,
			'base_url' => page_url($this->base_url .
					       build_query_string($this->get,
								  $this->get_defaults,
								  array('js'))),
		));
		
		// basic player stats ...		
		if (!$ajax) {
			$this->player_stats = $this->ps->get_player_stats($id);
			$this->_load_skill_chart($id);
		}

		// sessions stats ...
		if (!$ajax or $ajax == 'sessions') {
			$this->_load_player_sessions($id);
		}

		// weapons stats ...
		if (!$ajax or $ajax == 'weapons') {
			$this->_load_player_weapons($id);
		}

		// maps stats ...
		if (!$ajax or $ajax == 'maps') {
			$this->_load_player_maps($id);
		}

		// roles stats ...
		if (!$ajax or $ajax == 'roles') {
			$this->_load_player_roles($id);
		}
		
		// victims stats ...
		if (!$ajax or $ajax == 'victims') {
			$this->_load_player_victims($id);
		}

		// If an ajax call was made we only want to update the specified
		// table and return a JSON record.
		if (in_array($ajax, array('sessions','weapons','maps','roles','victims'))) {
			// soft reference variables
			$t = 'player_' . $ajax . '_table';
			$p = 'player_' . $ajax . '_pager';

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
			
			$total_ranked = $this->ps->get_total_players(array(
				'gametype' => $this->plr['gametype'],
				'modtype' => $this->plr['modtype'],
				'rank IS NOT NULL' => null
			));

			$title = trans('Player "%s"', $this->plr['name']);		
			if ($this->plr['rank']) {
				$page_subtitle = trans('Ranked <strong>%s</strong> of <strong>%s</strong> with a skill of <strong>%s</strong>',
					number_format($this->plr['rank']),
					number_format($total_ranked),
					$this->plr['skill']
				);
			} else {
				$page_subtitle = trans('This player is not ranked');
			}
			
			$data = array(
				'title'		=> $title,
				'page_title' 	=> $title,
				'page_subtitle' => $page_subtitle,
				'plr'		=> &$this->plr,
				'stats'		=> &$this->player_stats,

				'sessions'	=> &$this->player_sessions,
				'sessions_total'=> $this->player_sessions_total,
				'sessions_table'=> $this->player_sessions_table->render(),
				'sessions_pager'=> $this->player_sessions_pager->render(),

				'weapons'	=> &$this->player_weapons,
				'weapons_total'	=> $this->player_weapons_total,
				'weapons_table'	=> $this->player_weapons_table->render(),
				'weapons_pager'	=> $this->player_weapons_pager->render(),
				'weapons_chart'	=> $this->player_weapons_chart,

				'maps'		=> &$this->player_maps,
				'maps_total'	=> $this->player_maps_total,
				'maps_table'	=> $this->player_maps_table->render(),
				'maps_pager'	=> $this->player_maps_pager->render(),
				'maps_chart'	=> $this->player_maps_chart,
				'map_wins_chart'=> $this->player_map_wins_chart,

				'roles'		=> &$this->player_roles,
				'roles_total'	=> $this->player_roles_total,
				'roles_table'	=> $this->player_roles_table->render(),
				'roles_pager'	=> $this->player_roles_pager->render(),
				'roles_chart'	=> $this->player_roles_chart,

				'victims'	=> &$this->player_victims,
				'victims_total'	=> $this->player_victims_total,
				'victims_table'	=> $this->player_victims_table->render(),
				'victims_pager'	=> $this->player_victims_pager->render(),
				'victims_chart'	=> $this->player_victims_chart,

				'skill_chart'	=> $this->player_skill_chart,
			);
		}
		
		define('BODY_LAYOUT', $this->smarty->page_layout('300left'));
		define('PAGE', strtolower(get_class()));
		define('TEMPLATE', PAGE);
		
		$this->load->view('full_page', array('params' => $data));
	}

	function _load_skill_chart($id) {
		$params = array(
			'lineColor'		=> '4444cc',
			'showAnchors'		=> 1,
			'anchorAlpha'		=> 0,	// hide anhors but allow hover to work
			'anchorRadius'		=> 4,
			'showValues'		=> 0,
			'xAxisName'		=> '',
			'lineThickness'		=> 3,
			'shadowThickness'	=> 2,
			'shadowXShift'		=> 2,
			'shadowYShift'		=> 2,
			'formatNumberScale'	=> 0,
			'thousandSeparator'	=> '',
			'canvasBgAlpha'		=> 75,
			'canvasBorderThickness'	=> 1,
			'rotateNames'		=> 1,
			//'chartTopMargin'	=> 10,
			'chartRightMargin'	=> 4,
			'chartBottomMargin'	=> 1,
			'chartLeftMargin'	=> 1,
			//'decimalPrecision'	=> 0,
		);
		$fc = $this->charts->create('line', 285, 150, $params);
	
		$dates = $this->ps->get_min_max_dates($this->plr['gametype'], $this->plr['modtype'], true);
		
		$max_days = 31;
		$list = $this->ps->get_player_history(array(
			'id'		=> $id,
			'keyed' 	=> true,
			'fill_gaps' 	=> true,
			'start_date'	=> date('Y-m-d', $dates['max']),
			'fields'	=> 'skill',
			'sort'		=> 'statdate',
			'order'		=> 'desc',
			'limit'		=> $max_days,
		));

		// reverse it so the chart will go from oldest -> newest
		$list = array_reverse($list);
	
		$sum = 0;
		$total = 0;
		$avg = 0;
		$i = 0;
		$first = array();
		$last = array();
		foreach($list as $v){
			$settings = sprintf('name=%s;showName=%d;hoverText=%s;',
				date('M d', $v['date']),
				$i++ % 2 == 0, // show every other label
				date('M jS Y', $v['date'])
			);
			$fc->addChartData($v['skill'], $settings);
			if (!is_null($v['skill'])) {
				// only count values that are present for avg
				$total++;
				$sum += $v['skill'];
				if (!$first) {
					$first = $v;
				}
				$last = $v;
			}
		}
		$avg = $total ? $sum / $total : 0;
		if ($avg) {
			$fc->addTrendLine("startValue=$avg;color=44cc44;alpha=50;showOnTop=1;displayValue=" .
					  trans('Avg'));
		}
		//$fc->addTrendLine(sprintf("startValue=%f;endValue=%f;color=cc4444;isTrendZone=0;displayValue=%s",
		//		  $first['skill'],
		//		  $last['skill'],
		//		  trans('Trend')));

		$this->player_skill_chart = $fc->renderChart(false,false);
	}

	// Load player sessions array, total, table, and pager.
	function _load_player_sessions($id) {
		$this->player_sessions = $this->ps->get_player_sessions(array(
			'id' => $id,
			'sort'	=> $this->get['ss'],
			'order' => $this->get['so'],
			'start' => $this->get['sst'],
			'limit' => 10
		));

		$this->player_sessions_total = $this->ps->get_player_total($id, 'sessions');

		$this->player_sessions_table = $this->default_table->create()
			->set_data($this->player_sessions)
			->set_sort($this->get['ss'], $this->get['so'], array($this, '_sort_header_callback'))
			->set_sort_names(array('sort' => 'ss', 'order' => 'so', 'start' => 'sst'))
			->column('session_start',	trans('Session Start'),	array($this, '_cb_datetime'))
			->column('session_seconds',	trans('Length'), 	array($this, '_cb_session_length'))
			->column('name',		trans('Map'), 		array($this, '_cb_name'))
			->column('kills',		trans('Kills'), 	'number_format')
			->column('deaths',		trans('Deaths'), 	'number_format')
			->column('headshot_kills', 	trans('HS'),	 	'number_format')
			->column('skill',		trans('Skill'), 	array($this, '_cb_skill'))
			//->data_attr('session_start', 'class', 'name')
			->data_attr('skill', 'class', 'skill')
			->header_attr('headshot_kills', array( 'tooltip' => trans('Headshot Kills') ))
			;
		
		// allow game::mod's to change the table layout
		$this->ps->mod_table($this->player_sessions_table, 'player_sessions',
				     $this->plr['gametype'], $this->plr['modtype']);

		$this->player_sessions_pager = $this->default_pager->create(array(
			'total'	=> $this->player_sessions_total,
			'start'	=> $this->get['sst'],
			'start_var' => 'sst',
			'urltail' => '#sessions'
		));
	}

	function _load_player_weapons($id) {
		$this->player_weapons = $this->ps->get_player_weapons(array(
			'id' => $id,
			'sort'	=> $this->get['ws'],
			'order' => $this->get['wo'],
			'start' => $this->get['wst'],
			'limit'	=> 10,
		));
		
		$this->player_weapons_total = $this->ps->get_player_total($id, 'weapons');
		
		$this->player_weapons_table = $this->default_table->create()
			->set_data($this->player_weapons)
			->set_sort($this->get['ws'], $this->get['wo'], array($this, '_sort_header_callback'))
			->set_sort_names(array('sort' => 'ws', 'order' => 'wo', 'start' => 'wst'))
			->column('img',			trans('Weapon'), 	array($this, '_cb_weapon_img'))
			->column('name',		trans('Name'), 		array($this, '_cb_name'))
			->column('kills_scaled_pct',	trans('Kill%'), 	array($this, '_cb_kills_pct'))
			->column('kills',		trans('Kills'), 	'number_format')
			->column('deaths',		trans('Deaths'), 	'number_format')
			->column('headshot_kills', 	trans('HS'),	 	'number_format')
			//->column('headshot_kills_pct', 	trans('HS%'),	 	'cb:plr_hs')
			->data_attr('img', 'class', 'img')
			->data_attr('name', 'class', 'name')
			->header_attr('img', 'nosort', true)
			->header_attr('kills_scaled_pct', 	array( 'tooltip' => trans('Kill Percentage') ))
			->header_attr('headshot_kills', 	array( 'tooltip' => trans('Headshot Kills') ))
			//->header_attr('headshot_kills_pct', 	array( 'tooltip' => trans('Headshot Kills Percentage') ))
			;

		$this->ps->mod_table($this->player_weapons_table, 'player_weapons',
				     $this->plr['gametype'], $this->plr['modtype']);
		
		$this->player_weapons_pager = $this->default_pager->create(array(
			'total'	=> $this->player_weapons_total,
			'start'	=> $this->get['wst'],
			'start_var' => 'wst',
			'urltail' => '#weapons'
		));


		// build a PIE chart for the top 10 player weapons
		if (!$this->get['js']) {
			$by = 'kills';
			$by_trans = trans('kills');

			$list =& $this->player_weapons;
			if ($this->get['ws'] != $by or
			    $this->get['wo'] != 'desc' or
			    $this->get['wst'] != 0) {
				// get a new list if our current sort isn't the
				// top 10 based on kills.
				$list = $this->ps->get_player_weapons(array(
					'id' => $id,
					'sort'	=> $by,
					'order' => 'desc',
					'limit'	=> 10,
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
				if ($i>=10) break;
			}
			
			$this->player_weapons_chart = $fc->renderChart(false,false);
		}
	}

	function _load_player_maps($id) {
		$this->player_maps = $this->ps->get_player_maps(array(
			'id' => $id,
			'sort'	=> $this->get['ms'],
			'order' => $this->get['mo'],
			'start' => $this->get['mst'],
			'limit' => 10,
		));

		$this->player_maps_total = $this->ps->get_player_total($id, 'maps');

		$this->player_maps_table = $this->default_table->create()
			->set_data($this->player_maps)
			->set_sort($this->get['ms'], $this->get['mo'], array($this, '_sort_header_callback'))
			->set_sort_names(array('sort' => 'ms', 'order' => 'mo', 'start' => 'mst'))
			->column('img',			trans('Map'),	 	array($this, '_cb_map_img'))
			->column('name',		trans('Name'),	 	array($this, '_cb_name'))
			->column('kills_scaled_pct',	trans('Kill%'), 	array($this, '_cb_kills_pct'))
			->column('kills',		trans('Kills'), 	'number_format')
			->column('deaths',		trans('Deaths'), 	'number_format')
			->column('kills_per_death',	trans('KpD'),	 	'')
			//->column('kills_per_minute',	trans('KpM'),	 	'')
			->data_attr('img', 'class', 'img map')
			->data_attr('name', 'class', 'name')
			->header_attr('kills_scaled_pct', 	array( 'tooltip' => trans('Kill Percentage') ))
			->header_attr('kills_per_death', 	array( 'tooltip' => trans('Kills per Death') ))
			//->header_attr('kills_per_minute', 	array( 'tooltip' => trans('Kills per Minute') ))
			;

		$this->ps->mod_table($this->player_maps_table, 'player_maps',
				     $this->plr['gametype'], $this->plr['modtype']);

		$this->player_maps_pager = $this->default_pager->create(array(
			'total'	=> $this->player_maps_total,
			'start'	=> $this->get['mst'],
			'start_var' => 'mst',
			'urltail' => '#maps'
		));

		// build a PIE chart for the top 10 maps
		if (!$this->get['js']) {
			$by = 'kills';
			$by_trans = trans('kills');

			$list =& $this->player_maps;
			if ($this->get['ms'] != $by or
			    $this->get['mo'] != 'desc' or
			    $this->get['mst'] != 0) {
				// get a new list if our current sort isn't the
				// top 10 based on kills.
				$list = $this->ps->get_player_maps(array(
					'id' => $id,
					'sort'	=> $by,
					'order' => 'desc',
					'limit'	=> 10,
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
				$settings = sprintf('name=%s;showName=1;isSliced=%d;link=%s',
					$d['name'],
					$i++==0 ? 1 : 0,
					ps_site_url('map', $d['name'])
				);
				$fc->addChartData($d['kills'], $settings);
				if ($i>=10) break;
			}
			
			$this->player_maps_chart = $fc->renderChart(false,false);

			// create a column chart with the win ratio for the plr
			// but only if 'wins' is a column in the database.
			if (array_key_exists('wins', $this->player_stats)) {
				$params = array(
					'caption'		=> trans('Win Ratio'),
					'yAxisMinValue'		=> 0,
					'yAxisMaxValue'		=> 100,
					'numberSuffix'		=> '%',
				) + $params;
				
				$fc = $this->charts->create('column3d', 300, 200, $params);

				$wins = $this->player_stats['wins'];
				$losses = $this->player_stats['losses'];
				
				$fc->addChartData($wins / ($wins + $losses) * 100,
						  sprintf("name=%s;hoverText=%s;color=00aa00",
							  trans('Wins'),
							  trans('%d wins', $wins)));
				$fc->addChartData($losses / ($wins + $losses) * 100,
						  sprintf("name=%s;hoverText=%s;color=aa0000",
							  trans('Losses'),
							  trans('%d losses', $losses)));

				$this->player_map_wins_chart = $fc->renderChart(false,false);
			}
		}
		
	}

	function _load_player_roles($id) {
		$this->player_roles = $this->ps->get_player_roles(array(
			'id' => $id,
			'sort'	=> $this->get['rs'],
			'order' => $this->get['ro'],
			'start' => $this->get['rst'],
			'limit'	=> 10,
		));
		
		$this->player_roles_total = $this->ps->get_player_total($id, 'roles');
		
		$this->player_roles_table = $this->default_table->create()
			->set_data($this->player_roles)
			->set_sort($this->get['rs'], $this->get['ro'], array($this, '_sort_header_callback'))
			->set_sort_names(array('sort' => 'rs', 'order' => 'ro', 'start' => 'rst'))
			->column('name',		trans('Role'),	 	array($this, '_cb_name'))
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

		$this->ps->mod_table($this->player_roles_table, 'player_roles',
				     $this->plr['gametype'], $this->plr['modtype']);
		
		$this->player_roles_pager = $this->default_pager->create(array(
			'total'	=> $this->player_roles_total,
			'start'	=> $this->get['rst'],
			'start_var' => 'rst',
			'urltail' => '#roles'
		));


		// build a PIE chart for the top 10 player roles
		if (!$this->get['js']) {
			$by = 'kills';
			$by_trans = trans('kills');

			$list =& $this->player_roles;
			if ($this->get['rs'] != $by or
			    $this->get['ro'] != 'desc' or
			    $this->get['rst'] != 0) {
				// get a new list if our current sort isn't the
				// top 10 based on kills.
				$list = $this->ps->get_player_roles(array(
					'id' => $id,
					'sort'	=> $by,
					'order' => 'desc',
					'limit'	=> 10,
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
				if ($i>=10) break;
			}
			
			$this->player_roles_chart = $fc->renderChart(false,false);
		}
	}

	function _load_player_victims($id) {
		$this->player_victims = $this->ps->get_player_victims(array(
			'id' => $id,
			'sort'	=> $this->get['vs'],
			'order' => $this->get['vo'],
			'start' => $this->get['vst'],
			'limit' => 10,
		));
		
		$this->player_victims_total = $this->ps->get_player_total($id, 'victims');
		
		$this->player_victims_table = $this->default_table->create()
			->set_data($this->player_victims)
			->set_sort($this->get['vs'], $this->get['vo'], array($this, '_sort_header_callback'))
			->set_sort_names(array('sort' => 'vs', 'order' => 'vo', 'start' => 'vst'))
			->column('name',		trans('Victim'), 	array($this, '_cb_name'))
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

		$this->ps->mod_table($this->player_victims_table, 'player_victims',
				     $this->plr['gametype'], $this->plr['modtype']);

		$this->player_victims_pager = $this->default_pager->create(array(
			'total'	=> $this->player_victims_total,
			'start'	=> $this->get['vst'],
			'start_var' => 'vst',
			'urltail' => '#victims'
		));

		// build a PIE chart for the top 10 victims
		if (!$this->get['js']) {
			$by = 'kills';
			$by_trans = trans('kills');

			$list =& $this->player_victims;
			if ($this->get['vs'] != $by or
			    $this->get['vo'] != 'desc' or
			    $this->get['vst'] != 0) {
				// get a new list if our current sort isn't the
				// top 10 based on kills.
				$list = $this->ps->get_player_victims(array(
					'id' => $id,
					'sort'	=> $by,
					'order' => 'desc',
					'limit'	=> 10,
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
				if ($i>=10) break;
			}
			
			$this->player_victims_chart = $fc->renderChart(false,false);
		}
	}

	function _cb_weapon_img($name, $val, $data, $td, $table) {
		$img = img_url('weapons', $data['name'], $this->plr['gametype'], $this->plr['modtype']);
		$text = htmlentities($data['name'], ENT_NOQUOTES, 'UTF-8');
		if ($img) {
			$img = sprintf("<img src='%s' alt='%s' title='%s'/>",
				$img, $text, $text
			);
		}
		$link = sprintf('<a href="%s">%s</a>', ps_site_url('wpn', $data['name']), $img ? $img : $text);
		return $link;
	}

	function _cb_map_img($name, $val, $data, $td, $table) {
		$url = img_url('maps', $data['name'], $this->plr['gametype'], $this->plr['modtype']);
		$text = htmlentities($data['name'], ENT_NOQUOTES, 'UTF-8');
		$img = false;
		if ($url) {
			$img = sprintf('<img class="map-img" src="%s" alt="%s" title="%s" />',
				$url, $text, $text
			);
		} else {
			return '';
		}
		$link = sprintf('<a href="%s">%s</a>', ps_site_url('map', $data['name']), $img ? $img : $text);
		return $link;
	}

	function _cb_datetime($name, $val, $data, $td, $table) {
		$text = date('Y-m-d H:i', $val);
		return $text;
	}
	
	function _cb_session_length($name, $val, $data, $td, $table) {
		if ($data['session_seconds'] >= 60) {
			$text = trans('%d minutes', ceil($data['session_minutes']));
		} else {
			$text = '&lt; ' . trans('%d minute', 1);
		}
		return $text;
	}

	// modifies the value to be an <a> link to the record based on the type
	// of data being displayed in the table.
	function _cb_name($name, $val, $data, $td, $table) {
		$page = '';
		$text = htmlentities($val, ENT_NOQUOTES, 'UTF-8');
		$path = $text;
		
		if (isset($data['mapid'])) {
			$page = 'map';
		} elseif (isset($data['weaponid'])) {
			$page = 'wpn';
		} elseif (isset($data['roleid'])) {
			$page = 'role';
		} elseif (isset($data['victimid'])) {
			$page = 'plr';
			$path = $data['victimid'];
		}

		$link = sprintf('<a href="%s">%s</a>', ps_site_url($page, $path), $text);
		return $link;
	}
	
	function _cb_pct($name, $val, $data, $td, $table) {
		return $val ? $val . '<small>%</small>' : '-';
	}
	
	function _cb_skill($name, $val, $data, $td, $table) {
		return $val . ' ' . skill_change($data);
	}
	
	function _cb_kills_pct($name, $val, $data, $td, $table) {
		return pct_bar(array(
			'pct'	=> $val,
			'title'	=> sprintf('%0.02f%%', $data['kills_pct'])
		));
	}

}


?>