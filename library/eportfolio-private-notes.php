<?php

class ePortfolioPrivateNotes extends DeprecatedModel {

	function ePortfolioPrivateNotes () {
		$this->_table = 'eportfolio_privateNotes';
		$this->_index = 'uid';

		parent::DeprecatedModel();
	}

    /**
     * Gets all the private notes records associated with the specified user.
     *
     * @param <type> $userID
     * @return <type>
     */
	function getByID($userID) {
		$query = 'SELECT * FROM ' . $this->_table . " WHERE uid = $userID";

		return $this->db->getRow($query);
	}

    /**
     * Updates a person's Private Notes
     *
     * @param <type> $privateNotes
     */
	function update($privateNotes) {
        parent::update($privateNotes);
	}
}
?>