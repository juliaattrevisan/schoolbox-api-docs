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


use Alaress_Date_Format;
use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\Resource\Category;
use Schoolbox\Role;
use Schoolbox\User;

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
	 * @var int
	 */
	protected $id = null;

	/**
	 * Series Id for recurring events, I think
	 *
	 * @var int
	 */
	protected $seriesId = null;

	/**
	 * An instance of \Schoolbox\User
	 *
	 * The person who spawned the event in the first place
	 *
	 * @var User
	 */
	protected $author = null;

	/**
	 * An instance of \Schoolbox\User
	 *
	 * This denotes to whom the event is relevant for a particular
	 * combination of sid and access (I don't remember/understand which)
	 *
	 * @var User
	 */
	protected $owner = null;

	/**
	 * An instance of \Schoolbox\Resource\Category
	 *
	 * @var Category
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
	 * @var EventType
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
	 * @var Collection|Role[]
	 */
	protected $roleCollection = null;

	/**
	 * Any associated campuses
	 *
	 * @var Collection|Campus[]
	 */
	protected $campusCollection = null;

	/**
	 * The date when the event was published (made visible to users)
	 * An instance Alaress_Date_Format()
	 *
	 * @var Alaress_Date_Format
	 */
	protected $publishDateTime = null;

	/**
	 * The datetime when the event starts
	 *
	 * @var Alaress_Date_Format
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
	 * @var Alaress_Date_Format
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

		$startDate = new Alaress_Date_Format($selectedDate, 'U');
		$endDate = new Alaress_Date_Format($selectedDate, 'U');

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
	 * @return self
	 */
	public function setAccess($access) {
		$this->access = $access;
	 	return $this;
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
	 * @return self
	 */
	public function setAllDay($allDay) {
		$this->allDay = $allDay;
	 	return $this;
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
	 * @return self
	 */
	public function setAttendance($attendance) {
		$this->attendance = $attendance;
	 	return $this;
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
	 * @param User $author
	 * @return self
	 */
	public function setAuthor($author) {
		$this->author = $author;
	 	return $this;
	}

	/**
	 * Get author
	 *
	 * @return User
	 */
	public function getAuthor() {
		return $this->author;
	}

	/**
	 * Set category
	 *
	 * @param Category $category
	 * @return self
	 */
	public function setCategory($category) {
		$this->category = $category;
	 	return $this;
	}

	/**
	 * Get category
	 *
	 * @return Category
	 */
	public function getCategory() {
		return $this->category;
	}

	/**
	 * Set colour
	 *
	 * @param string $colour
	 * @return self
	 */
	public function setColour($colour) {
		$this->colour = $colour;
	 	return $this;
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
	 * @return self
	 */
	public function setCompleted($completed) {
		$this->completed = $completed;
	 	return $this;
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
	 * @return self
	 */
	public function setDetail($detail) {
		$this->detail = $detail;
	 	return $this;
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
	 * @param Alaress_Date_Format $endDateTime
	 * @return self
	 */
	public function setEndDateTime($endDateTime) {
		$this->endDateTime = $endDateTime;
	 	return $this;
	}

	/**
	 * Get end datetime
	 *
	 * @return Alaress_Date_Format
	 */
	public function getEndDateTime() {
		return $this->endDateTime;
	}

	/**
	 * Set event type
	 *
	 * @param EventType $eventType
	 * @return self
	 */
	public function setEventType($eventType) {
		$this->eventType = $eventType;
	 	return $this;
	}

	/**
	 * Get event type
	 *
	 * @return EventType
	 */
	public function getEventType() {
		return $this->eventType;
	}

	/**
	 * Set id
	 *
	 * @param int $id
	 * @return self
	 */
	public function setId($id) {
		$this->id = $id;
	 	return $this;
	}

	/**
	 * Get id
	 *
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set location
	 *
	 * @param string $location
	 * @return self
	 */
	public function setLocation($location) {
		$this->location = $location;
	 	return $this;
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
	 * @return self
	 */
	public function setNote($note) {
		$this->note = $note;
	 	return $this;
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
	 * @param User $owner
	 * @return self
	 */
	public function setOwner($owner) {
		$this->owner = $owner;
	 	return $this;
	}

	/**
	 * Get owner
	 *
	 * @return User
	 */
	public function getOwner() {
		return $this->owner;
	}

	/**
	 * Set series id
	 *
	 * @param int $seriesId
	 * @return self
	 */
	public function setSeriesId($seriesId) {
		$this->seriesId = $seriesId;
	 	return $this;
	}

	/**
	 * Get series id
	 *
	 * @return int
	 */
	public function getSeriesId() {
		return $this->seriesId;
	}

	/**
	 * Set sid
	 *
	 * @param int $sid
	 * @return self
	 */
	public function setSid($sid) {
		$this->sid = $sid;
	 	return $this;
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
	 * @param Alaress_Date_Format $startDateTime
	 * @return self
	 */
	public function setStartDateTime($startDateTime) {
		$this->startDateTime = $startDateTime;
	 	return $this;
	}

	/**
	 * Get start datetime
	 *
	 * @return Alaress_Date_Format
	 */
	public function getStartDateTime() {
		return $this->startDateTime;
	}

	/**
	 * Set type
	 *
	 * @param int $type
	 * @return self
	 */
	public function setType($type) {
		$this->type = $type;
	 	return $this;
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
	 * @return self
	 */
	public function setEndDate($endDate) {
		$this->endDate = $endDate;
	 	return $this;
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
	 * @return self
	 */
	public function setEndTime($endTime) {
		$this->endTime = $endTime;
	 	return $this;
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
	 * @return self
	 */
	public function setStartDate($startDate) {
		$this->startDate = $startDate;
	 	return $this;
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
	 * @return self
	 *
	 */
	public function setStartTime($startTime) {
		$this->startTime = $startTime;
	 	return $this;
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
	 * @param Alaress_Date_Format $publishDateTime
	 * @return self
	 */
	public function setPublishDateTime($publishDateTime) {
		$this->publishDateTime = $publishDateTime;
	 	return $this;
	}

	/**
	 * Get publish datetime
	 *
	 * @return Alaress_Date_Format
	 */
	public function getPublishDateTime() {
		return $this->publishDateTime;
	}

	/**
	 * Set campus collection
	 *
	 * @param Collection $campusCollection
	 * @return self
	 */
	public function setCampusCollection($campusCollection) {
		$this->campusCollection = $campusCollection;
	 	return $this;
	}

	/**
	 * Get campus collection
	 *
	 * @return Collection|Campus[]
	 */
	public function getCampusCollection() {
		return $this->campusCollection;
	}

	/**
	 * Set role collection
	 *
	 * @param Collection|Role[] $roleCollection
	 * @return self
	 */
	public function setRoleCollection($roleCollection) {
		$this->roleCollection = $roleCollection;
	 	return $this;
	}

	/**
	 * Get role collection
	 *
	 * @return Collection|Role[]
	 */
	public function getRoleCollection() {
		return $this->roleCollection;
	}

	/**
	 * Set attendance dirty bit
	 *
	 * @param boolean $attendanceDirty
	 * @return self
	 */
	public function setAttendanceDirty($attendanceDirty) {
		$this->attendanceDirty = $attendanceDirty;
	 	return $this;
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