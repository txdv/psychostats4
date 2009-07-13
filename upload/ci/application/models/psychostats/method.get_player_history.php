<?php
/**
 * PsychoStats method get_player_history()
 * $Id$
 *
 * Fetches the historic stats for a player.
 *
 */

class Psychostats_Method_Get_Player_History extends Psychostats_Method {
	public function execute($criteria = array(), $gametype = null, $modtype = null) {
		if (!is_array($criteria)) {
			$criteria = array( 'id' => $criteria );
		}
		// set defaults
		$criteria += array(
			'id'		=> 0,
			'sort'		=> 'kills',
			'order' 	=> 'desc',
			'limit' 	=> 31,
			'start' 	=> 0,
			'fields'	=> '*',		// fields to select
			'keyed'		=> false,	// if true data is keyed by statdate
			'fill_gaps'	=> false,	// fill in missing dates with null data? ('keyed' must be true)
			'start_date'	=> date('Y-m-d'),
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

		// determine what fields are going to be selected
		$fields = '*';
		if ($criteria['fields']) {
			$fields = '';
			$list = array_unique(array_map('trim', explode(',', $criteria['fields'])));
			foreach ($list as $f) {
				$fields .= $f != '*' ? $ci->db->protect_identifiers($f) . ',' : $f;
			}
			$fields = substr($fields, 0, -1);
		}

		$t_data = $this->ps->tbl('plr_data', false);
		$t_data2 = $this->ps->tbl('plr_data', $gametype, $modtype);
		
		// load the compiled stats
		$sql =
<<<CMD
		SELECT statdate,UNIX_TIMESTAMP(statdate) date,$fields
		FROM ($t_data d, $t_data2 d2)
		WHERE d.plrid = ? AND d.dataid=d2.dataid
CMD;

		$sql .= $this->ps->order_by($criteria['sort'], $criteria['order']);
		$sql .= $this->ps->limit($criteria['limit'], $criteria['start']);
		$q = $ci->db->query($sql, $id);

		$res = array();
		if ($q->num_rows()) {
			foreach ($q->result_array() as $row) {
				$row['missing'] = false;
				if ($criteria['keyed']) {
					$res[$row['statdate']] = $row;
				} else {
					$res[] = $row;
				}
			}
		}
		
		// fill in the days that are missing starting with 'start_day'
		// and working backwards.
		if ($criteria['fill_gaps'] and
		    $criteria['keyed'] and
		    $criteria['start_date'] and
		    $criteria['limit']) {
			// determine what stat keys are available
			$keys = array_keys(reset($res));
			unset($keys['statdate'], $keys['date']);
			
			// initialize the date
			$date = $criteria['start_date'];
			if (!is_numeric($date)) {
				$date = strtotime($date);
			}
			
			$last = reset($res);
			while (count($res) < $criteria['limit']) {
				$ymd = date('Y-m-d', $date);
				
				// Add the date if it doesn't exist
				if (!array_key_exists($ymd, $res)) {
					$res[$ymd] = array(
						'statdate' => $ymd,
						'date' => $date,
						'missing' => true
					);
					//$res[$ymd] += $last;
					// simulate no-data for this date
					foreach ($keys as $k) {
						if (!isset($res[$ymd][$k])) {
							$res[$ymd][$k] = null;
						}
					}
				}
				
				// rewind one day. I use this method to help avoid any
				// possible DST issues.
				$date = strtotime('yesterday', $date);
			}
			
			if ($criteria['order'] == 'desc') {
				krsort($res);
			} else {
				ksort($res);
			}
		}
		
		$q->free_result();

		return $res;
	} 
} 

?>