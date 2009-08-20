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
		);
		
		$id = isset($criteria['id']) ? $criteria['id'] : 0;

		$t_map = $this->ps->tbl('map', false);

		// Match the record based on the numeric ID or its name
		$key = (is_numeric($criteria['id']) and !$criteria['force_string'])
			? 'mapid'
			: 'name';
		
		// load the basic record first.
		$sql = 
<<<CMD
		SELECT map.*, COALESCE(full_name, name) long_name
		FROM $t_map map
		WHERE map.$key = ?
CMD;

		$ci =& get_instance();
		$q = $ci->db->query($sql, $id);

		if ($q->num_rows() == 0) {
			// not found
			return false;
		}

		$res = $q->row_array();
		$q->free_result();

		return $res;
	} 
} 

?>
