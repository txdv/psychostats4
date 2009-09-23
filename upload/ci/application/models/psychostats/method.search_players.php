<?php
/**
 * PsychoStats method search_players()
 * $Id$
 *
 * Performs a search on the DB for players matching the criteria specified.
 * 
 * @param  string/array  $criteria
 * 	Search phrase string or an array of criteria parameters.
 * @param  string  $search_id
 * 	Optional search_id to use for this search. Of null one will be
 * 	generated automatically.
 * 
 * @return array 
 */
class Psychostats_Method_Search_Players extends Psychostats_Method {
	public function execute($criteria, $search_id = null) {
		$conf =& get_config();
		
		// convert criteria string to an array
		if (!is_array($criteria)) {
			$criteria = array( 'phrase' => $criteria );
		}

		$max_limit = coalesce($conf['search_limit'], 1000);
		
		// assign criteria defaults
		$criteria += array(
			// phrase: text phrase to search for
			'phrase'	=> null,
			// is_ranked: null=no filter, true=ranked only, false=unranked only
			'is_ranked'	=> null,
			// mode: contains, begins, ends, exact
			'mode'		=> 'contains',
			'limit'		=> $max_limit,
		);
		
		// 'limit' is forced based on current configuration
		if (!$criteria['limit'] or $criteria['limit'] > $max_limit) {
			$criteria['limit'] = $max_limit;
		}
	
		// do not allow blank phrases to be searched
		$criteria['phrase'] = trim($criteria['phrase']);
		if (is_null($criteria['phrase']) or $criteria['phrase'] == '') {
			return false;
		}
	
		// sanitize 'mode'
		$criteria['mode'] = strtolower($criteria['mode']);
		if (!in_array($criteria['mode'], array('contains', 'begins', 'ends', 'exact'))) {
			$criteria['mode'] = 'contains';
		}
	
		// sanitize 'is_ranked'
		if (!in_array($criteria['is_ranked'], array(null, true, false))) {
			$criteria['is_ranked'] = null;
		}
	
		// tokenize our search phrase
		$tokens = array();
		if ($criteria['mode'] == 'exact') {
			$tokens = array( $criteria['phrase'] );
		} else {
			$tokens = query_to_tokens($criteria['phrase']);
		}

		// build our WHERE clause
		$where = "";
		$inner = array();
		$outer = array();

		// loop through each field and add it to the 'where' clause.
		// Search plr, profile and ids
		foreach (array('plr.uniqueid', 'pp.name', 'pp.email',
			       'n.name', 'g.guid', 'ip.ipaddr')
			 as $field) {
			foreach ($tokens as $t) {
				if ($field == 'ip.ipaddr') {
					// Does the token phrase look like an
					// IPv4 IP address? 
					if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $t)) {
						$inner[] = "$field = INET_ATON('" . $this->ps->db->escape($t) . "')";
					} elseif (preg_match('/^\d+$/', $t)) {
						// allow a 32bit int for IP
						// matching too...
						$inner[] = "$field = $t";
					}
				} else {
					$token = $this->ps->token_to_sql($t, $criteria['mode']);
					$inner[] = "$field LIKE '$token'";
				}
			}
			if ($inner) {
				$outer[] = $inner;
			}
			$inner = array();
		}
	
		// combine the outer and inner clauses into a where clause
		foreach ($outer as $in) {
			$where .= " (" . join(" AND ", $in) . ") OR ";
		}
		$where = substr($where, 0, -4);		// remove the trailing " OR "

		$t_plr 			= $this->ps->tbl('plr', false);
		$t_plr_profile 		= $this->ps->tbl('plr_profile', false);
		$t_plr_ids_guid 	= $this->ps->tbl('plr_ids_guid', false);
		$t_plr_ids_ipaddr 	= $this->ps->tbl('plr_ids_ipaddr', false);
		$t_plr_ids_name 	= $this->ps->tbl('plr_ids_name', false);

		// perform search and find Jimmy Hoffa!
		// NOTE: SQL_CALC_FOUND_ROWS is MYSQL specific and would need to
		// be changed for other databases.
		$cmd  =
<<<CMD
			SELECT SQL_CALC_FOUND_ROWS DISTINCT plr.plrid
			FROM
				$t_plr plr,
				$t_plr_profile pp,
				$t_plr_ids_name n,
				$t_plr_ids_guid g,
				$t_plr_ids_ipaddr ip 
			WHERE
				plr.uniqueid=pp.uniqueid
				AND plr.plrid=n.plrid
				AND plr.plrid=g.plrid
				AND plr.plrid=ip.plrid
CMD;
		//$cmd = preg_replace('/^\s+/m', '', $cmd); // remove leading whitespace (I'm OCD)

		// filter results based for un/ranked players
		if (!is_null($criteria['is_ranked'])) {
			$cmd .= ' AND ';
			if ($criteria['is_ranked']) {
				$cmd .= $this->ps->is_ranked_sql;
			} else {
				$cmd .= $this->ps->is_not_ranked_sql;
			}
		}

		$cmd .= " AND ($where) ";
		$cmd .= " LIMIT " . $criteria['limit'];
		$q = $this->ps->db->query($cmd);
		
		$plrids = array();
		$total = 0;
		if ($q->num_rows()) {
			foreach ($q->result_array() as $r) {
				$plrids[] = $r['plrid'];
			}
		}
		$q->free_result();

		// find absolute total
		$q = $this->ps->db->query("SELECT FOUND_ROWS() total");
		$total = $q->num_rows() ? $q->row()->total : 0;
		$q->free_result();

		// delete any searches that are more than a few hours old
		$this->ps->delete_stale_searches();
	
		// save search results
		$results = array(
			'search_id'	=> $this->ps->is_search($search_id) ? $search_id : $this->ps->init_search(),
			'session_id'	=> $this->ps->session->userdata('session_id'),
			'phrase'	=> $criteria['phrase'],
			'result_total'	=> count($plrids),
			'abs_total'	=> $total,
			'results'	=> join(',', $plrids),
			'query'		=> preg_replace('/^\s+/m', '', $cmd),
			'updated'	=> date('Y-m-d H:i:s'),
		);
		$ok = $this->ps->save_search($results);

		// the results should be an array, not a string		
		$results['results'] = $plrids;
		
		return $ok ? $results : false;
	} 
} 

?>