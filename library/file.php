<?php

require_once '../include/model.php';

class File extends Model {

	function File () {
	
		$this->_table = "file";
		$this->_index = "id";
		
		parent::Model();
	}
	
	function getByID($fileID) {
		$query = "SELECT f.id, f.category, f.owner, concat(substring(u.firstname,1,1),' ',u.lastname) ownerName, f.realname, 
						UNIX_TIMESTAMP(f.submitted) submitted, f.description, f.comment, f.type, ft.name typeName, f.file_size, 
						f.mime_type, f.hidden, c.name categoryName
				  FROM file f 
				  LEFT JOIN user u ON u.id = f.id 
				  LEFT JOIN category c ON c.id = f.category 
				  LEFT JOIN file_type ft ON ft.id = f.type
				  WHERE f.id = $fileID AND deleted = 0";
		return $this->db->getRow($query);
	}

	function getAllInCategory($categoryID) {

		$query = "SELECT file.id, realname, '' as url, file_size, file.description, iconFile, 
						allowedFileTypes.description as mime_description, inline, hidden, 'N' is_url
				  FROM file 
				  LEFT JOIN allowedFileTypes ON file.mime_type = allowedFileTypes.mime
				  WHERE file.category = " . $categoryID . " AND deleted = '0'
				  
				  UNION SELECT link.id, '', link.url, '', link.description, 'http-url.png', link.comment, '', hidden, 'Y' is_url
				  FROM link
				  WHERE link.category = " . $categoryID . "
				  
				  ORDER BY description";
		return $this->db->getAll($query);
	
	}
	

}	
?>