<?php

require_once '../include/model.php';

class Duty extends Model {

	function Duty () {
	
		$this->_table = "duty";
		$this->_index = "dutyID";
		
		parent::Model();
	}
	
	function getAllInCategory($categoryID) {
		$query = "SELECT l.*, d.user, d.date, d.cid FROM luDuties l LEFT JOIN duty d ON l.dutyTypeID = d.dutyTypeID 
			and d.date = '".date('Y-m-d')."' where l.cid = ".$categoryID ;	
		return $this->db->getAll($query);
	}
}
?>