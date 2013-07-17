<?php
/**
 * SocialStream_Authenticator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream;

/**
 * Class Authenticator
 * @package SocialStream
 */
class Authenticator {

	/**
	 * A component instance
	 *
	 * @var null
	 */
	private $instance = null;

	/**
	 * A user instance
	 *
	 * @var null
	 */
	private $currentUser = null;

	/**
	 * Constructor
	 *
	 * @param \Component_Instance $instance
	 * @param \Schoolbox\User $currentUser
	 */
	public function __construct(\Component_Instance $instance, \Schoolbox\User $currentUser) {
		$this->setInstance($instance);
		$this->setCurrentUser($currentUser);
	}

	/**
	 * Find a particular config value
	 *
	 * @param $key
	 * @return mixed
	 */
	private function findConfigValue($key) {
		if (is_array($this->getInstance()->config) && count($this->getInstance()->config)) {
			foreach($this->getInstance()->config as $currentConfig) {
				if ($currentConfig->key == $key) {
					return $currentConfig->value;
				}
			}
		}
	}

	/**
	 * Determine whether $currentUser can collaborate on $instance
	 *
	 * @return bool
	 */
	public function canCollaborate() {
		$configValue = $this->findConfigValue('collaborate');

		if ($configValue) {
			return true;
		}

		return false;
	}

	/**
	 * Determine whether $currentUser can comment on $instance
	 *
	 * @return bool
	 */
	public function canComment() {
		$configValue = $this->findConfigValue('commentsAllowed');

		if ($configValue) {
			return true;
		}

		return false;
	}

	/* Getters/Setters
	 */

	/**
	 * Set current user
	 *
	 * @param $currentUser
	 */
	private function setCurrentUser($currentUser) {
		$this->currentUser = $currentUser;
	}

	/**
	 * Get current user
	 *
	 * @return null
	 */
	private function getCurrentUser() {
		return $this->currentUser;
	}

	/**
	 * Set instance
	 *
	 * @param $instance
	 */
	private function setInstance($instance) {
		$this->instance = $instance;
	}

	/**
	 * Get instance
	 *
	 * @return null
	 */
	private function getInstance() {
		return $this->instance;
	}
}