<?php
/**
 * PsychoStats method get_total_maps()
 * $Id$
 *
 * Returns the total maps available based on the criteria given.
 *
 */

class Psychostats_Method_Get_Total_Maps extends Psychostats_Method {
	public function execute($criteria = array(), $gametype = null, $modtype = null) {
		// set defaults
		if (!is_array($criteria)) {
			$criteria = array();
		}
		$criteria += array(
			'where' 	=> null,
			'is_ranked'	=> null
		);

		if ($gametype === null) {
			$gametype = $this->ps->gametype();
		}
		if ($modtype === null) {
			$modtype = $this->ps->modtype();
		}

		$t_map = $this->ps->tbl('map', false);
		$c_map_data = $this->ps->tbl('c_map_data', $gametype, $modtype);
		
		// start basic query
		$cmd = "SELECT COUNT(*) total FROM $t_map map,$c_map_data d WHERE ";
		
		// add join clause for tables
		$criteria['where'][] = 'd.mapid=map.mapid';

		// apply is_ranked shortcut
		if ($criteria['is_ranked']) {
			$criteria['where'][] = $this->ps->is_ranked_sql;
		}
		
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
