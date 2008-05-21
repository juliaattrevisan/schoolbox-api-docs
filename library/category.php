<?php

require_once '../include/model.php';

class Category extends Model {

	function Category () {
	
		$this->_table = "category";
		$this->_index = "id";
		
		parent::Model();
	}
	
	function getCategoryList ($categoryID) {
		$query = "SELECT id, name, code, template, (SELECT count(*) FROM category WHERE parent = c.id) as children
				  FROM category c
				  WHERE parent = $categoryID ORDER BY name";
		return $this->db->getAll($query);
	}
	

	function getParent($categoryID) {
		$query = "SELECT parent FROM category WHERE id = $categoryID";
		return $this->db->getOne($query);
	}
	
	function getByID($categoryID) {
		$query = "SELECT c.*, hc.* FROM category c, homepage_config hc WHERE hc.cid = c.id AND c.id = '$categoryID'"; 
		return $this->db->getRow($query);		
	}
	
	function logPageView($categoryID) {
		$query = "SELECT datetime FROM homepage_log WHERE cid = '". $categoryID. "' AND user = '".$_SESSION['id']."'";
		$this->db->query($query);
		
		if($row = $this->db->getNextArray() ) {
			$query = "UPDATE homepage_log SET datetime = NOW(), hitcount = hitcount + 1 WHERE cid = '" .$categoryID ."'
			AND user = '".$_SESSION['id']."'";
		}
		else
		{
			$query = "INSERT INTO homepage_log (datetime, user, cid,hitcount) VALUES ( NOW() , 
			'".$_SESSION['id']."', '" . $categoryID . "', '0')";
		}
		
		$this->db->query($query);	
	}
	
	function getComponents($categoryID) {

		$query = "SELECT position, `column`, component, homepage_comp_location.config, name, method, filename 
				  FROM homepage_comp_location, homepage_comp 
				  WHERE component = id AND cid = '" . $categoryID . "'
				  ORDER BY position";
		return $this->db->getAll($query);
	
	}
	
	function getImages($categoryID) {
		$query = "SELECT id, file.description 
				  FROM file 
				  LEFT JOIN allowedFileTypes ON mime_type = mime 
				  WHERE category = '{$categoryID}' 
				  AND deleted = '0' AND INSTR(mime_type, 'image/') AND inline = 'true'
				  ORDER BY submitted";
		return $this->db->getAll($query);
	}
}

?>