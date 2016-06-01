<?php
/**
 * Alaress_Soap_WSSEToken
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Soap_WSSEToken {
	private $UsernameToken;

	/**
	 * Constructor
	 *
	 * @param $innerVal
	 */
	public function __construct($innerVal) {
		$this->setUsernameToken($innerVal);
	}

	/* Getters/Setters
	 */

	/**
	 * Set username token
	 *
	 * @param $usernameToken
	 */
	private function setUsernameToken($usernameToken) {
		$this->UsernameToken = $usernameToken;
	}
}
