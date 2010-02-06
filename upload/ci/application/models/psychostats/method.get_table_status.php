<?php
/**
 * PsychoStats method get_table_status()
 * $Id$
 *
 *
 */

class Psychostats_Method_get_table_status extends Psychostats_Method {
	public function execute($keyed = false) {
		$ci =& get_instance();

		// get table status for any table that contains our DB prefix
		$cmd = sprintf("SHOW TABLE STATUS LIKE '%s_%%'", $this->db->dbprefix);
		$q = $ci->db->query($cmd);

		$res = array();
		if ($q->num_rows()) {
			$i = 0;
			foreach ($q->result_array() as $row) {
				// We only retain certain 'useful' information
				$res[ $keyed ? $row['Name'] : $i++ ] = array(
					'name'		=> $row['Name'],
					//'engine'	=> $row['Engine'],
					//'format'	=> $row['Row_format'],
					'rows'		=> $row['Rows'],
					//'avg_row_length'=> $row['Avg_row_length'],
					'data_length'	=> $row['Data_length'],
					'index_length'	=> $row['Index_length'],
					'data_free'	=> $row['Data_free'],	// overhead
					'auto_increment'=> $row['Auto_increment'],
					'create_time'	=> $row['Create_time'],
					'update_time'	=> $row['Update_time'],
					'check_time'	=> $row['Check_time'],
				);
			}
		}
		$q->free_result();

		return $res;
	} 
} 

?>
