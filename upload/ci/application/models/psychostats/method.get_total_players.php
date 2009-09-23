<?php
/**
 * PsychoStats method get_total_players()
 * $Id$
 *
 * Returns the total players that have stats based on the criteria given.
 *
 */
class Psychostats_Method_Get_Total_Players extends Psychostats_Method {
	public function execute($criteria = array(), $gametype = null, $modtype = null) {
		// set defaults
		if (!is_array($criteria)) {
			$criteria = array();
		}
		$criteria += array(
			'where' 	=> null,
			'is_ranked' 	=> false,	// false
			'search'	=> null,
		);

		if ($gametype === null) {
			$gametype = $this->ps->gametype();
		}
		if ($modtype === null) {
			$modtype = $this->ps->modtype();
		}

		$t_plr = $this->ps->tbl('plr', false);
		$c_plr_data = $this->ps->tbl('c_plr_data', $gametype, $modtype);
		
		// start basic query
		$cmd = "SELECT COUNT(*) total FROM $t_plr plr,$c_plr_data d WHERE ";
		
		// add join clause for tables
		$criteria['where'][] = 'd.plrid=plr.plrid';

		// apply is_ranked shortcut
		if ($criteria['is_ranked']) {
			$criteria['where'][] = $this->ps->is_ranked_sql;
		}

		// apply search results
		if ($criteria['search']) {
			$matched = array();
			if (is_array($criteria['search'])) {
				// search is a full result array
				$matched = $criteria['search']['results'];
			} else {
				// search is a search_id string
				$search = $this->ps->get_search($criteria['search']);
				if ($search) {
					$matched = $search['results'];
				}
			}

			if ($matched) {
				// limit matches based on search results
				$criteria['where'][] = 'plr.plrid IN (' . join(',',$matched) . ')';
			} else {
				// force an empty result set since the search
				// didn't have any results
				$criteria['where'] = array( '0=1' );
			}
			unset($matched);
		}

		$cmd .= $this->ps->where($criteria['where']);

		$q = $this->ps->db->query($cmd);

		$count = 0;
		if ($q->num_rows()) {
			$res = $q->row_array();
			$count = $res['total'];
		}
		$q->free_result();

		return $count;
	} 
} 

?>