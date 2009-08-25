<?php
/**
 * PsychoStats method get_role_stats()
 * $Id$
 *
 * Fetches the basic stats for a single role.
 *
 */

class Psychostats_Method_Get_Role_Stats
extends Psychostats_Method {
	public function execute($criteria = array(), $gametype = null, $modtype = null) {
		$ci =& get_instance();
		if (!is_array($criteria)) {
			$criteria = array( 'id' => $criteria );
		}
		// set defaults
		$criteria += array(
			'id' 		=> 0,
			'select'	=> null,
		);
		$id = isset($criteria['id']) ? $criteria['id'] : 0;
		
		$res = array();

		if (!$gametype) {
			$g = $this->ps->get_role_gametype($id);
			if (!$g) {
				return false;
			}
			list($gametype, $modtype) = $g;
		}

		$t_role_data = $this->ps->tbl('c_role_data', $gametype, $modtype);

		$stats = $criteria['select'] ? $criteria['select'] : $this->get_sql();
		$fields = is_array($stats) ? implode(',', $stats) : $stats;

		$cmd = "SELECT $fields FROM $t_role_data d WHERE roleid=?";
		$q = $ci->db->query($cmd, $id);

		$res = false;
		if ($q->num_rows()) {
			$res = $q->row_array();
			unset($res['roleid']); // remove useless column
		}
		$q->free_result();

		return $res;
	} 

	protected function get_sql() {
		// non game specific stats
		$sql = array(
			'*' => 'd.*',
			'headshot_kills_pct' => 'ROUND(IFNULL(headshot_kills / kills * 100, 0), 0) headshot_kills_pct',
		);
		return $sql;
	}
} 

?>