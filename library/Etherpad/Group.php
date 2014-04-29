<?php
/**
 * Group
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Etherpad;

class Group {

	/**
	 * Identifier
	 *
	 * @var null
	 */
	private $id = null;

	/**
	 * Etherpad Group ID
	 * Takes the form similar to g.asdoiju89234
	 * @var null
	 */
	private $groupId = '';

	/**
	 * Schoolbox user id belonging to an Etherpad Group
	 *
	 * @var null
	 */
	private $userId = null;

	/**
	 * The homepage this Etherpad Group was spawned inside
	 *
	 * @var null
	 */
	private $homepageId = null;

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/* Getters/Setters
	 */

	/**
	 * Set group id
	 *
	 * @param null $groupId
	 */
	public function setGroupId($groupId) {
		$this->groupId = $groupId;
	}

	/**
	 * Get group id
	 *
	 * @return null
	 */
	public function getGroupId() {
		return $this->groupId;
	}

	/**
	 * Set homepage id
	 *
	 * @param null $homepageId
	 */
	public function setHomepageId($homepageId) {
		$this->homepageId = $homepageId;
	}

	/**
	 * Get homepage id
	 *
	 * @return null
	 */
	public function getHomepageId() {
		return $this->homepageId;
	}

	/**
	 * Set id
	 *
	 * @param null $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Get id
	 *
	 * @return null
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set user id
	 *
	 * @param null $userId
	 */
	public function setUserId($userId) {
		$this->userId = $userId;
	}

	/**
	 * Get user id
	 *
	 * @return null
	 */
	public function getUserId() {
		return $this->userId;
	}
}