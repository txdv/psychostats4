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
		$t_weapons = $this->ps->tbl('c_plr_weapons', $gametype, $modtype);
		$t_weapon = $this->ps->tbl('weapon', false);
		
		// load the compiled stats
		$sql =
<<<CMD
		SELECT w.name, w.full_name, w.weight, w.class, d.*,
		IFNULL(d.kills / (SELECT MAX(d3.kills) FROM $t_weapons d3 WHERE d3.plrid=d.plrid) * 100, 0) kills_scaled_pct,
		IFNULL(d.kills / d2.kills * 100, 0) kills_pct
		FROM ($t_weapons d, $t_weapon w)
		LEFT JOIN $t_data d2 ON d2.plrid=d.plrid
		WHERE w.weaponid = d.weaponid AND d.plrid = ?
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