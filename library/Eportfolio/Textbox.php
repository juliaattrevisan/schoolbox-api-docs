<?php

class Eportfolio_Textbox extends DeprecatedModel {
	public $_table = 'eportfolio_textBox';
	public $_index = 'textBoxID';

	/**
	 * Get a textbox record using only a componentLocationID
	 *
	 * @param  $componentLocationID
	 * @return array|bool
	 */
	public function getByComponentLocationID($componentLocationID) {
		$query = "SELECT * FROM eportfolio_textBox WHERE componentLocationID = '$componentLocationID'";

		return $this->db->getRow($query);
	}

	/**
	 * Delete a textbox only needing to know what the componentLocation is,
	 * since the textboxID could be empty in the case that they've created the
	 * component but not added content
	 *
	 * @param  $componentLocationID
	 * @return void
	 */
	public function delete($componentLocationID) {
		$query = "SELECT textBoxID FROM {$this->_table} WHERE componentLocationID = '$componentLocationID'";

		$record = $this->db->getRow($query);

		parent::delete($record['textBoxID']);
	}
}
