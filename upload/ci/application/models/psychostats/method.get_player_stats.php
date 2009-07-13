<?php
/**
 * PsychoStats method get_player_stats()
 * $Id$
 *
 * Fetches the basic stats for a single player.
 *
 */

class Psychostats_Method_Get_Player_Stats extends Psychostats_Method {
	public function execute($criteria = array(), $gametype = null, $modtype = null) {
		$ci =& get_instance();
		if (!is_array($criteria)) {
			$criteria = array( 'id' => $criteria );
		}
		// set defaults
		$criteria += array(
			'id' => 0,
		);
		$id = isset($criteria['id']) ? $criteria['id'] : 0;
		
		$res = array();

		if (!$gametype) {
			$g = $this->ps->get_player_gametype($id);
			if (!$g) {
				return false;
			}
			list($gametype, $modtype) = $g;
		}

		$t_plr_data = $this->ps->tbl('c_plr_data', $gametype, $modtype);

		$sql = "SELECT * FROM $t_plr_data WHERE plrid = ? LIMIT 1";
		$q = $ci->db->query($sql, $id);

		$res = false;
		if ($q->num_rows()) {
			$res = $q->row_array();
			unset($res['plrid']);
		}
		$q->free_result();

		return $res;
	} 
} 

?>