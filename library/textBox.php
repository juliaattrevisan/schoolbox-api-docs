<?php

class TextBox extends DeprecatedModel {

	function TextBox () {
		$this->_table = 'textBox';
		$this->_index = 'textBoxID';

		parent::DeprecatedModel();
	}

	function getAllByCategory($categoryID) {
		$query = "SELECT * FROM ".$this->_table." WHERE categoryID = $categoryID AND deleted = 'false' ORDER BY heading";
		return $this->db->getAll($query);
	}

	function getCurrent($categoryID) {
		$textBoxList = $this->getAllByCategory($categoryID);
		return $textBoxList[0];
	}

	function delete ($textBoxID) {
		$data = array('textBoxID'	=> $textBoxID,
					  'deleted'		=> 'true',
					  );
		return $this->update($data);
	}

	/**
	 * Soft-deletes content
	 *
	 * TODO: Refactor
	 *
	 * @param  $cid
	 * @return void
	 */
	public function homepageClean($cid) {
		$query = "UPDATE {$this->_table} SET deleted = 'true' WHERE categoryID = '{$cid}'";

		$this->db->query($query);
	}
}
