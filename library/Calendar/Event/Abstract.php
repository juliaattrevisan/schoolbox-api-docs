<?php
/**
 * Calendar_Event_Abstract
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
abstract class Calendar_Event_Abstract {

	/* These probably exist elsewhere.
	 */
	const CAMPUS_ACCESS = 3;
	const GROUP_ACCESS = 2;

	/* Inheritable class vars
	 */
	protected $eventId = -1;
	protected $startTime = '';
	protected $endTime = '';
	protected $date = '';

	/* The date when the event was published (made visible to users)
	 * Stored always as an Alaress_Date_Format() instance
	 */
	protected $publishDate = null;

	/* A hex string representing the colour of this event
	 */
	protected $colour = '';

	/* Location, short description and long description of the event
	 */
	protected $location = '';
	protected $note = '';
	protected $detail = '';

	/* Unholy selection of field names: eventType and type
	 *
	 * eventType is a human-friendly description of an event's type;
	 * e.g. Event, Excursion, Report etc. and comes from a lookup table
	 */
	protected $eventType = '';

	/* An int from the event table corresponding to $eventType
	 */
	protected $type = -1;

	/* Event author's name and id
	 */
	protected $author = '';
	protected $authorId = -1;

	/* Access level
	 */
	protected $access = -1;

	/* CSV separated list of campus ids relevant to this event.
	 */
	protected $campusList = '';

	/* sid ><
	 */
	protected $sid = -1;

	/* Attendance flag
	 */
	protected $attendance = 0;

	/* Category id and name
	 */
	protected $categoryId = -1;
	protected $categoryName = '';

	/**
	 * Constructor
	 *
	 * Set up default settings
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 * Hook method for your own customisations
	 *
	 * Empty by default
	 *
	 */
	protected function setup() {
	}

	/**
	 * Can someone attend this event
	 *
	 * @return bool
	 */
	public function canAttend() {
		if (is_numeric($this->getAttendance()) && ($this->getAttendance() > 0)) {
			return true;
		}

		return false;
	}

	/**
	 * Is campus access or not
	 *
	 * @return bool
	 */
	protected function isCampusAccess() {
		if ($this->getAccess() == self::CAMPUS_ACCESS) {
			return true;
		}

		return false;
	}

	/**
	 * is group access or not
	 *
	 * @return bool
	 */
	protected function isGroupAccess() {
		if ($this->getAccess() == self::GROUP_ACCESS) {
			return true;
		}

		return false;
	}

	/* Getters/Setters
	 */

	/**
	 * Set author
	 *
	 * @param $author
	 */
	public function setAuthor($author) {
		$this->author = $author;
	}

	/**
	 * Get author
	 *
	 * @return null
	 */
	public function getAuthor() {
		return $this->author;
	}

	/**
	 * Set author id
	 *
	 * @param $authorId
	 */
	public function setAuthorId($authorId) {
		$this->authorId = $authorId;
	}

	/**
	 * Get author id
	 *
	 * @return null
	 */
	public function getAuthorId() {
		return $this->authorId;
	}

	/**
	 * Set colour
	 *
	 * @param $colour
	 */
	public function setColour($colour) {
		if ($colour[0] != '#') {
			$this->colour = '#' . $colour;
		}
		else {
			$this->colour = $colour;
		}
	}

	/**
	 * Get colour
	 *
	 * @return null
	 */
	public function getColour() {
		return $this->colour;
	}

	/**
	 * Set detail
	 *
	 * @param $detail
	 */
	public function setDetail($detail) {
		$this->detail = $detail;
	}

	/**
	 * Get detail
	 *
	 * @return null
	 */
	public function getDetail() {
		return $this->detail;
	}

	/**
	 * Set event type
	 *
	 * @param $eventType
	 */
	public function setEventType($eventType) {
		$this->eventType = $eventType;
	}

	/**
	 * Get event type
	 *
	 * @return null
	 */
	public function getEventType() {
		return $this->eventType;
	}

	/**
	 * Set location
	 *
	 * @param $location
	 */
	public function setLocation($location) {
		$this->location = $location;
	}

	/**
	 * Get location
	 *
	 * @return null
	 */
	public function getLocation() {
		return $this->location;
	}

	/**
	 * Set note
	 *
	 * @param $note
	 */
	public function setNote($note) {
		$this->note = Scrub::html($note);
	}

	/**
	 * Get note
	 *
	 * @return null
	 */
	public function getNote() {
		return $this->note;
	}

	/**
	 * Set type
	 *
	 * @param $type
	 */
	public function setType($type) {
		$this->type = $type;
	}

	/**
	 * Get type
	 *
	 * @return null
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Set date
	 *
	 * @param $date
	 */
	public function setDate($date) {
		$this->date = $date;
	}

	/**
	 * Get date
	 *
	 * @return null
	 */
	public function getDate() {
		return $this->date;
	}

	/**
	 * Set publish date object
	 *
	 * @param $publishDate
	 */
	public function setPublishDate($publishDate) {
		$this->publishDate = new Alaress_Date_Format($publishDate, 'y-m-d h:i:s');
	}

	/**
	 * Get publish date
	 *
	 * @return null
	 */
	public function getPublishDate() {
		return $this->publishDate;
	}

	/**
	 * Set event id
	 *
	 * @param $eventId
	 */
	public function setEventId($eventId) {
		$this->eventId = $eventId;
	}

	/**
	 * Get event id
	 *
	 * @return null
	 */
	public function getEventId() {
		return $this->eventId;
	}

	/**
	 * Set access
	 *
	 * @param $access
	 */
	public function setAccess($access) {
		$this->access = $access;
	}

	/**
	 * Get access
	 *
	 * @return int
	 */
	public function getAccess() {
		return $this->access;
	}

	/**
	 * Set campus list
	 *
	 * @param $campusList
	 */
	public function setCampusList($campusList) {
		$this->campusList = $campusList;
	}

	/**
	 * Get campus list
	 *
	 * @return null
	 */
	public function getCampusList() {
		return $this->campusList;
	}

	/**
	 * Set end time
	 *
	 * @param $endTime
	 */
	public function setEndTime($endTime) {
		$this->endTime = $endTime;
	}

	/**
	 * Get end time
	 *
	 * @return null
	 */
	public function getEndTime() {
		return $this->endTime;
	}

	/**
	 * Set start time
	 *
	 * @param $startTime
	 */
	public function setStartTime($startTime) {
		$this->startTime = $startTime;
	}

	/**
	 * Get start time
	 *
	 * @return null
	 */
	public function getStartTime() {
		return $this->startTime;
	}

	/**
	 * Set sid
	 *
	 * @param $sid
	 */
	public function setSid($sid) {
		$this->sid = $sid;
	}

	/**
	 * Get sid
	 *
	 * @return int
	 */
	public function getSid() {
		return $this->sid;
	}

	/**
	 * Set attendance
	 *
	 * @param $attendance
	 */
	public function setAttendance($attendance) {
		$this->attendance = $attendance;
	}

	/**
	 * Get attendance
	 *
	 * @return string
	 */
	public function getAttendance() {
		return $this->attendance;
	}

	/**
	 * Set category id
	 *
	 * @param $categoryId
	 */
	public function setCategoryId($categoryId) {
		$this->categoryId = $categoryId;
	}

	/**
	 * Get category id
	 *
	 * @return int
	 */
	public function getCategoryId() {
		return $this->categoryId;
	}

	/**
	 * Set category name
	 *
	 * @param $categoryName
	 */
	public function setCategoryName($categoryName) {
		$this->categoryName = $categoryName;
	}

	/**
	 * Get category name
	 *
	 * @return string
	 */
	public function getCategoryName() {
		return $this->categoryName;
	}
}
