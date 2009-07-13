<?php
/**
 * PsychoStats method get_player()
 * $Id$
 *
 * Fetches a single player record.
 *
 */

class Psychostats_Method_Get_Player extends Psychostats_Method {
	public function execute($criteria = array()) {
		$ci =& get_instance();
		if (!is_array($criteria)) {
			$criteria = array( 'id' => $criteria );
		}
		// set defaults
		$criteria += array(
			'id'		=> 0,
		);
		$id = isset($criteria['id']) ? $criteria['id'] : 0;

		$t_plr		= $this->ps->tbl('plr', false);
		$t_profile	= $this->ps->tbl('plr_profile', false);
		$t_user		= $this->ps->tbl('user', false);
		
		// load the basic player record first.
		$sql = 
<<<CMD
		SELECT p.*, pp.*
		FROM $t_plr p
		LEFT JOIN $t_profile pp ON pp.uniqueid = p.uniqueid
		WHERE p.plrid = ?
		LIMIT 1
CMD;
		$q = $ci->db->query($sql, $id);

		if ($q->num_rows() == 0) {
			// player not found
			return false;
		}

		$res = $q->row_array();
		$q->free_result();

		// sanitize the player name
		//$res['name'] = htmlentities($res['name'], ENT_NOQUOTES, 'UTF-8');

		// load basic stats
		//$res['stats'] = $this->ps->get_player_stats($id, $res['gametype'], $res['modtype']);

		return $res;
	} 
} 

?>