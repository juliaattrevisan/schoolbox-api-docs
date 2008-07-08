<?php

require_once '../include/model.php';

class CategoryNews extends Model {

	function CategoryNews () {
	
		$this->_table = "subject_news";
		$this->_index = "id";
		
		parent::Model();
	}

	function getAllForCategory($categoryID) {
		$query = "SELECT n.id, heading, UNIX_TIMESTAMP(datetime_posted) posted, 
					UNIX_TIMESTAMP(datetime_expires) expires, body, deleted, code, owner, u.firstname, u.lastname, u.title, u.id as uid
				  FROM ".$this->_table." n
				  LEFT JOIN user u ON u.id = n.owner 
				  WHERE code = '$categoryID'  AND datetime_expires > NOW() AND deleted = 0
    			  ORDER BY datetime_posted DESC";

		return $this->db->getAll($query);
	}
}

?>