<?php
/**
 * Leave_Core_Notice_User_Submitted
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Leave_Core_Notice_User_Submitted extends Leave_Core_Notice_User_Abstract {

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
			'LEAVE_SUBMITTED_BY' => $this->getRequest()->getUser()->formatName(),
		));

		$this->getEmail()->parse('submitted_by_block', 'submitted_by');
		$this->getEmail()->setVar('authorised_by_block', '');
		$this->getEmail()->setVar('rejected_by_block', '');
	}

	/**
	 * Send it
	 */
	public function send() {
		$this->getEmail()->send();
	}
}
