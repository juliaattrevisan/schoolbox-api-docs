<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Calendar;


abstract class Base {

	/**
	 * The person to whom these imported events are relevant
	 *
	 * @var int
	 */
	protected $userId = -1;

	/**
	 * The person who spawned the event
	 *
	 * @var int
	 */
	protected $authorId = null;

	/**
	 * A list of selected event_group relations
	 *
	 * @var array
	 */
	protected $groupList = array();

	/**
	 * A list of selected event_campus relations
	 *
	 * @var array
	 */
	protected $campusList = array();

	/**
	 * The source file from which to import
	 *
	 * @var null
	 */
	protected $file = null;

	/**
	 * The outcome of each event's processing
	 * This is an array of name, status, and error message values
	 *
	 * @var null
	 */
	protected $output = array();

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
	abstract public function load();

	/* Getters/Setters
	 */

	/**
	 * Set author id
	 *
	 * @param null $authorId
	 */
	protected function setAuthorId($authorId) {
		$this->authorId = $authorId;
	}

	/**
	 * Get author id
	 *
	 * @return null
	 */
	protected function getAuthorId() {
		return $this->authorId;
	}

	/**
	 * Set campus list
	 *
	 * @param array $campusList
	 */
	protected function setCampusList($campusList) {
		$this->campusList = $campusList;
	}

	/**
	 * Get campus list
	 *
	 * @return array
	 */
	protected function getCampusList() {
		return $this->campusList;
	}

	/**
	 * Set file
	 *
	 * @param null $file
	 */
	protected function setFile($file) {
		$this->file = $file;
	}

	/**
	 * Get file
	 *
	 * @return null
	 */
	protected function getFile() {
		return $this->file;
	}

	/**
	 * Set group list
	 *
	 * @param array $groupList
	 */
	protected function setGroupList($groupList) {
		$this->groupList = $groupList;
	}

	/**
	 * Get group list
	 *
	 * @return array
	 */
	protected function getGroupList() {
		return $this->groupList;
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

	/**
	 * Set output
	 *
	 * @param null $output
	 */
	protected function setOutput($output) {
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