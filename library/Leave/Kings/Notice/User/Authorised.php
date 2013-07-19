<?php
/**
 * Leave_Kings_Notice_User_Authorised
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Leave_Kings_Notice_User_Authorised extends Leave_Kings_Notice_User_Abstract {

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
			'LEAVE_AUTHORISED_BY' => $this->getRequest()->getAuthorisationUser()->formatName(),
			'LEAVE_CONFIRMED_BY' => $this->getRequest()->getConfirmedByMapIndex($this->getRequest()->getConfirmedBy()),
		));

		$this->getEmail()->setVar('submitted_by_block', '');
		$this->getEmail()->parse('authorised_by_block', 'authorised_by');
		$this->getEmail()->setVar('rejected_by_block', '');
	}

	/**
	 * Send it
	 */
	public function send() {
		$this->getEmail()->send();
	}
}

