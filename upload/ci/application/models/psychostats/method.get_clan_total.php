<?php
/**
 * PsychoStats method get_clan_total()
 * $Id$
 *
 * Fetches the total count of a list for a particular clan.
 * IE: Total members, weapons, maps, roles or victims
 */

class Psychostats_Method_Get_Clan_Total extends Psychostats_Method {
	public function execute($criteria, $table, $gametype = null, $modtype = null) {
		$ci =& get_instance();
		if (!is_array($criteria)) {
			$criteria = array( 'id' => $criteria );
		}
		// set defaults
		$criteria += array(
			'id'		=> 0,
			'where'		=> null,
			'is_ranked'	=> true,	// true
		);
		$id = isset($criteria['id']) ? $criteria['id'] : 0;

		// make sure a valid table list is specified
		if (!in_array($table, array(
		    'players','maps','roles','victims','weapons',
		    'ids_guid','ids_ipaddr','ids_name'
		    ))) {
			return false;
		}

		if (!$gametype) {
			$g = $this->ps->get_clan_gametype($id);
			if (!$g) {
				return false;
			}
			list($gametype, $modtype) = $g;
		}
		
		$t_plr = $this->ps->tbl('plr', false);

		if ($table == 'players') {
			$cmd = "SELECT COUNT(*) total FROM $t_plr plr WHERE ";
		} else {
			$key = '';
			if (in_array($table, array('ids_guid','ids_ipaddr','ids_name'))) {
				$tbl = $this->ps->tbl('plr_' . $table, false);
				$key = substr($table, 3);
			} else {
				$tbl = $this->ps->tbl('c_plr_' . $table, $gametype, $modtype);
				$key = substr($table, 0, -1) . 'id';
			}

			$cmd =
<<<CMD
				SELECT COUNT(DISTINCT $key) total
				FROM $tbl d, $t_plr plr
				WHERE d.plrid=plr.plrid AND 
CMD;
		}
		$cmd .= "plr.clanid=? ";
		
		// apply is_ranked shortcut
		if ($criteria['is_ranked']) {
			$criteria['where'][] = $this->ps->is_ranked_sql;
		}

		$cmd .= $this->ps->where($criteria['where'], 'AND', true, ' AND ');
		//print "$cmd<br/>\n";

		$q = $ci->db->query($cmd, $id);

		$res = 0;
		if ($q->num_rows()) {
			$res = $q->row();
			$res = $res->total;
		}
		$q->free_result();
		
		return $res;
	} 
} 

?>