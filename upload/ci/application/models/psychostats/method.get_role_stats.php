<?php
/**
 * PsychoStats method get_role_stats()
 * $Id$
 *
 * Fetches the basic stats for a single role.
 *
 */

class Psychostats_Method_Get_Role_Stats extends Psychostats_Method {
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
			$g = $this->ps->get_role_gametype($id);
			if (!$g) {
				return false;
			}
			list($gametype, $modtype) = $g;
		}

		$t_role_data = $this->ps->tbl('c_role_data', $gametype, $modtype);

		// non game specific stats
		$stats = array(
			'd.*',
		);

		// allow game::mod specific stats to be added
		if ($meth = $this->ps->load_overloaded_method('get_role_stats_sql', $gametype, $modtype)) {
			$meth->execute($stats);
		}
		
		// combine everything into a string for our query
		$fields = implode(',', $stats);
		
		$sql = "SELECT $fields FROM $t_role_data d WHERE roleid = ? LIMIT 1";
		$q = $ci->db->query($sql, $id);

		$res = false;
		if ($q->num_rows()) {
			$res = $q->row_array();
			unset($res['roleid']);
		}
		$q->free_result();

		return $res;
	} 
} 

?>
