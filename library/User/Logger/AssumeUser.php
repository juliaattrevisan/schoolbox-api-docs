<?php
/**
 * User_Logger_AssumeUser
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class User_Logger_AssumeUser extends DeprecatedModel {
	public $_table = 'user_assume_log';
	public $_index = 'id';

	/* Details of who assumed who
	 */
	private $session = array();
	private $user = array();

	/**
	 * @param $session
	 * @param \Schoolbox\User $user
	 */
	public function __construct($session, \Schoolbox\User $user) {
		$this->setSession($session);
		$this->setUser($user);

		parent::DeprecatedModel();
	}

	/**
	 * Log when a user has been assumed
	 */
	public function notify() {
		$date = new Alaress_Date_Format(time(), 'U');
		$user = $this->getUser();

		$log = array(
			'user' => 'admin',
			'assumed' => $user->getUsername(),
			'created_at' => $date->getDatabase(),
			'ip' => $_SERVER['REMOTE_ADDR'],
		);

		parent::create($log);
	}

	/**
	 * Set session
	 *
	 * @param $session
	 */
	private function setSession($session) {
		$this->session = $session;
	}

	/**
	 * Get session
	 *
	 * @return array
	 */
	private function getSession() {
		return $this->session;
	}

	/**
	 * Set user
	 *
	 * @param $user
	 */
	private function setUser($user) {
		$this->user = $user;
	}

	/**
	 * @return array
	 */
	private function getUser() {
		return $this->user;
	}
}
