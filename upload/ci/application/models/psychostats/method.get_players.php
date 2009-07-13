<?php
/**
 * PsychoStats method get_players()
 * $Id$
 *
 */
class Psychostats_Method_Get_Players extends Psychostats_Method {
	/**
	 * Fetches a list of player stats for a specific game.
	 * 
	 * @param array $criteria
	 * 	Criteria that defines what players will be returned.
	 * @param string $gametype
	 * 	Game type to fetch players list for. Leave null for current
	 * 	default (from set_gametype).
	 * @param string $modtype
	 * 	Mod type to fetch players list for. Leave null for current
	 * 	default (from set_gametype or set_modtype)
	 */
	public function execute($criteria = array(), $gametype = null, $modtype = null) {
		// set defaults
		$criteria += array(
			'select' 	=> null,
			'limit' 	=> null,
			'start' 	=> null,
			'order' 	=> null,
			'where' 	=> null,
			'is_ranked' 	=> true,	// true
		);
		
		$ci =& get_instance();
		if ($gametype === null) {
			$gametype = $this->ps->gametype;
		}
		if ($modtype === null) {
			$modtype = $this->ps->modtype;
		}

		//$t_plr = $ci->db->dbprefix('plr');
		//$t_plr_profile = $ci->db->dbprefix('plr_profile');
		//if ($modtype) {
		//	$t_plr_data = $ci->db->dbprefix('c_plr_data_' . $gametype . '_' . $modtype);
		//} else {
		//	$t_plr_data = $ci->db->dbprefix('c_plr_data_' . $gametype);
		//}
		//
		//// -- Active Record method below ...
		

		$t_plr_data = $ci->db->dbprefix('c_plr_data_' . $gametype);
		if ($modtype) {
			$t_plr_data .= '_' . $modtype;
		}

		if (!empty($criteria['select'])) {
			$ci->db->select($criteria['select'], false);
		}
		if (!empty($criteria['where'])) {
			$ci->db->where($criteria['where']);
		}
		
		if ($criteria['is_ranked']) {
			$ci->db->where('rank IS NOT NULL AND rank <> 0');
		}
		
		$ci->db->where('gametype', $gametype);
		$ci->db->where('modtype', $modtype);
		$ci->db->where("plr.plrid = $t_plr_data.plrid");

		$ci->db->join('plr_profile',
			      'plr.uniqueid = plr_profile.uniqueid',
			      'left');

		if (!empty($criteria['order'])) {
			$ci->db->order_by($criteria['order']);
		} else {
			$ci->db->order_by('rank asc');
		}

		if (isset($criteria['limit']) and isset($criteria['start'])) {
			$ci->db->limit($criteria['limit'], $criteria['start']);
		} elseif (isset($criteria['limit'])) {
			$ci->db->limit($criteria['limit']);
		}

		$q = $ci->db->get("plr, $t_plr_data");
		return $q->result_array();
	}
} 

?>