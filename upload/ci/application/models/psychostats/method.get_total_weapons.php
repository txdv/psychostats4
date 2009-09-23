<?php
/**
 * PsychoStats method get_total_weapons()
 * $Id$
 *
 * Returns the total weapons available based on the criteria given.
 *
 */
class Psychostats_Method_Get_Total_Weapons extends Psychostats_Method {
	public function execute($criteria = array(), $gametype = null, $modtype = null) {
		// set defaults
		if (!is_array($criteria)) {
			$criteria = array();
		}
		$criteria += array(
			'where' 	=> null
		);

		if ($gametype === null) {
			$gametype = $this->ps->gametype();
		}
		if ($modtype === null) {
			$modtype = $this->ps->modtype();
		}

		$t_weapon = $this->ps->tbl('weapon', false);
		$c_weapon_data = $this->ps->tbl('c_weapon_data', $gametype, $modtype);
		
		// start basic query
		$cmd = "SELECT COUNT(*) total FROM $c_weapon_data d,$t_weapon wpn WHERE ";
		
		// add join clause for tables
		$criteria['where'][] = 'd.weaponid=wpn.weaponid';

		$cmd .= $this->ps->where($criteria['where']);

		$q = $this->ps->db->query($cmd);

		$count = 0;
		if ($q->num_rows()) {
			$res = $q->row_array();
			$count = $res['total'];
		}
		$q->free_result();

		return $count;
	} 
} 

?>
