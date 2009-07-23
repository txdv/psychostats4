<?php
/**
 * PsychoStats method get_player_roles()
 * $Id$
 *
 * Fetches the roles stats for a single player.
 *
 */

class Psychostats_Method_Get_Player_Roles extends Psychostats_Method {
	public function execute($criteria = array(), $gametype = null, $modtype = null) {
		if (!is_array($criteria)) {
			$criteria = array( 'id' => $criteria );
		}
		// set defaults
		$criteria += array(
			'id'		=> 0,
			'sort'		=> 'kills',
			'order' 	=> 'desc',
			'limit' 	=> null,
			'start' 	=> 0,
			'fields'	=> null,	// extra fields to select
		);
		
		$ci =& get_instance();
		$res = array();
		$id = isset($criteria['id']) ? $criteria['id'] : 0;

		if (!$gametype) {
			$g = $this->ps->get_player_gametype($id, true);
			if (!$g) {
				return false;
			}
			list($gametype, $modtype) = $g;
		}

		$t_data = $this->ps->tbl('c_plr_data', $gametype, $modtype);
		$t_roles = $this->ps->tbl('c_plr_roles', $gametype, $modtype);
		$t_role = $this->ps->tbl('role', false);

		// non game specific stats
		$stats = array(
			'r.name, r.full_name, d.*',
			'IFNULL(d.kills / d.deaths, 0) kills_per_death',
			"IFNULL(d.kills / (SELECT MAX(d2.kills) FROM $t_roles d2 WHERE d2.plrid=d.plrid) * 100, 0) kills_scaled_pct",
			'IFNULL(d.kills / d2.kills * 100, 0) kills_pct'
		);

		// allow game::mod specific stats to be added
		if ($meth = $this->ps->load_overloaded_method('get_player_roles_sql', $gametype, $modtype)) {
			$meth->execute($stats);
		}
		
		// combine everything into a string for our query
		$fields = implode(',', $stats);
		
		// load the compiled stats
		$sql =
<<<CMD
		SELECT $fields
		FROM ($t_roles d, $t_role r)
		LEFT JOIN $t_data d2 ON d2.plrid=d.plrid
		WHERE r.roleid=d.roleid AND d.plrid = ?
CMD;

		$sql .= $this->ps->order_by($criteria['sort'], $criteria['order']);
		$sql .= $this->ps->limit($criteria['limit'], $criteria['start']);
		
		$q = $ci->db->query($sql, $id);

		$res = array();
		if ($q->num_rows()) {
			foreach ($q->result_array() as $row) {
				// remove useless fields
				unset($row['plrid']);
				$res[] = $row;
			}
		}
		$q->free_result();

		return $res;
	} 
} 

?>