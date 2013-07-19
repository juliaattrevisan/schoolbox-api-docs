<?php
/**
 * Leave_Kings_Notice_Parent_Rejected
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Leave_Kings_Notice_Parent_Rejected extends Leave_Kings_Notice_Parent_Abstract {

	/**
	 * Setup
	 */
	public function setup() {

		/* Common replacements
		 */
		parent::setup();

		/* Custom replacements
		 */
		$this->getEmail()->setVar(array(
			'LEAVE_REJECTED_BY' => $this->getRequest()->getAuthorisationUser()->formatName(),
		));

		$this->getEmail()->setVar('submitted_by_block', '');
		$this->getEmail()->setVar('authorised_by_block', '');
		$this->getEmail()->parse('rejected_by_block', 'rejected_by');
	}
}
