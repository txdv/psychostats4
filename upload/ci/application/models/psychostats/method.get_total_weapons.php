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

		$ci =& get_instance();
		if ($gametype === null) {
			$gametype = $this->ps->gametype();
		}
		if ($modtype === null) {
			$modtype = $this->ps->modtype();
		}

		$t_weapon = $this->ps->tbl('weapon', false);
		$c_weapon_data = $ci->db->dbprefix('c_weapon_data_' . $gametype);
		if ($modtype) {
			$c_weapon_data .= '_' . $modtype;
		}
		
		// start basic query
		$sql = "SELECT COUNT(*) total FROM $t_weapon weapon,$c_weapon_data d WHERE ";
		
		// add join clause for tables
		$criteria['where'][] = 'd.weaponid=weapon.weaponid';

		$sql .= $this->ps->where($criteria['where']);

		$q = $ci->db->query($sql);

		$count = 0;
		if ($q->num_rows()) {
			$res = $q->row_array();
			$count = $res['total'];
		}
		$q->free_result();

		return $count;	} 
} 

?>
