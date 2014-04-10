<?php
/**
 * Authenticator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Etherpad;


class Authenticator {

	/**
	 * An instance of \Group
	 *
	 * @var \Group
	 */
	private $groupHandler = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setGroupHandler(new \Group());
	}

	/**
	 * Determine if you can create a pad for $homepageId
	 *
	 * @param $homepageId
	 * @return bool
	 */
	public function canCreate($homepageId) {
		if ($this->getGroupHandler()->getPermission($homepageId) > READ_ACCESS) {
			return true;
		}

		return false;
	}

	/**
	 * Determine if a user is part of an authoring group
	 *
	 * @param $homepageId
	 * @return bool
	 */
	public function isParticipator($homepageId) {
		if ($this->getGroupHandler()->getPermission($homepageId) >= READ_ACCESS) {
			return true;
		}

		return false;
	}

	/* Getters/Setters
	 */

	/**
	 * Set group handler
	 *
	 * @param \Group $groupHandler
	 */
	public function setGroupHandler($groupHandler) {
		$this->groupHandler = $groupHandler;
	}

	/**
	 * Get group handler
	 *
	 * @return \Group
	 */
	public function getGroupHandler() {
		return $this->groupHandler;
	}
}