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
	
	function initialize($conftype = null, $section = null) {
		$this->db->select('conftype, section, var, value');
		$this->db->where('var IS NOT NULL');
		if ($conftype) {
			$this->db->where('conftype', $conftype);
		}
		if ($section) {
			$this->db->where('section', $section);
		}
		
		$query = $this->db->get('config');
		foreach ($query->result() as $row) {
			if (!empty($row->section)) {
				$config[$row->conftype][$row->section][$row->var] = $row->value;
			} else {
				$config[$row->conftype][$row->var] = $row->value;
			}
		}
	}

	function __get($name) {
		
	}
}

?>