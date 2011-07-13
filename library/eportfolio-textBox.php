<?php

class ePortfolioTextBox extends DeprecatedModel {

	function ePortfolioTextBox () {
		$this->_table = 'eportfolio_textBox';
		$this->_index = 'textBoxID';

		parent::DeprecatedModel();
	}

    /**
     * Gets all the textboxes associated with the specified user.
     * @param <type> $userID
     * @return <type>
     */
	function getAllByUser($userID) {
		$query = "SELECT * FROM ".$this->_table." WHERE userID = $userID AND deleted = 'false' ORDER BY heading";

		return $this->db->getAll($query);
	}

    /**
     * Get the latest textbox.
     *
     * @param <type> $userID
     * @return <type>
     */
	function getCurrent($userID) {
		$textBoxList = $this->getAllByUser($userID);

		return $textBoxList[0];
	}

    /**
     * Deletes a textbox.
     * 
     * @param <type> $textBoxID
     * @return <type>
     */
	function delete($textBoxID) {
		$data = array(
            'textBoxID' => $textBoxID,
		    'deleted' => 'true',
        );

		return $this->update($data);
	}
}
?>