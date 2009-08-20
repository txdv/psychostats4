<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Weapons extends MY_Controller {

	function Weapons()
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
		
		// determine the total weapons available
		$total_weapons = $this->ps->get_total_weapons();
		
		// determine the total players available
		$total_players = $this->ps->get_total_players();

		$t_weapons = $this->ps->tbl('c_weapon_data', $this->get['gametype'], $this->get['modtype']);

		// non game specific stats		
		$stats = array(
			// basic information
			'name, full_name, weight, class',

			// static stats
			'd.*',

			// calculated stats
			"IFNULL(d.kills / (SELECT MAX(d3.kills) FROM $t_weapons d3) * 100, 0) kills_scaled_pct",
			"IFNULL(d.kills / (SELECT SUM(d2.kills) FROM $t_weapons d2) * 100, 0) kills_pct",
			'ROUND(IFNULL(headshot_kills / kills * 100, 0), 0) headshot_kills_pct',
			'IFNULL(d.shots / d.hits * 100, 0) accuracy',
			'IFNULL(d.hit_head / d.hits * 100, 0) hit_head_pct',
			'IFNULL(d.hit_chest / d.hits * 100, 0) hit_chest_pct',
			'IFNULL(d.hit_leftarm / d.hits * 100, 0) hit_leftarm_pct',
			'IFNULL(d.hit_rightarm / d.hits * 100, 0) hit_rightarm_pct',
			'IFNULL(d.hit_stomach / d.hits * 100, 0) hit_stomach_pct',
			'IFNULL(d.hit_leftleg / d.hits * 100, 0) hit_leftleg_pct',
			'IFNULL(d.hit_rightleg / d.hits * 100, 0) hit_rightleg_pct',
			'IFNULL(d.dmg_head / d.damage * 100, 0) dmg_head_pct',
			'IFNULL(d.dmg_chest / d.damage * 100, 0) dmg_chest_pct',
			'IFNULL(d.dmg_leftarm / d.damage * 100, 0) dmg_leftarm_pct',
			'IFNULL(d.dmg_rightarm / d.damage * 100, 0) dmg_rightarm_pct',
			'IFNULL(d.dmg_stomach / d.damage * 100, 0) dmg_stomach_pct',
			'IFNULL(d.dmg_leftleg / d.damage * 100, 0) dmg_leftleg_pct',
			'IFNULL(d.dmg_rightleg / d.damage * 100, 0) dmg_rightleg_pct',
		);
		
		$criteria = array(
			'select'=> $stats,
			'limit' => $this->get['limit'],
			'start' => $this->get['start'],
			'sort'	=> $this->get['sort'],
			'order'	=> $this->get['order'],
		);
		$weapons = $this->ps->get_weapons($criteria);

		$table = $this->psychotable->create()
			->set_data($weapons)
			->set_template('table_open', '<table class="neat">')
			->set_sort($this->get['sort'], $this->get['order'], array($this, '_sort_header_callback'))
			->column('img',			false,		 	array($this, '_cb_weapon_img'))
			->column('name',		trans('Weapon'),	array($this, '_cb_name_link'))
			->column('kills_scaled_pct',	trans('Kill%'), 	array($this, '_cb_kills_pct'))
			->column('kills',		trans('Kills'), 	'number_format')
			->column('headshot_kills', 	trans('HS'),	 	'number_format')
			->column('headshot_kills_pct', 	trans('HS%'),	 	array($this, '_cb_pct'))
			->column('accuracy', 		trans('Accuracy'), 	array($this, '_cb_pct2'))
			->column('Damage',		trans('Damage'), 	array($this, '_cb_abbrnum'))
			->data_attr('name', 'class', 'name')
			->data_attr('img', 'class', 'img')
			->header_attr('img', 'nosort', true)
			->header_attr('name', 'colspan', 2)
			->header_attr('kills_scaled_pct', 	array( 'tooltip' => trans('Kill Percentage') ))
			->header_attr('headshot_kills', 	array( 'tooltip' => trans('Headshot Kills') ))
			->header_attr('headshot_kills_pct', 	array( 'tooltip' => trans('Headshot Kills Percentage') ))
			;
		$this->ps->mod_table($table, 'weapons', $this->get['gametype'], $this->get['modtype']);

		// define pager
		$this->pager->initialize(array(
			'base_url'	=> page_url(build_query_string($this->get, $this->get_defaults, 'start')),
			'total' 	=> $total_weapons,
			'start'		=> $this->get['start'],
		));
		$pager = $this->pager->create_links();

		$page_subtitle = trans('<strong>%s</strong> weapons have killed <strong>%s</strong> players',
			number_format($total_weapons),
			number_format($total_players)
		);
		$data = array(
			'title'		=> trans('Weapon Statistics'),
			'page_title' 	=> trans('Weapon Statistics'),
			'page_subtitle' => $page_subtitle,
			'weapons' 	=> &$weapons,
			'table'		=> $table->render(),
			'total_weapons' => $total_weapons,
			'total_players' => $total_players,
			'pager'		=> $pager,
		);

		define('PAGE', strtolower(get_class()));
		define('TEMPLATE', PAGE);
		
		$this->load->view('full_page', array('params' => $data));
	}

}

?>