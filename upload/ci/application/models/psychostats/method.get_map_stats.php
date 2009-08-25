<?php
/**
 * PsychoStats method get_map_stats()
 * $Id$
 *
 * Fetches the basic stats for a single map.
 *
 */

class Psychostats_Method_Get_Map_Stats
extends Psychostats_Method {
	public function execute($criteria = array(), $gametype = null, $modtype = null) {
		$ci =& get_instance();
		if (!is_array($criteria)) {
			$criteria = array( 'id' => $criteria );
		}
		// set defaults
		$criteria += array(
			'id' 		=> 0,
			'select' 	=> null,
		);
		$id = isset($criteria['id']) ? $criteria['id'] : 0;
		
		if (!$gametype) {
			$g = $this->ps->get_map_gametype($id);
			if (!$g) {
				return false;
			}
			list($gametype, $modtype) = $g;
		}

		$t_map_data = $this->ps->tbl('c_map_data', $gametype, $modtype);

		$stats = $criteria['select'] ? $criteria['select'] : $this->get_sql();
		$fields = is_array($stats) ? implode(',', $stats) : $stats;
		
		$cmd = "SELECT $fields FROM $t_map_data d WHERE mapid=?";
		$q = $ci->db->query($cmd, $id);

		$res = false;
		if ($q->num_rows()) {
			$res = $q->row_array();
			unset($res['mapid']); // remove useless column
		}
		$q->free_result();

		return $res;
	} 

	protected function get_sql() {
		// non-game specific stats
		$sql = array(
			'*' => '*',
			'kills_per_minute' => 'ROUND(IFNULL(d.kills / (d.online_time / 60), 0),2) kills_per_minute',
		);
		
		return $sql;
	}
} 

?>