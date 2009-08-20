<?php
/**
 * PsychoStats method get_player_sessions()
 * $Id$
 *
 * Fetches the sessions stats for a single player.
 *
 */

class Psychostats_Method_Get_Player_Sessions extends Psychostats_Method {
	public function execute($criteria = array(), $gametype = null, $modtype = null) {
		if (!is_array($criteria)) {
			$criteria = array( 'id' => $criteria );
		}
		// set defaults
		$criteria += array(
			'id'		=> 0,
			'sort'		=> 'session_start',
			'order' 	=> 'desc',
			'limit' 	=> null,
			'start' 	=> 0,
			'fields'	=> null,	// extra fields to select
		);
		
		$ci =& get_instance();
		$res = array();
		$id = isset($criteria['id']) ? $criteria['id'] : 0;

		if (!$gametype) {
			$g = $this->ps->get_player_gametype($id);
			if (!$g) {
				return false;
			}
			list($gametype, $modtype) = $g;
		}

		$t_map = $this->ps->tbl('map', false);
		$t_session = $this->ps->tbl('plr_sessions', false);
		$t_data = $this->ps->tbl('plr_sessions', $gametype, $modtype);

		// load the compiled stats
		$sql =
<<<CMD
		SELECT
		m.name, s.mapid, s.session_start, s.session_end,
		s.session_end - s.session_start AS session_seconds,
		(s.session_end - s.session_start) / 60 AS session_minutes,
		IFNULL(s.skill - s.skill_prev, 0) AS skill_diff,
		CASE
			WHEN s.skill > s.skill_prev THEN 'up'
			WHEN s.skill < s.skill_prev THEN 'down'
			ELSE 'same'
		END AS skill_change,
		s.skill, s.skill_prev, d.*
		FROM ($t_session s, $t_data d)
		LEFT JOIN $t_map m ON m.mapid = s.mapid
		WHERE s.dataid = d.dataid AND s.plrid = ?
CMD;

		$sql .= $this->ps->order_by($criteria['sort'], $criteria['order']);
		$sql .= $this->ps->limit($criteria['limit'], $criteria['start']);

		$q = $ci->db->query($sql, $id);

		$res = array();
		if ($q->num_rows()) {
			foreach ($q->result_array() as $row) {
				$res[] = $row;
			}
		}
		$q->free_result();

		return $res;
	} 
} 

?>