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
		if (!$this->ps->has_roles()) {
			return array();
		}
		
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

		$t_data = $this->ps->tbl('c_plr_data', $gametype, $modtype);
		$t_roles = $this->ps->tbl('c_plr_roles', $gametype, $modtype);
		$t_role = $this->ps->tbl('role', false);

		$stats = $criteria['select'] ? $criteria['select'] : $this->get_sql();
		$fields = is_array($stats) ? implode(',', $stats) : $stats;

		$cmd =
<<<CMD
		SELECT $fields
		FROM ($t_roles d, $t_role role)
		WHERE role.roleid = d.roleid AND d.plrid = ?
CMD;

		$cmd .= $this->ps->order_by($criteria['sort'], $criteria['order']);
		$cmd .= $this->ps->limit($criteria['limit'], $criteria['start']);
		
		$q = $ci->db->query($cmd, $id);

		$list = array();
		if ($q->num_rows()) {
			foreach ($q->result_array() as $row) {
				// remove id so it doesn't cause problems with
				// some url generation.
				unset($row['plrid']);
				$list[] = $row;
			}
		}
		$q->free_result();

		return $list;
	} 

	protected function get_sql() {
		$t_maps = $this->ps->tbl('c_plr_maps', $this->ps->gametype(), $this->ps->modtype());

		// non game specific stats
		$sql = array(
			'*' 			=> 'd.*',
			'role'			=> 'role.name, role.full_name',
			'kills_per_death' 	=> 'ROUND(IFNULL(kills / deaths, 0),2) kills_per_death',

			'headshot_kills_pct' 	=> 'IFNULL(headshot_kills / kills * 100, 0) headshot_kills_pct',
			'headshot_deaths_pct' 	=> 'IFNULL(headshot_deaths / deaths * 100, 0) headshot_deaths_pct',

			'kills_scaled_pct' 	=> "IFNULL(d.kills / (SELECT MAX(d3.kills) FROM $t_maps d3 WHERE d3.plrid=d.plrid) * 100, 0) kills_scaled_pct",
			'kills_pct' 		=> "IFNULL(d.kills / (SELECT SUM(d2.kills) FROM $t_maps d2) * 100, 0) kills_pct",
		);

		return $sql;
	}
} 

?>