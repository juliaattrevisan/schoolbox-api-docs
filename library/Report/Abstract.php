<?php
/**
 * Report_Abstract
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
abstract class Report_Abstract {
	protected $page = null;
	protected $yearLevel = null;
	protected $groupHandler = null;
	protected $externalUserHandler = null;

	/**
	 * Constructor
	 *
	 * @param $page
	 * @param $yearLevel
	 */
	public function __construct($page, $yearLevel) {
		$this->setGroupHandler(new Group());

		if (!is_numeric($yearLevel) || !$this->checkPermissions()) {
			error(19);
		}
		else {
			$this->setPage($page);
			$this->setYearLevel($yearLevel);

			$this->setExternalUserHandler(new External_User());
			$this->getExternalUserHandler()->getCurrentSemester();

			$this->setup();
		}
	}

	/**
	 * Set up blocks and stuff
	 *
	 * @abstract
	 */
	abstract protected function setup();

	/**
	 * Permissions for who can view this report
	 *
	 * @abstract
	 */
	abstract protected function checkPermissions();

	/**
	 * Render dat
	 *
	 * @abstract
	 */
	abstract public function render();

	/**
	 * Set year level
	 *
	 * @param $yearLevel
	 */
	protected function setYearLevel($yearLevel) {
		$this->yearLevel = $yearLevel;
	}

	/**
	 * Get year level
	 *
	 * @return mixed
	 */
	protected function getYearLevel() {
		return $this->yearLevel;
	}

	/**
	 * Set group handler
	 *
	 * @param $groupHandler
	 */
	private function setGroupHandler($groupHandler) {
		$this->groupHandler = $groupHandler;
	}

	/**
	 * Get group handler
	 *
	 * @return null
	 */
	protected function getGroupHandler() {
		return $this->groupHandler;
	}

	/**
	 * Set external user handler
	 *
	 * @param $externalUserHandler
	 */
	private function setExternalUserHandler($externalUserHandler) {
		$this->externalUserHandler = $externalUserHandler;
	}

	/**
	 * Get external user handler
	 *
	 * @return null
	 */
	protected function getExternalUserHandler() {
		return $this->externalUserHandler;
	}

	/**
	 * Set page
	 *
	 * @param $page
	 */
	private function setPage($page) {
		$this->page = $page;
	}

	/**
	 * Get page
	 *
	 * @return null
	 */
	protected function getPage() {
		return $this->page;
	}
}
