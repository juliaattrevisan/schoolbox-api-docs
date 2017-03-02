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

    /**
     * @var \Schoolbox\User
     */
    private $currentUser;

    /**
     * @var \Schoolbox\USer
     */
    private $user;

	/**
	 * @param $session
	 * @param \Schoolbox\User $user
	 */
	public function __construct(\Schoolbox\User $currentUser, \Schoolbox\User $user) {
		$this->setCurrentUser($currentUser);
		$this->setUser($user);

		parent::__construct();
	}

	/**
	 * Log when a user has been assumed
	 */
	public function notify() {
		$date = new Alaress_Date_Format(time(), 'U');
		$user = $this->getUser();

		$log = array(
			'user' => $this->getCurrentUser()->getUsername(),
			'assumed' => $user->getUsername(),
			'created_at' => $date->getDatabase(),
			'ip' => $_SERVER['REMOTE_ADDR'],
		);

		parent::create($log);
	}

	/**
	 * Set session
	 *
	 * @param \Schoolbox\User $currentUser
	 */
	private function setCurrentUser($currentUser) {
		$this->currentUser = $currentUser;
	}

	/**
	 * Get session
	 *
	 * @return \Schoolbox\User
	 */
	private function getCurrentUser() {
		return $this->currentUser;
	}

	/**
	 * Set user
	 *
	 * @param \Schoolbox\User $user
	 */
	private function setUser($user) {
		$this->user = $user;
	}

	/**
	 * @return \Schoolbox\User
	 */
	private function getUser() {
		return $this->user;
	}
}
