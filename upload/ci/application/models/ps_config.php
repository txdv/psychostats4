<?php
/**
 *	Main Psychostats ps_config model.
 */

class Ps_config extends MY_Model {
	protected $config = array();
	
	function Ps_config() {
		parent::MY_Model();
		$this->initialize('main');
	}
	
	function initialize($group = null, $section = null) {
		$this->db->select('cfg_group, cfg_section, cfg_var, cfg_value');
		if ($group) {
			$this->db->where('cfg_group', $group);
		}
		if ($section) {
			$this->db->where('cfg_section', $section);
		}
		
		$query = $this->db->get('config');
		foreach ($query->result() as $row) {
			$config[$row->cfg_var] = $row->cfg_value;
			if (!empty($row->cfg_section)) {
				$config[$row->cfg_group][$row->cfg_section][$row->cfg_var] = $row->cfg_value;
			} else {
				$config[$row->cfg_group][$row->cfg_var] = $row->cfg_value;
			}
		}
		print_r($config);
	}

	function __get($name) {
		
	}
}

?>