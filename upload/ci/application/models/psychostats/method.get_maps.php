<?php
/**
 * PsychoStats method get_maps()
 * $Id$
 *
 */
class Psychostats_Method_Get_Maps extends Psychostats_Method {
	/**
	 * Fetches a list of map stats for a specific game.
	 * 
	 * @param array $criteria
	 * 	Criteria that defines what maps will be returned.
	 * @param string $gametype
	 * 	Game type to fetch maps list for. Leave null for current
	 * 	default (from set_gametype).
	 * @param string $modtype
	 * 	Mod type to fetch maps list for. Leave null for current
	 * 	default (from set_gametype or set_modtype)
	 */
	public function execute($criteria = array(), $gametype = null, $modtype = null) {
		// set defaults
		$criteria += array(
			'limit' 	=> null,
			'start' 	=> null,
			'sort'		=> null,
			'order' 	=> null,
			'where' 	=> null,
			'select' 	=> null,
		);
		
		$ci =& get_instance();
		if ($gametype === null) {
			$gametype = $this->ps->gametype();
		}
		if ($modtype === null) {
			$modtype = $this->ps->modtype();
		}

		// setup table names
		$t_map = $this->ps->tbl('map', false);
		$c_map_data = $this->ps->tbl('c_map_data', $gametype, $modtype);

		$stats = $criteria['select'] ? $criteria['select'] : $this->get_sql();
		$fields = is_array($stats) ? implode(',', $stats) : $stats;

		// start basic query
		$cmd = "SELECT $fields FROM $t_map map, $c_map_data d WHERE ";

		// add join clause for tables
		$criteria['where'][] = 'd.mapid=map.mapid';
		
		// apply sql clauses
		$cmd .= $this->ps->where($criteria['where']);
		$cmd .= $this->ps->order_by($criteria['sort'], $criteria['order']);
		$cmd .= $this->ps->limit($criteria['limit'], $criteria['start']);

		$q = $ci->db->query($cmd);

		$list = array();
		if ($q->num_rows()) {
			foreach ($q->result_array() as $row) {
				$list[] = $row;
			}
		}
		$q->free_result();

		return $list;
	}

	protected function get_sql() {
		$c_maps = $this->ps->tbl('c_map_data');

		$sql = array(
			'*' => 'd.*',
			'map' => 'map.name',

			'online_time_scaled_pct' => "IFNULL(d.online_time / (SELECT MAX(online_time) FROM $c_maps) * 100, 0) online_time_scaled_pct",
			'online_time_pct' => "IFNULL(d.online_time / (SELECT SUM(online_time) FROM $c_maps) * 100, 0) online_time_pct",
			'kills_scaled_pct' => "IFNULL(d.kills / (SELECT MAX(kills) FROM $c_maps) * 100, 0) kills_scaled_pct",
			'kills_pct' => "IFNULL(d.kills / (SELECT SUM(kills) FROM $c_maps) * 100, 0) kills_pct",
		);
		
		return $sql;
	}

} 

?>
