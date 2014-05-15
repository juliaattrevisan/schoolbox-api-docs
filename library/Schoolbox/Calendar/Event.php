<?php
/**
 * Event
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Calendar;


class Event {

	/**
	 * Event Access Levels
	 *
	 * @var array
	 */
	private $accessLevels = array(
		'personal' => 1,
		'group' => 2,
		'campus' => 3,
	);

	/**
	 * Event id
	 *
	 * @var null
	 */
	protected $id = null;

	/**
	 * Series Id for recurring events, I think
	 *
	 * @var null
	 */
	protected $seriesId = null;

	/**
	 * An instance of \Schoolbox\User
	 *
	 * The person who spawned the event in the first place
	 *
	 * @var null
	 */
	protected $author = null;

	/**
	 * An instance of \Schoolbox\User
	 *
	 * This denotes to whom the event is relevant for a particular
	 * combination of sid and access (I don't remember/understand which)
	 *
	 * @var null
	 */
	protected $owner = null;

	/**
	 * An instance of \Schoolbox\Resource\Category
	 *
	 * @var null
	 */
	protected $category = null;

	/**
	 * Access level
	 *
	 * @var int
	 */
	protected $access = 0;

	/**
	 * sid ><
	 *
	 * @var int
	 */
	protected $sid = 0;

	/**
	 * A hex string representing the colour of this event
	 *
	 * @var string
	 */
	protected $colour = '';

	/**
	 * Unholy selection of field names: eventType and type
	 *
	 * eventType is a human-friendly description of an event's type;
	 * e.g. Event, Excursion, Report etc. and comes from a lookup table
	 *
	 * @var string
	 */
	protected $eventType = '';

	/**
	 * An int from the event table corresponding to $eventType
	 *
	 * @var int
	 */
	protected $type = 0;

	/**
	 * Title of the event
	 *
	 * @var string
	 */
	protected $note = '';

	/**
	 * A description of the event
	 *
	 * @var string
	 */
	protected $detail = '';

	/**
	 * Where the event is being held
	 *
	 * @var string
	 */
	protected $location = '';

	/**
	 * Whether or not people are supposed to attend
	 *
	 * @var int
	 */
	protected $attendance = 0;

	/**
	 * Determine whether the attendance state changed prior to saving
	 *
	 * This saves us an extra db call in save() where it used to
	 * load the old copy of the event, and then compare it to the
	 * newly-constructed version of the event, to determine whether
	 * existing attendances should be cleared or preserved.
	 *
	 * @var bool
	 */
	protected $attendanceDirty = false;

	/**
	 * If this is a task, whether it's completed or not
	 * That should have been normalised -_-
	 *
	 * @var int
	 */
	protected $completed = 0;

	/**
	 * Whether it's an all-day event
	 *
	 * @var int
	 */
	protected $allDay = 0;

	/**
	 * Any associated roles
	 *
	 * @var null
	 */
	protected $roleCollection = null;

	/**
	 * Any associated campuses
	 *
	 * @var null
	 */
	protected $campusCollection = null;

	/**
	 * The date when the event was published (made visible to users)
	 * An instance Alaress_Date_Format()
	 *
	 * @var null
	 */
	protected $publishDateTime = null;

	/**
	 * The datetime when the event starts
	 *
	 * @var null
	 */
	protected $startDateTime = null;

	/**
	 * For mappers
	 *
	 * @var string
	 */
	protected $startDate = '';

	/**
	 * For mappers
	 *
	 * @var string
	 */
	protected $startTime = '';
	
	/**
	 * The datetime when the event ends
	 *
	 * @var null
	 */
	protected $endDateTime = null;

	/**
	 * For mappers
	 *
	 * @var string
	 */
	protected $endDate = '';

	/**
	 * For mappers
	 *
	 * @var string
	 */
	protected $endTime = '';
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 * Hook method
	 */
	protected function setup() {
	}

	/**
	 * Is this event a personal-accesslevel instance?
	 *
	 * @return bool
	 */
	public function isPersonalLevel() {
		if ($this->getAccess() == $this->accessLevels['personal']) {
			return true;
		}

		return false;
	}

	/**
	 * Is this event a group-accesslevel instance?
	 *
	 * @return bool
	 */
	public function isGroupLevel() {
		if ($this->getAccess() == $this->accessLevels['group']) {
			return true;
		}

		return false;
	}

	/**
	 * Is this event a campus-accesslevel instance?
	 *
	 * @return bool
	 */
	public function isCampusLevel() {
		if ($this->getAccess() == $this->accessLevels['campus']) {
			return true;
		}

		return false;
	}

	/* Auxiliary methods which don't belong anywhere else
	 */

	/**
	 * Derive start and end dates
	 *
	 * @param $date
	 */
	public function setupDates($date) {
		list($selectedDay, $selectedMonth, $selectedYear) = explode("-", $date);

		$selectedDate = @mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear);

		if ($selectedDate <= 0) {
			$selectedDate = time();
		}

		$startDate = new \Alaress_Date_Format($selectedDate, 'U');
		$endDate = new \Alaress_Date_Format($selectedDate, 'U');

		$this->setStartDateTime($startDate);
		$this->setEndDateTime($endDate);
	}

	/**
	 * Ensure that the user hasn't modified the URL in an untoward manner.
	 * If they have, or it's somehow absent, default to week view.
	 *
	 * Really, the redirect should be passed as a base-64 encoded paramter
	 * (or similar) rather than having to implicitly "guess" where to go next
	 *
	 * @param string $returnView
	 * @param string $returnDate
	 * @param int    $groupId
	 * @param int    $userId
	 *
	 * @return string
	 * @internal param $returnTo
	 */
	public function setupRedirect($returnView, $returnDate = "", $groupId = null, $userId = null) {
		if ($returnView === 'dashboard') {
			return '/';
		}
		else if ($returnView === 'upcomingEventAttendance') {
			return '/calendar/attendance/upcomingEvents/';
		}
		else if ($returnView === 'homepage') {
			return '/homepage/'.$groupId;
		}
		else if ($returnView === 'calendar') {
			$return = "/calendar/full/$returnDate";

			if ($userId) {
				$return .= '/'.$userId;
			}

			return $return;
		}

		// Otherwise - guess using Back to the future!
		return '';
	}

	/* Getters/Setters
	 */

	/**
	 * Set access
	 *
	 * @param int $access
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
	 * Set all day
	 *
	 * @param int $allDay
	 */
	public function setAllDay($allDay) {
		$this->allDay = $allDay;
	}

	/**
	 * Get all day
	 *
	 * @return int
	 */
	public function getAllDay() {
		return $this->allDay;
	}

	/**
	 * Set attendance
	 *
	 * @param int $attendance
	 */
	public function setAttendance($attendance) {
		$this->attendance = $attendance;
	}

	/**
	 * Get attendance
	 *
	 * @return int
	 */
	public function getAttendance() {
		return $this->attendance;
	}

	/**
	 * Set author
	 *
	 * @param null $author
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
	 * Set category
	 *
	 * @param null $category
	 */
	public function setCategory($category) {
		$this->category = $category;
	}

	/**
	 * Get category
	 *
	 * @return null
	 */
	public function getCategory() {
		return $this->category;
	}

	/**
	 * Set colour
	 *
	 * @param string $colour
	 */
	public function setColour($colour) {
		$this->colour = $colour;
	}

	/**
	 * Get colour
	 *
	 * @return string
	 */
	public function getColour() {
		return $this->colour;
	}

	/**
	 * Set completed
	 *
	 * @param int $completed
	 */
	public function setCompleted($completed) {
		$this->completed = $completed;
	}

	/**
	 * Get completed
	 *
	 * @return int
	 */
	public function getCompleted() {
		return $this->completed;
	}

	/**
	 * Set detail
	 *
	 * @param string $detail
	 */
	public function setDetail($detail) {
		$this->detail = $detail;
	}

	/**
	 * Get detail
	 *
	 * @return string
	 */
	public function getDetail() {
		return $this->detail;
	}

	/**
	 * Set end datetime
	 *
	 * @param null $endDateTime
	 */
	public function setEndDateTime($endDateTime) {
		$this->endDateTime = $endDateTime;
	}

	/**
	 * Get end datetime
	 *
	 * @return null
	 */
	public function getEndDateTime() {
		return $this->endDateTime;
	}

	/**
	 * Set event type
	 *
	 * @param string $eventType
	 */
	public function setEventType($eventType) {
		$this->eventType = $eventType;
	}

	/**
	 * Get event type
	 *
	 * @return string
	 */
	public function getEventType() {
		return $this->eventType;
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
	 * Set location
	 *
	 * @param string $location
	 */
	public function setLocation($location) {
		$this->location = $location;
	}

	/**
	 * Get location
	 *
	 * @return string
	 */
	public function getLocation() {
		return $this->location;
	}

	/**
	 * Set note
	 *
	 * @param string $note
	 */
	public function setNote($note) {
		$this->note = $note;
	}

	/**
	 * Get note
	 *
	 * @return string
	 */
	public function getNote() {
		return $this->note;
	}

	/**
	 * Set owner
	 *
	 * @param null $owner
	 */
	public function setOwner($owner) {
		$this->owner = $owner;
	}

	/**
	 * Get owner
	 *
	 * @return null
	 */
	public function getOwner() {
		return $this->owner;
	}

	/**
	 * Set series id
	 *
	 * @param null $seriesId
	 */
	public function setSeriesId($seriesId) {
		$this->seriesId = $seriesId;
	}

	/**
	 * Get series id
	 *
	 * @return null
	 */
	public function getSeriesId() {
		return $this->seriesId;
	}

	/**
	 * Set sid
	 *
	 * @param int $sid
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
	 * Set start datetime
	 *
	 * @param null $startDateTime
	 */
	public function setStartDateTime($startDateTime) {
		$this->startDateTime = $startDateTime;
	}

	/**
	 * Get start datetime
	 *
	 * @return null
	 */
	public function getStartDateTime() {
		return $this->startDateTime;
	}

	/**
	 * Set type
	 *
	 * @param int $type
	 */
	public function setType($type) {
		$this->type = $type;
	}

	/**
	 * Get type
	 *
	 * @return int
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Set end date
	 *
	 * @param string $endDate
	 */
	public function setEndDate($endDate) {
		$this->endDate = $endDate;
	}

	/**
	 * Get end date
	 *
	 * @return string
	 */
	public function getEndDate() {
		return $this->endDate;
	}

	/**
	 * Set end time
	 *
	 * @param string $endTime
	 */
	public function setEndTime($endTime) {
		$this->endTime = $endTime;
	}

	/**
	 * Get end time
	 *
	 * @return string
	 */
	public function getEndTime() {
		return $this->endTime;
	}

	/**
	 * Set start date
	 *
	 * @param string $startDate
	 */
	public function setStartDate($startDate) {
		$this->startDate = $startDate;
	}

	/**
	 * Get start date
	 *
	 * @return string
	 */
	public function getStartDate() {
		return $this->startDate;
	}

	/**
	 * Set start time
	 *
	 * @param string $startTime
	 */
	public function setStartTime($startTime) {
		$this->startTime = $startTime;
	}

	/**
	 * Get start time
	 *
	 * @return string
	 */
	public function getStartTime() {
		return $this->startTime;
	}

	/**
	 * Set publish datetime
	 *
	 * @param null $publishDateTime
	 */
	public function setPublishDateTime($publishDateTime) {
		$this->publishDateTime = $publishDateTime;
	}

	/**
	 * Get publish datetime
	 *
	 * @return null
	 */
	public function getPublishDateTime() {
		return $this->publishDateTime;
	}

	/**
	 * Set campus collection
	 *
	 * @param null $campusCollection
	 */
	public function setCampusCollection($campusCollection) {
		$this->campusCollection = $campusCollection;
	}

	/**
	 * Get campus collection
	 *
	 * @return null
	 */
	public function getCampusCollection() {
		return $this->campusCollection;
	}

	/**
	 * Set role collection
	 *
	 * @param null $roleCollection
	 */
	public function setRoleCollection($roleCollection) {
		$this->roleCollection = $roleCollection;
	}

	/**
	 * Get role collection
	 *
	 * @return null
	 */
	public function getRoleCollection() {
		return $this->roleCollection;
	}

	/**
	 * Set attendance dirty bit
	 *
	 * @param boolean $attendanceDirty
	 */
	public function setAttendanceDirty($attendanceDirty) {
		$this->attendanceDirty = $attendanceDirty;
	}

	/**
	 * Get attendance dirty bit
	 *
	 * @return boolean
	 */
	public function getAttendanceDirty() {
		return $this->attendanceDirty;
	}
}