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
			'select'	=> null,
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

		$stats = $criteria['select'] ? $criteria['select'] : $this->get_sql();
		$fields = is_array($stats) ? implode(',', $stats) : $stats;

		$cmd =
<<<CMD
		SELECT $fields
		FROM ($t_session sess, $t_data d)
		LEFT JOIN $t_map m ON m.mapid = sess.mapid
		WHERE sess.dataid = d.dataid AND sess.plrid = ?
CMD;

		$cmd .= $this->ps->order_by($criteria['sort'], $criteria['order']);
		$cmd .= $this->ps->limit($criteria['limit'], $criteria['start']);

		$q = $ci->db->query($cmd, $id);

		$list = array();
		if ($q->num_rows()) {
			foreach ($q->result_array() as $row) {
				$list[] = $row;
			}
		}
		$q->free_result();

		return $list;
	} 

	protected function get_sql() {
		$sql = array(
			'*' =>
<<<CMD
				m.name, sess.mapid, sess.session_start, sess.session_end,
				sess.session_end - sess.session_start AS session_seconds,
				(sess.session_end - sess.session_start) / 60 AS session_minutes,
				IFNULL(sess.skill - sess.skill_prev, 0) AS skill_diff,
				CASE
					WHEN sess.skill > sess.skill_prev THEN 'up'
					WHEN sess.skill < sess.skill_prev THEN 'down'
					ELSE 'same'
				END AS skill_change,
				sess.skill, sess.skill_prev, d.*
CMD
		);
		return $sql;
	}
}

?>