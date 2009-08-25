<?php
/**
 * PsychoStats method get_min_max_dates()
 * $Id$
 *
 * Returns the MIN and MAX dates found in the database for the game/mod.
 * @param boolean $as_unixtime If true a unix timestamp is returned otherwise a
 *			       DATE string is returned YYYY-MM-DD
 *
*/

class Psychostats_Method_Get_Min_Max_Dates extends Psychostats_Method {
	public function execute($gametype, $modtype, $as_unixtime = false) {

		$t_map  = $this->ps->tbl('map', false);
		$t_maps = $this->ps->tbl('map_data', false);

		if ($as_unixtime) {
			$min = "UNIX_TIMESTAMP(MIN(statdate))";
			$max = "UNIX_TIMESTAMP(MAX(statdate))";
		} else {
			$min = "MIN(statdate)";
			$max = "MAX(statdate)";
		}

		$cmd =
<<<CMD
		SELECT $min min,$max max
		FROM $t_maps d, $t_map m
		WHERE d.mapid=m.mapid AND gametype=? AND modtype
CMD;
		$cmd .= $modtype ? '=?' : ' IS ?';
		
		$ci =& get_instance();
		$q = $ci->db->query($cmd, array($gametype, $modtype));

		$res = array();
		if ($q->num_rows()) {
			$res = $q->row_array();
		}
		return $res;
	} 
} 

?>