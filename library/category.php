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
}

?>