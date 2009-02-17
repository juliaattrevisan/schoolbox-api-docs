<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';

class MyFolder extends Model {

	function MyFolder ($owner = 0) {
		if($owner == 0)
			$this->owner = $_SESSION['id'];
		else
			$this->owner = $owner;
			
		$this->_table = "myFolder";
		$this->_index = "id";
		
		parent::Model();
	}
	
	function getFolderList($parent = 0) {
		$fields = array('owner'=>$this->owner,
						'parent'=>$parent,
						);
		return $this->db->getByArray($fields);
	
	
	}
}

?>
