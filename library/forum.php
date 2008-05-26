<?php

require_once '../include/model.php';

class Forum extends Model {

	function Forum () {
	
		$this->_table = "forum";
		$this->_index = "id";
		
		parent::Model();
	}
	
	function getThreads($categoryID, $config) {
		$query = "SELECT thread, id, heading FROM forum WHERE cid='".$categoryID."' AND deleted = '0' GROUP BY thread ORDER BY date DESC";
		if($config['size'] != 0 && $config['size'] != '') 
			$query .= " LIMIT 0, {$config['size']}";  
		
		return $this->db->getAll($query);
	}
}
?>