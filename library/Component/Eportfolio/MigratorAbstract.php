<?php
/**
 * MigratorAbstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Component\Eportfolio;

abstract class MigratorAbstract {

	/**
	 * An old db handler
	 *
	 * @var null
	 */
	protected $db = null;

	/**
	 * The owner of the eportfolio
	 *
	 * @var null
	 */
	protected $userId = null;

	/**
	 * The page belonging to the user
	 *
	 * @var null
	 */
	protected $pageId = null;

	/**
	 * Constructor
	 */
	final public function __construct($userId, $pageId) {
		$this->setDb(\DeprecatedDatabase::getInstance());

		$this->setUserId($userId);
		$this->setPageId($pageId);

		$this->setup();
	}

	/**
	 * Hook method
	 *
	 * @return mixed
	 */
	abstract protected function setup();

	/**
	 * Main method
	 *
	 * @return mixed
	 */
	abstract public function migrate();

	/* Getters/Setters
	 */

	/**
	 * Set db
	 *
	 * @param null $db
	 */
	protected function setDb($db) {
		$this->db = $db;
	}

	/**
	 * Get db
	 *
	 * @return null
	 */
	protected function getDb() {
		return $this->db;
	}

	/**
	 * Set page id
	 * 
	 * @param null $pageId
	 */
	protected function setPageId($pageId) {
		$this->pageId = $pageId;
	}

	/**
	 * Get page id
	 *
	 * @return null
	 */
	protected function getPageId() {
		return $this->pageId;
	}

	/**
	 * Set user id
	 *
	 * @param null $userId
	 */
	protected function setUserId($userId) {
		$this->userId = $userId;
	}

	/**
	 * Get user id
	 *
	 * @return null
	 */
	protected function getUserId() {
		return $this->userId;
	}
}