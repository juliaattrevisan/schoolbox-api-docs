<?php

require_once '../include/model.php';

class File extends Model {

	function File () {
	
		$this->_table = "file";
		$this->_index = "id";
		
		parent::Model();
	}
	
	function getByID($fileID) {
		$query = "SELECT f.*, concat(substring(u.firstname,1,1),' ',u.lastname) ownerName, 
						UNIX_TIMESTAMP(f.submitted) submitted, ft.name typeName, c.name categoryName, af.description as mimeDescription, iconFile
				  FROM file f 
				  LEFT JOIN user u ON u.id = f.id 
				  LEFT JOIN category c ON c.id = f.category 
				  LEFT JOIN file_type ft ON ft.id = f.type
				  LEFT JOIN allowedFileTypes af ON f.mime_type = af.mime
				  WHERE f.id = $fileID AND deleted = 0";
		return $this->db->getRow($query);
	}

	function getAllInCategory($categoryID, $config = false) {

		$query = "SELECT f.*, concat(substring(u.firstname,1,1),' ',u.lastname) ownerName, 
						UNIX_TIMESTAMP(f.submitted) submitted, ft.name typeName, c.name categoryName, af.description as mimeDescription, iconFile
				  FROM file f 
				  LEFT JOIN user u ON u.id = f.owner
				  LEFT JOIN category c ON c.id = f.category 
				  LEFT JOIN file_type ft ON ft.id = f.type
				  LEFT JOIN allowedFileTypes af ON f.mime_type = af.mime
				  LEFT JOIN submission_box sb on sb.fid = f.id
				  WHERE f.category = " . $categoryID . " AND f.deleted = '0' AND sb.id IS NULL";
		
		if (perm($categoryID, HOMEPAGESERVICE) < 2)
			$query .= " AND f.hidden = 0";				  
		
		$sortFields = array('name' => 'f.description', 'date' => 'f.submitted');
		$config['sort'] = in_array($config['sort'], $sortFields) ? $config['sort'] : 'f.submitted';
		$config['sortdir'] = strtoupper($config['sortdir']) == 'DESC' ? 'DESC' : 'ASC';

		$query .= " ORDER BY " . $config['sort'] . " " . $config['sortdir'];
		
		if($config['size'] > 0) 
			$query .= " LIMIT 0, " . $config['size']; 

		return $this->db->getAll($query);
	
	}
	

}	
?>