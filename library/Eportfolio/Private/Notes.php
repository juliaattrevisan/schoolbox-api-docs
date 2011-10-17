<?php
/**
 * Eportfolio_Private_Notes
 *
 * Ported existing privateNotes handler into actual class
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Eportfolio_Private_Notes extends Eportfolio_Private_Abstract {
	public $_table = 'eportfolio_privateNotes';
	public $_index = 'uid';
	private $canEditPrivateNotes = false;

	/**
	 * Render component
	 *
	 * @return void
	 */
	public function render() {
		$this->getPage()->setBlock(TEMPLATE_content, 'privateNotes', 'privateNotes_block');

		$data = $this->getByID($this->getUserId());

		$this->getPage()->setVar(array(
			'ID' => $this->getUserId(),
			'PRIVATE_NOTES' => $data['privateNotes']
		));

		/* Show private notes edit functionality if allowed
		 */
		if ($this->getCanEditPrivateNotes()) {
			$this->getPage()->parse('privateNotes_block', 'privateNotes');
		}
		else {
			$this->getPage()->setVar('privateNotes_block', '');
		}
	}

	/**
	 * Render modify screen
	 *
	 * @return void
	 */
	public function renderModify() {
		$data = $this->getByID($this->getUserId());

		$this->getPage()->setFile(TEMPLATE_content, 'eportfolio/components/private/notes/modify.html');

		$this->getPage()->setVar(array(
			'ID' => $this->getUserId(),
			'PRIVATE_NOTES' => $data['privateNotes']
		));
	}

    /**
     * Gets all the private notes records associated with the specified user.
     *
     * @param <type> $userID
     * @return <type>
     */
	public function getByID($userID) {
		$query = "SELECT * FROM {$this->_table} WHERE uid = $userID";

		return $this->db->getRow($query);
	}

    /**
     * Updates a person's Private Notes
     *
     * @param <type> $privateNotes
     */
	public function update($privateNotes) {
		$existing = $this->getByID($this->getUserId());

		if ($existing) {
	        parent::update($privateNotes);
		}
		else {
			parent::create($privateNotes);
		}

		SitePage::localRedir('/eportfolio/' . $this->getUserId());
	}

	/**
	 * Set value for editing private notes
	 *
	 * @param  $val
	 * @return void
	 */
	public function setCanEditPrivateNotes($val) {
		$this->canEditPrivateNotes = $val;
	}

	/**
	 * Get value for editing private notes
	 *
	 * @return bool
	 */
	private function getCanEditPrivateNotes() {
		return $this->canEditPrivateNotes;
	}
}