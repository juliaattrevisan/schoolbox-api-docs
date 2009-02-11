<?php

require_once '../include/model.php';

class Link extends Model {

	function Link () {
	
		$this->_table = "link";
		$this->_index = "id";
		
		parent::Model();
	}

	function getByID($linkID) {
		$query = "SELECT l.*, concat(substring(u.firstname,1,1),' ',u.lastname) ownerName,
						UNIX_TIMESTAMP(l.submitted) submitted, c.name categoryName 
				  FROM link l 
				  LEFT JOIN user u ON u.id = l.owner
				  LEFT JOIN category c ON c.id = l.category 
				  WHERE l.id = $linkID";
		return $this->db->getRow($query);
	}

	
	function getAllInCategory($categoryID, $config = false) {
		$query = "SELECT l.*, concat(substring(u.firstname,1,1),' ',u.lastname) ownerName, UNIX_TIMESTAMP(submitted) submitted, 'http-url.png' iconFile 
				  FROM link l
				  LEFT JOIN user u ON u.id = l.owner
				  WHERE category = '".$categoryID."'";

		if (perm($categoryID, HOMEPAGESERVICE) < 2)
			$query .= " AND l.hidden = 0";	

		$sortFields = array('Name' => 'l.description', 'Date' => 'l.submitted');
		$config['sort'] = isset($sortFields[$config['sort']]) ? $config['sort'] : 'Name';
		$config['sortdir'] = strtoupper($config['sortdir']) == 'DESC' ? 'DESC' : 'ASC';

		$query .= " ORDER BY " . $sortFields[$config['sort']] . " " . $config['sortdir'];
		
		if($config['size'] > 0) 
			$query .= " LIMIT 0, " . $config['size']; 
			
							  
		return $this->db->getAll($query);
	}
}
?>