<?php

require_once '../include/model.php';

class Link extends Model {

	function Link () {
	
		$this->_table = "link";
		$this->_index = "id";
		
		parent::Model();
	}
	
	function getAllInCategory($categoryID, $config = false) {
		$query = "SELECT l.*, concat(substring(u.firstname,1,1),' ',u.lastname) ownerName, UNIX_TIMESTAMP(submitted) submitted, 'http-url.png' iconFile 
				  FROM link l
				  LEFT JOIN user u ON u.id = l.owner
				  WHERE category = '".$categoryID."'";

		if (perm($categoryID, HOMEPAGESERVICE) < 2)
			$query .= " AND l.hidden = 0";	

		$sortFields = array('name' => 'l.description', 'date' => 'l.submitted');
		$config['sort'] = in_array($config['sort'], $sortFields) ? $config['sort'] : 'l.submitted';
		$config['sortdir'] = strtoupper($config['sortdir']) == 'DESC' ? 'DESC' : 'ASC';

		$query .= " ORDER BY " . $config['sort'] . " " . $config['sortdir'];
		
		if($config['size'] > 0) 
			$query .= " LIMIT 0, " . $config['size']; 
			
							  
		return $this->db->getAll($query);
	}
}
?>