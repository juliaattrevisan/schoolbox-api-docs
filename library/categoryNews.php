<?php

require_once $_SERVER['DOCUMENT_ROOT'] .'/../include/model.php';

class CategoryNews extends Model {

	function CategoryNews () {
	
		$this->_table = "subject_news";
		$this->_index = "id";
		
		parent::Model();
	}

	function getAllForCategory($categoryID, $archive = false) {
		$query = "SELECT n.id, heading, UNIX_TIMESTAMP(datetime_posted) posted, 
					UNIX_TIMESTAMP(datetime_expires) expires, body, deleted, code, owner, u.firstname, u.lastname, u.title, u.id as uid
				  FROM ".$this->_table." n
				  LEFT JOIN user u ON u.id = n.owner 
				  WHERE code = '$categoryID'  AND deleted = 0 ";
		if ($archive)
			$query .= "AND datetime_expires < CURRENT_DATE()";
    	else
			$query .= "AND datetime_expires > CURRENT_DATE()";

		$query .= "ORDER BY datetime_posted DESC";

		return $this->db->getAll($query);
	}
	
	function homepageClean($cid) {
		$this->db->delete('subject_news', 'code', $cid);
	}
	
	function create($news) {
		$news['datetime_posted'] = 'NOW()';
		parent::create($news);
	}
	
	function delete($id) {
		$this->update(array('id','deleted'=>1));
	}
}

?>