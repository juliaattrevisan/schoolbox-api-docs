<?php
/**
 * Alaress_Soap_WSSEAuth
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Soap_WSSEAuth {
	private $Username;
	private $Password;

	/**
	 * Constructor
	 *
	 * @param $username
	 * @param $password
	 */
	public function __construct($username, $password) {
		$this->setUsername($username);
		$this->setPassword($password);
	}

	/* Getters/Setters
	 */

	/**
	 * Set username
	 *
	 * @param $Username
	 */
	private function setUsername($Username) {
		$this->Username = $Username;
	}

	/**
	 * Set password
	 *
	 * @param $Password
	 */
	private function setPassword($Password) {
		$this->Password = $Password;
	}
}
