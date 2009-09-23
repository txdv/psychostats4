<?php
/**
 * PsychoStats method get_total_clans()
 * $Id$
 *
 * Returns the total clans that have stats based on the criteria given.
 *
 */
class Psychostats_Method_Get_Total_Clans extends Psychostats_Method {
	public function execute($criteria = array(), $gametype = null, $modtype = null) {
		// set defaults
		if (!is_array($criteria)) {
			$criteria = array();
		}
		$criteria += array(
			'where' 	=> null,
			'ranked_only'	=> null,
			'min_members'	=> null,
			'is_ranked' 	=> false,	// false
			'is_plr_ranked'	=> false,	// false
		);

		if ($gametype === null) {
			$gametype = $this->ps->gametype();
		}
		if ($modtype === null) {
			$modtype = $this->ps->modtype();
		}

		$t_clan = $this->ps->tbl('clan', false);
		$t_plr = $this->ps->tbl('plr', false);
		
		// start basic query
		$cmd =
<<<CMD
		SELECT COUNT(DISTINCT plr.clanid) total
		FROM ($t_plr plr,$t_clan clan)
		WHERE
CMD;
		
		// add join clause for tables
		$criteria['where'][] = 'plr.clanid=clan.clanid';

		// apply is_ranked shortcut
		if ($criteria['ranked_only'] || $criteria['is_ranked']) {
			$criteria['where'][] = $this->ps->is_clan_ranked_sql;
		}
		if ($criteria['ranked_only'] || $criteria['is_plr_ranked']) {
			$criteria['where'][] = $this->ps->is_ranked_sql;
		}

		// limiting the total clans based on minimum members is kinda
		// messy but I can't come up with a better way to do this yet.
		// (not without pre-compiling clan data).
		if ($criteria['min_members']) {
			$ranked = '';
			if ($criteria['ranked_only'] || $criteria['is_plr_ranked']) {
				$ranked = 'AND ' . $this->ps->is_ranked_sql('p');
			}
			
			$criteria['where'][] =
<<<CMD
			(SELECT COUNT(DISTINCT p.plrid)
			FROM $t_plr p
			WHERE p.clanid=plr.clanid $ranked) >= {$criteria['min_members']}
CMD;
		}

		$cmd .= $this->ps->where($criteria['where']);
		//print "$cmd<br/>\n";
		
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