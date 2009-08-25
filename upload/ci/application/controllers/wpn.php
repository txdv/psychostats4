<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Wpn extends MY_Controller {
	protected $weapon_stats;
	
	protected $base_url;
	protected $get_defaults;
	protected $get;
	protected $wpn;
	protected $default_table;
	protected $default_pager;
	protected $blocks;

	protected $limit_players = 25;
	
	function Wpn()
	{
		parent::MY_Controller();
	}
	
	function view($id = null)
	{
		list($id) = parent::view(func_get_args());
		
		$this->load->library('charts');
		$this->load->library('psychotable');
		$this->load->helper('get');

		// assign ourself to the PS object so overloaded methods
		// within PS will be able to use callbacks in the MY_Controller
		// object.
		$this->ps->controller = $this;
		
		$this->base_url = "$id/"; 	// used by build_query_string()
		$this->get_defaults = array(
			'ks'	=> 'kills',
			'ko'	=> 'desc',
			'kst'	=> 0,
			//'js'	=> '',			// ajax call
		);
		$this->get = get_url_params($this->get_defaults, 1);
		
		//$ajax = $this->get['js'];

		// Load the record ... If it doesn't exist then silently
		// redirect to the weapons list ...
		if ($id) {
			$this->wpn = $this->ps->get_weapon($id);
		}
		if (!$this->wpn) {
			redirect_previous('weapons');
		}
		if (!is_numeric($id)) {
			$id = $this->wpn['weaponid'];
		}

		// set the default game/mod so we don't have to pass it around
		$this->ps->set_gametype($this->wpn['gametype'], $this->wpn['modtype']);

		// define a base table that all other tables will inherit from
		$this->default_table = $this->psychotable->create()
			->set_template('table_open', '<table class="neat">')
			;

		$this->weapon_stats = $this->ps->get_weapon_stats($id);
		$this->topten_kills = $this->ps->get_weapon_players(array(
			'id' => $id,
			'sort' => $this->get['ks'],
			'order' => $this->get['ko'],
			'limit' => $this->limit_players,
		));

		$this->topten_kills_table = $this->default_table->create()
			->set_data($this->topten_kills)
			->set_sort($this->get['ks'], $this->get['ko'], array($this, '_sort_header_callback'))
			->set_sort_names(array('sort' => 'ks', 'order' => 'ko', 'start' => 'kst'))
			->column('rank', 		trans('Rank'), 		array($this, '_cb_plr_rank'))
			->column('name',		trans('Victim'), 	array($this, '_cb_name_link'))
			->column('kills',		trans('Kills'), 	'number_format')
			->column('deaths',		trans('Deaths'), 	'number_format')
			->column('kills_per_death',	trans('KpD'),	 	'')
			->column('headshot_kills', 	trans('HS'),	 	'number_format')
			->column('skill',		trans('Skill'), 	array($this, '_cb_plr_skill'))
			->data_attr('rank', 'class', 'rank')
			->data_attr('name', 'class', 'name')
			->data_attr('skill', 'class', 'skill')
			->header_attr('kills_scaled_pct', 	array( 'tooltip' => trans('Kill Percentage') ))
			->header_attr('kills_per_death', 	array( 'tooltip' => trans('Kills per Death') ))
			->header_attr('headshot_kills', 	array( 'tooltip' => trans('Headshot Kills') ))
			;

		$this->ps->mod_table($this->topten_kills_table, 'weapon_topten_kills',
				     $this->wpn['gametype'], $this->wpn['modtype']);

		// a normal page display will include all data ...
		$total_weapons = $this->ps->get_total_weapons();

		$title = trans('Weapon "%s"', $this->wpn['long_name']);		
		$page_subtitle = $this->wpn['name'] != $this->wpn['long_name'] ? $this->wpn['name'] : '';
		
		// load blocks of data for the side nav area
		$this->nav_blocks = array();
		$this->nav_blocks['weapon_vitals'] = array(
			'title' => trans('Weapon Vitals'),
			'rows' => array(
				'long_name' => array(
					'label' => trans('Name'),
					'value' => htmlentities($this->wpn['long_name'], ENT_COMPAT, 'UTF-8'),
					'value_nowrap' => true,
				),
				'class' => array(
					'label' => trans('Class'),
					'value' => $this->wpn['class'] ? $this->wpn['class'] : '<em>' . trans('none') . '</em>',
				),
				'weight' => array(
					'label' => trans('Skill Modifier'),
					'value' => $this->wpn['weight'] ? $this->wpn['weight'] : '<em>1.0</em>',
				),
			),
		);
		
		// allow game specific updates to the blocks ...
		// load method if available
		$method = $this->ps->load_overloaded_method('nav_blocks_weapon', $this->wpn['gametype'], $this->wpn['modtype']);
		$nav_block_html = $this->smarty->render_blocks(
			$method, $this->nav_blocks, 
			array(&$this->wpn, &$this->weapon_stats)
		);
		
		$data = array(
			'title'		=> $title,
			'page_title' 	=> $title,
			'page_subtitle' => $page_subtitle,
			'wpn'		=> &$this->wpn,
			'stats'		=> &$this->weapon_stats,
			'topten_kills'	=> &$this->topten_kills,
			'topten_kills_table' => $this->topten_kills_table->render(),
			'nav_block_html'=> &$nav_block_html,

		);
		
		define('BODY_LAYOUT', $this->smarty->page_layout('300left'));
		define('PAGE', strtolower(get_class()));
		define('TEMPLATE', PAGE);
		
		$this->load->view('full_page', array('params' => $data));
	}

	function _cb_weapon_img($name, $val, $data, $td, $table) {
		$img = img_url('weapons', $data['name'], $this->wpn['gametype'], $this->wpn['modtype']);
		$text = htmlentities($data['name'], ENT_NOQUOTES, 'UTF-8');
		if ($img) {
			$img = sprintf("<img src='%s' alt='%s' title='%s'/>",
				$img, $text, $text
			);
		}
		return sprintf('<a href="%s">%s</a>', ps_site_url('wpn', $data['name']), $img ? $img : $text);
	}

}


?>