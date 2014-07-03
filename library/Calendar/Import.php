<?php
/**
 * Import
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Calendar;


class Import implements ImportInterface {

	/**
	 * The person to whom these imported events are relevant
	 *
	 * @var int
	 */
	private $userId = -1;

	/**
	 * The person who spawned the event
	 *
	 * @var int
	 */
	private $authorId = null;

	/**
	 * A list of selected event_group relations
	 *
	 * @var array
	 */
	private $groupList = array();

	/**
	 * A list of selected event_campus relations
	 *
	 * @var array
	 */
	private $campusList = array();

	/**
	 * The source file from which to import
	 *
	 * @var null
	 */
	private $file = null;

	/**
	 * The outcome of each event's processing
	 * This is an array of name, status, and error message values
	 *
	 * @var null
	 */
	private $output = array();

	/**
	 * Constructor
	 *
	 * @param $userId
	 * @param $authorId
	 * @param $groupList
	 * @param $campusList
	 * @param $file
	 */
	public function __construct($userId, $authorId, $groupList, $campusList, $file) {
		$this->setUserId($userId);
		$this->setAuthorId($authorId);
		$this->setGroupList($groupList);
		$this->setCampusList($campusList);
		$this->setFile($file);
	}

	/**
	 * Load events from file into schoolbox
	 */
	public function load() {
//		if ($this->getCalendarHandler()->create($event)) {
//			$output[] = array(
//				'name' => $summary,
//				'status' => 'Added',
//				'error' => 'OK',
//			);
//		}
//		else {
//			$output[] = array(
//				'name' => $summary,
//				'status' => 'Not Added',
//				'error' => 'Could not create event',
//			);
//		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set author id
	 *
	 * @param null $authorId
	 */
	private function setAuthorId($authorId) {
		$this->authorId = $authorId;
	}

	/**
	 * Get author id
	 *
	 * @return null
	 */
	private function getAuthorId() {
		return $this->authorId;
	}

	/**
	 * Set campus list
	 *
	 * @param array $campusList
	 */
	private function setCampusList($campusList) {
		$this->campusList = $campusList;
	}

	/**
	 * Get campus list
	 *
	 * @return array
	 */
	private function getCampusList() {
		return $this->campusList;
	}

	/**
	 * Set file
	 *
	 * @param null $file
	 */
	private function setFile($file) {
		$this->file = $file;
	}

	/**
	 * Get file
	 *
	 * @return null
	 */
	private function getFile() {
		return $this->file;
	}

	/**
	 * Set group list
	 *
	 * @param array $groupList
	 */
	private function setGroupList($groupList) {
		$this->groupList = $groupList;
	}

	/**
	 * Get group list
	 *
	 * @return array
	 */
	private function getGroupList() {
		return $this->groupList;
	}

	/**
	 * Set user id
	 *
	 * @param null $userId
	 */
	private function setUserId($userId) {
		$this->userId = $userId;
	}

	/**
	 * Get user id
	 *
	 * @return null
	 */
	private function getUserId() {
		return $this->userId;
	}

	/**
	 * Set output
	 *
	 * @param null $output
	 */
	private function setOutput($output) {
		$this->output = $output;
	}

	/**
	 * Get output
	 *
	 * @return null
	 */
	public function getOutput() {
		return $this->output;
	}
}