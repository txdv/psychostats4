<?php
/**
 * PsychoStats method get_clan()
 * $Id$
 *
 * Fetches a single clan record.
 *
 */

class Psychostats_Method_Get_Clan extends Psychostats_Method {
	public function execute($criteria = array()) {
		$ci =& get_instance();
		if (!is_array($criteria)) {
			$criteria = array( 'id' => $criteria );
		}
		// set defaults
		$criteria += array(
			'id'		=> 0,
			'select'	=> null,
		);
		$id = isset($criteria['id']) ? $criteria['id'] : 0;

		$t_clan		= $this->ps->tbl('clan', false);
		$t_profile	= $this->ps->tbl('clan_profile', false);

		$stats = $criteria['select'] ? $criteria['select'] : $this->get_sql();
		$fields = is_array($stats) ? implode(',', $stats) : $stats;

		$cmd = 
<<<CMD
		SELECT $fields
		FROM ($t_clan clan)
		LEFT JOIN $t_profile cp ON cp.clantag = clan.clantag
		WHERE clan.clanid = ?
CMD;

		$q = $ci->db->query($cmd, $id);

		if ($q->num_rows() == 0) {
			// not found
			return false;
		}

		$res = $q->row_array();
		$q->free_result();

		// sanitize the clantag
		//$res['clantag'] = htmlentities($res['clantag'], ENT_NOQUOTES, 'UTF-8');

		return $res;
	}
	
	protected function get_sql() {
		$sql = array(
			'clan' 			=> 'clan.*',
			'cp'			=> 'cp.*',
		);
		return $sql;
	}
} 

?>
