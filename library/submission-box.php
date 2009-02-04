<?php

require_once '../include/model.php';

class SubmissionBox extends Model {

	function SubmissionBox () {
	
		$this->_table = "submission_box";
		$this->_index = "id";
		
		parent::Model();
	}
}
?>