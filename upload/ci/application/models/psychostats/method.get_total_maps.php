<?php
/**
 * PsychoStats method get_total_maps()
 * $Id$
 *
 * Returns the total maps available based on the criteria given.
 *
 * Note: 'gametype' and 'modtype' must be passed in the $where criteria if
 * you want to limit players to a certain game. This will allow totals to be
 * calculated on ALL maps in the database regardless of game.
 *
 */

class Psychostats_Method_Get_Total_Maps extends Psychostats_Method {
	public function execute($where = array()) {
		$ci =& get_instance();

		if (!empty($where)) {
			$ci->db->where($where);
		}

		// TODO: This is wrong. it will return total maps in the database
		// regardless if they have stats or not. FIXME

		$count = $ci->db->count_all_results('map');
		return $count;
	} 
} 

?>
