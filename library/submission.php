<?php

require_once '../include/model.php';

class Submission extends Model {

	function Submission () {
	
		$this->_table = "submission_box";
		$this->_index = "id";
		
		parent::Model();
	}
}
?>