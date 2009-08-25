<?php
/**
 * PsychoStats method get_player_total()
 * $Id$
 *
 * Fetches the total count of a list for a particular player.
 * IE: Total sessions, weapons, maps, roles or victims
 */

class Psychostats_Method_Get_Player_Total extends Psychostats_Method {
	public function execute($id, $table, $gametype = null, $modtype = null) {

		$ci =& get_instance();
		$res = array();

		// make sure a valid table list is specified
		if (!in_array($table, array(
		    'maps','roles','sessions','victims','weapons',
		    'ids_guid','ids_ipaddr','ids_name'
		    ))) {
			return null;
		}

		if (!$gametype) {
			$g = $this->ps->get_player_gametype($id);
			if (!$g) {
				return false;
			}
			list($gametype, $modtype) = $g;
		}
		
		if (in_array($table, array('sessions','ids_guid','ids_ipaddr','ids_name'))) {
			$tbl = $this->ps->tbl('plr_' . $table, false);
		} else {
			$tbl = $this->ps->tbl('c_plr_' . $table, $gametype, $modtype);
		}
		
		$cmd = "SELECT COUNT(plrid) total FROM $tbl WHERE plrid=?";
		
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