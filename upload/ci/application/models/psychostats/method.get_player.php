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
			'select'	=> null,
		);
		$id = isset($criteria['id']) ? $criteria['id'] : 0;

		$t_plr		= $this->ps->tbl('plr', false);
		$t_profile	= $this->ps->tbl('plr_profile', false);
		$t_user		= $this->ps->tbl('user', false);

		$stats = $criteria['select'] ? $criteria['select'] : $this->get_sql();
		$fields = is_array($stats) ? implode(',', $stats) : $stats;

		$cmd = 
<<<CMD
		SELECT $fields
		FROM $t_plr plr
		LEFT JOIN $t_profile pp ON pp.uniqueid = plr.uniqueid
		WHERE plr.plrid = ?
CMD;
		$q = $ci->db->query($cmd, $id);

		if ($q->num_rows() == 0) {
			// player not found
			return false;
		}

		$res = $q->row_array();
		$q->free_result();

		// sanitize the player name
		//$res['name'] = htmlentities($res['name'], ENT_NOQUOTES, 'UTF-8');

		return $res;
	}
	
	protected function get_sql() {
		$sql = array(
			'plr' 			=> 'plr.*',
			'pp'			=> 'pp.*', 
			'skill_change_pct' 	=> 'ROUND(IF(skill>=skill_prev, (skill-skill_prev)/skill_prev,(skill_prev-skill)/skill*-1)*100, 2) skill_change_pct',
		);
		return $sql;
	}
} 

?>
