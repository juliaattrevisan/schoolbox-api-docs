<?php
/**
 * Adds validation and detection methods
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Email {
	private $email = null;

	public function __construct($email) {
		$this->setEmail($email);
	}

	/**
	 * Check if an email address is valid. Stolen and adapted from the Ormond
	 * ContactForm class.
	 *
	 * This should obsolete isEmail()
	 *
	 * @return bool
	 */
	public function isValid() {
		if (!preg_match('/^([0-9a-zA-Z]+[-._+&])*[0-9a-zA-Z]+@([-0-9a-zA-Z]+[.])+[a-zA-Z]{2,6}$/', $this->getEmail())) {
			return false;
		}

		/* Do domain lookup
		 */
		list($alias, $domain) = explode('@', $this->getEmail());

		/* This prevents appending of localhost to lookup
		 */
		$domain .= '.';

		/* Check for mx records or that it resolves
		 */
		if (getmxrr($domain, $temp) || checkdnsrr($domain, 'ANY')) {
			return true;
		}

		return false;
	}

	/**
	 * Set email
	 *
	 * @param  $val
	 * @return void
	 */
	private function setEmail($val) {
		$this->email = $val;
	}

	/**
	 * Get email
	 *
	 * @return
	 */
	public function getEmail() {
		return $this->email;
	}
}
