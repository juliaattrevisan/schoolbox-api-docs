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
    protected $userHandler = null;
	protected $allRecords = array();

	/**
	 * Set up blocks and stuff
	 *
	 * @abstract
	 */
	abstract protected function setup();

	/**
	 * Check permissions. By default only parents and superusers can view this page
	 *
	 * @return bool
	 */
	protected function checkPermissions() {
		if ($this->getGroupHandler()->isParent() || $this->getGroupHandler()->isSuperuser() || $this->getGroupHandler()->isStaff()) {
			return true;
		}

		return false;
	}

	/**
	 * Render dat
	 *
	 * @abstract
	 */
	abstract public function render();

	/**
	 * @param $resource
	 * @param $offset
	 * @param $limit
	 * @return array|bool
	 */
	protected function getRecordsFromResource(&$resource, $offset, $limit) {
		if (!is_resource($resource)) {
			return false;
		}

		// fforward to the desired offset
		mysql_data_seek($resource, max($offset, 0));

		// collect a page of data
		$set = array();

		for ($i = 0; $i < $limit; $i++) {
			if (!($datum = mysql_fetch_assoc($resource))) {
				break;
			}

			$set[] = $datum;
		}

		// rewind the data cursor so if export is being called it won't miss the first opage of results
		mysql_data_seek($resource, 0);

		return $set;
	}

	/* Getters/Setters
	 */

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
	public function getYearLevel() {
		return $this->yearLevel;
	}

	/**
	 * Set group handler
	 *
	 * @param $groupHandler
	 */
	protected function setGroupHandler($groupHandler) {
		$this->groupHandler = $groupHandler;
	}

	/**
	 * Get group handler
	 *
	 * @return Group
	 */
	public function getGroupHandler() {
		return $this->groupHandler;
	}

	/**
	 * Set external user handler
	 *
	 * @param $externalUserHandler
	 */
	protected function setExternalUserHandler($externalUserHandler) {
		$this->externalUserHandler = $externalUserHandler;
	}

    /**
     * @param $userHandler
     */
    protected function setUserHandler($userHandler) {
        $this->userHandler = $userHandler;
    }

    /**
     * @return User
     */
    protected function getUserHandler() {
        return $this->userHandler;
    }

	/**
	 * Get external user handler
	 *
	 * @return External_User
	 */
	protected function getExternalUserHandler() {
		return $this->externalUserHandler;
	}

	/**
	 * Set page
	 *
	 * @param $page
	 */
	protected function setPage($page) {
		$this->page = $page;
	}

	/**
	 * Get page
	 *
	 * @return SitePage
	 */
	protected function getPage() {
		return $this->page;
	}

	/**
	 * Set all records
	 *
	 * @param $allRecords
	 */
	protected function setAllRecords($allRecords) {
		$this->allRecords = $allRecords;
	}

	/**
	 * Get all records
	 *
	 * @return array
	 */
	protected function getAllRecords() {
		return $this->allRecords;
	}
}
