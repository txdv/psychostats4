<?php
/**
 * PsychoStats method get_total_players()
 * $Id$
 *
 * Returns the total players available based on the criteria given.
 *
 * Note: 'gametype' and 'modtype' must be passed in the $where criteria if
 * you want to limit players to a certain game. This will allow totals to be
 * calculated on ALL players in the database regardless of game.
 *
 */

class Psychostats_Method_Get_Total_Players extends Psychostats_Method {
	public function execute($where = array()) {
		$ci =& get_instance();

		if (!empty($where)) {
			$ci->db->where($where);
		}

		$count = $ci->db->count_all_results('plr');
		return $count;
	} 
} 

?>