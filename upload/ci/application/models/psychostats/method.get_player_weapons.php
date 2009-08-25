<?php
/**
 * PsychoStats method get_player_weapons()
 * $Id$
 *
 * Fetches weapon stats for a single player.
 *
 */

class Psychostats_Method_Get_Player_Weapons extends Psychostats_Method {
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
		$t_weapons = $this->ps->tbl('c_plr_weapons', $gametype, $modtype);
		$t_weapon = $this->ps->tbl('weapon', false);

		$stats = $criteria['select'] ? $criteria['select'] : $this->get_sql();
		$fields = is_array($stats) ? implode(',', $stats) : $stats;
		
		$cmd =
<<<CMD
		SELECT $fields
		FROM ($t_weapons d, $t_weapon wpn)
		WHERE wpn.weaponid = d.weaponid AND d.plrid = ?
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

				// remove useless fields; since we selected more
				// then we truly need.
				unset($row['gametype'], $row['modtype'], $row['dataid']);

				$list[] = $row;
			}
		}
		$q->free_result();

		return $list;
	} 

	protected function get_sql() {
		$t_weapons = $this->ps->tbl('c_plr_weapons', $this->ps->gametype(), $this->ps->modtype());

		// non game specific stats
		$sql = array(
			'*' 			=> 'd.*',
			'wpn' 			=> 'wpn.*',
			'kills_scaled_pct' 	=> "IFNULL(d.kills / (SELECT MAX(d3.kills) FROM $t_weapons d3 WHERE d3.plrid=d.plrid) * 100, 0) kills_scaled_pct",
			'kills_pct' 		=> "IFNULL(d.kills / (SELECT SUM(d2.kills) FROM $t_weapons d2) * 100, 0) kills_pct",
		);
		return $sql;
	}
} 

?>
