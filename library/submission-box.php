<?php

require_once '../include/model.php';

class SubmissionBox extends Model {

	function SubmissionBox () {
	
		$this->_table = "submission_box";
		$this->_index = "id";
		
		parent::Model();
	}
	
	function delete($id, $clean = false) {
		return $this->update(array('id'=>$id,'deleted'=>1));
	}
	
	function homepageClean($cid) {
		return $this->db->update($this->_table, 'category', array('category'=>$cid, 'deleted'=>1));
	}
}
?>