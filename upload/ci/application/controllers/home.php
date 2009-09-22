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

		// get totals ...
		$total_players = $this->ps->get_total_players();
		$total_clans   = $this->ps->get_total_clans(array('min_members' => 2));
		$total_weapons = $this->ps->get_total_weapons();
		$total_maps    = $this->ps->get_total_maps();
		$total_roles   = $this->ps->get_total_roles();

		// PLAYERS
		$select = array(
			'plr.plrid, plr.skill, plr.skill_prev', 
			'plr.rank, plr.rank_prev', 
			'pp.name, pp.cc',
			'kills'
		);
		$criteria = array(
			'select' => $select,
			'limit' => 5,
			'start' => 0,
			'sort' => 'rank asc, kills desc', 
		);
		$players = $this->ps->get_players($criteria);

		$players_table = $this->psychotable->create()
			->set_caption(sprintf('<a href="%s">%s</a>',
					      rel_site_url('players'),
					      trans("Top %d players out of %s",
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

		// CLANS
		//$select = array(
		//	'*' => 'SUM(kills) kills,AVG(rank) rank, AVG(skill) skill',
		//	'clan' => 'clan.clanid',
		//	'cp' => 'cp.*',
		//);
		$criteria = array(
			'select' => null,
			'limit' => 5,
			'start' => 0,
			'sort' => 'skill desc', 
		);
		$clans = $this->ps->get_clans($criteria);

		$clans_table = $this->psychotable->create()
			->set_caption(sprintf('<a href="%s">%s</a>',
					      rel_site_url('clans'),
					      trans("Top %d clans out of %s",
						    count($clans),
						    number_format($total_clans))))
			->set_data($clans)
			->set_sort('skill', 'desc')
			->column('+', 			'#', 		'')
			->column('clantag',		trans('Clan'), 	array($this, '_cb_name_link_no_wrap'))
			->column('total_members',	trans('Mem'), 'number_format')
			->column('kills',		trans('Kills'),	'number_format')
			->column('skill',		trans('Skill'),	array($this, '_cb_clan_skill'))
			->data_attr('+', 'class', 'rank')
			->data_attr('clantag', 'class', 'link')
			->data_attr('skill', 'class', 'skill')
			->header_attr('total_members', array( 'tooltip' => trans('Total Members')))
			;
		$this->ps->mod_table($clans_table, 'clans_top5', $this->get['gametype'], $this->get['modtype']);

		// WEAPONS
		$criteria = array(
			'select' => null,
			'limit' => 5,
			'start' => 0,
			'sort' => 'kills desc', 
		);
		$weapons = $this->ps->get_weapons($criteria);

		$weapons_table = $this->psychotable->create()
			->set_caption(sprintf('<a href="%s">%s</a>',
					      rel_site_url('weapons'),
					      trans("Top %d weapons out of %s",
						    count($weapons),
						    number_format($total_weapons))))
			->set_data($weapons)
			->set_sort('rank', 'asc')
			->column('+', 			'#', 			'')
			//->column('img',			false,		 	array($this, '_cb_weapon_img'))
			->column('name',		trans('Weapon'), 	array($this, '_cb_name_link_no_wrap'))
			->column('kills',		trans('Kills'), 	'number_format')
			->column('headshot_kills',	trans('HS'),	 	'number_format')
			->data_attr('img', 'class', 'img')
			->data_attr('name', 'class', 'link')
			//->header_attr('name', 'colspan', 2)
			;
		$this->ps->mod_table($weapons_table, 'weapons_top5', $this->get['gametype'], $this->get['modtype']);

		// MAPS
		$criteria = array(
			'select' => null,
			'limit' => 5,
			'start' => 0,
			'sort' => 'kills desc', 
		);
		$maps = $this->ps->get_maps($criteria);

		$maps_table = $this->psychotable->create()
			->set_caption(sprintf('<a href="%s">%s</a>',
					      rel_site_url('maps'),
					      trans("Top %d maps out of %s",
						    count($maps),
						    number_format($total_maps))))
			->set_data($maps)
			->set_sort('kills', 'desc')
			->column('+', 			'#', 			'')
			//->column('img',		false,		 	array($this, '_cb_map_img'))
			->column('name',		trans('Map'), 		array($this, '_cb_name_link_no_wrap'))
			->column('kills',		trans('Kills'), 	'number_format')
			->data_attr('img', 'class', 'img')
			->data_attr('name', 'class', 'link')
			//->header_attr('name', 'colspan', 2)
			;
		$this->ps->mod_table($maps_table, 'maps_top5', $this->get['gametype'], $this->get['modtype']);

		// ROLES
		$criteria = array(
			'select' => null,
			'limit' => 5,
			'start' => 0,
			'sort' => 'kills desc', 
		);
		$roles = $this->ps->get_roles($criteria);

		$roles_table = $this->psychotable->create()
			->set_caption(sprintf('<a href="%s">%s</a>',
					      rel_site_url('roles'),
					      trans("Top %d roles out of %s",
						    count($roles),
						    number_format($total_roles))))
			->set_data($roles)
			->set_sort('kills', 'desc')
			->column('+', 			'#', 			'')
			->column('name',		trans('Role'), 		array($this, '_cb_name_link_no_wrap'))
			->column('kills',		trans('Kills'), 	'number_format')
			->data_attr('img', 'class', 'img')
			->data_attr('name', 'class', 'link')
			;
		$this->ps->mod_table($roles_table, 'roles_top5', $this->get['gametype'], $this->get['modtype']);

		$data = array(
			'total_players' => $total_players,
			'total_clans' 	=> $total_clans,
			'total_weapons'	=> $total_weapons,
			'total_maps'	=> $total_maps,
			'total_roles'	=> $total_roles,
			'players_table'	=> $players_table->render(),
			'clans_table'	=> $clans_table->render(),
			'weapons_table'	=> $weapons_table->render(),
			'maps_table'	=> $maps_table->render(),
			'roles_table'	=> $roles_table->render(),
		);

		
		define('PAGE', strtolower(get_class()));
		define('TEMPLATE', PAGE);
		
		$this->load->view('full_page', array('params' => $data));
	}
}

?>