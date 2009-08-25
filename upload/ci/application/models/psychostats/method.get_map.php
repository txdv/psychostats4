<?php
/**
 * PsychoStats method get_map()
 * $Id$
 *
 * Fetches a single player record.
 *
 */

class Psychostats_Method_Get_Map extends Psychostats_Method {
	public function execute($criteria = array()) {
		if (!is_array($criteria)) {
			$criteria = array( 'id' => $criteria );
		}
		// set defaults
		$criteria += array(
			'id'		=> 0,
			'force_string'	=> false,
			'select'	=> null,
		);
		
		$id = isset($criteria['id']) ? $criteria['id'] : 0;

		$t_map = $this->ps->tbl('map', false);

		// Match the record based on the numeric ID or its name
		$key = (is_numeric($criteria['id']) and !$criteria['force_string'])
			? 'mapid'
			: 'name';

		$stats = $criteria['select'] ? $criteria['select'] : $this->get_sql();
		$fields = is_array($stats) ? implode(',', $stats) : $stats;

		$cmd = "SELECT $fields FROM $t_map map WHERE map.$key=?";

		$ci =& get_instance();
		$q = $ci->db->query($cmd, $id);

		if ($q->num_rows() == 0) {
			// not found
			return false;
		}

		$res = $q->row_array();
		$q->free_result();

		return $res;
	} 

	protected function get_sql() {
		$sql = array(
			'map' => 'map.*, COALESCE(full_name, name) long_name',
		);
		return $sql;
	}
} 

?>
