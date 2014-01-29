<?php
/**
 * Event
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC\Calendar;


class Event {

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
	 * The date when the event was published (made visible to users)
	 * An instance Alaress_Date_Format()
	 *
	 * @var null
	 */
	protected $publishDate = null;

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

		$this->setStartDate($startDate);
		$this->setEndDate($endDate);
	}
	/**
	 * Ensure that the user hasn't modified the URL in an untoward manner.
	 * If they have, or it's somehow absent, default to week view.
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
	 * @param int $access
	 */
	public function setAccess($access) {
		$this->access = $access;
	}

	/**
	 * @return int
	 */
	public function getAccess() {
		return $this->access;
	}

	/**
	 * @param int $allDay
	 */
	public function setAllDay($allDay) {
		$this->allDay = $allDay;
	}

	/**
	 * @return int
	 */
	public function getAllDay() {
		return $this->allDay;
	}

	/**
	 * @param int $attendance
	 */
	public function setAttendance($attendance) {
		$this->attendance = $attendance;
	}

	/**
	 * @return int
	 */
	public function getAttendance() {
		return $this->attendance;
	}

	/**
	 * @param null $author
	 */
	public function setAuthor($author) {
		$this->author = $author;
	}

	/**
	 * @return null
	 */
	public function getAuthor() {
		return $this->author;
	}

	/**
	 * @param null $category
	 */
	public function setCategory($category) {
		$this->category = $category;
	}

	/**
	 * @return null
	 */
	public function getCategory() {
		return $this->category;
	}

	/**
	 * @param string $colour
	 */
	public function setColour($colour) {
		$this->colour = $colour;
	}

	/**
	 * @return string
	 */
	public function getColour() {
		return $this->colour;
	}

	/**
	 * @param int $completed
	 */
	public function setCompleted($completed) {
		$this->completed = $completed;
	}

	/**
	 * @return int
	 */
	public function getCompleted() {
		return $this->completed;
	}

	/**
	 * @param string $detail
	 */
	public function setDetail($detail) {
		$this->detail = $detail;
	}

	/**
	 * @return string
	 */
	public function getDetail() {
		return $this->detail;
	}

	/**
	 * @param null $endDateTime
	 */
	public function setEndDateTime($endDateTime) {
		$this->endDateTime = $endDateTime;
	}

	/**
	 * @return null
	 */
	public function getEndDateTime() {
		return $this->endDateTime;
	}

	/**
	 * @param string $eventType
	 */
	public function setEventType($eventType) {
		$this->eventType = $eventType;
	}

	/**
	 * @return string
	 */
	public function getEventType() {
		return $this->eventType;
	}

	/**
	 * @param null $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * @return null
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @param string $location
	 */
	public function setLocation($location) {
		$this->location = $location;
	}

	/**
	 * @return string
	 */
	public function getLocation() {
		return $this->location;
	}

	/**
	 * @param string $note
	 */
	public function setNote($note) {
		$this->note = $note;
	}

	/**
	 * @return string
	 */
	public function getNote() {
		return $this->note;
	}

	/**
	 * @param null $owner
	 */
	public function setOwner($owner) {
		$this->owner = $owner;
	}

	/**
	 * @return null
	 */
	public function getOwner() {
		return $this->owner;
	}

	/**
	 * @param null $publishDate
	 */
	public function setPublishDate($publishDate) {
		$this->publishDate = $publishDate;
	}

	/**
	 * @return null
	 */
	public function getPublishDate() {
		return $this->publishDate;
	}

	/**
	 * @param null $seriesId
	 */
	public function setSeriesId($seriesId) {
		$this->seriesId = $seriesId;
	}

	/**
	 * @return null
	 */
	public function getSeriesId() {
		return $this->seriesId;
	}

	/**
	 * @param int $sid
	 */
	public function setSid($sid) {
		$this->sid = $sid;
	}

	/**
	 * @return int
	 */
	public function getSid() {
		return $this->sid;
	}

	/**
	 * @param null $startDateTime
	 */
	public function setStartDateTime($startDateTime) {
		$this->startDateTime = $startDateTime;
	}

	/**
	 * @return null
	 */
	public function getStartDateTime() {
		return $this->startDateTime;
	}

	/**
	 * @param int $type
	 */
	public function setType($type) {
		$this->type = $type;
	}

	/**
	 * @return int
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @param string $endDate
	 */
	public function setEndDate($endDate) {
		$this->endDate = $endDate;
	}

	/**
	 * @return string
	 */
	public function getEndDate() {
		return $this->endDate;
	}

	/**
	 * @param string $endTime
	 */
	public function setEndTime($endTime) {
		$this->endTime = $endTime;
	}

	/**
	 * @return string
	 */
	public function getEndTime() {
		return $this->endTime;
	}

	/**
	 * @param string $startDate
	 */
	public function setStartDate($startDate) {
		$this->startDate = $startDate;
	}

	/**
	 * @return string
	 */
	public function getStartDate() {
		return $this->startDate;
	}

	/**
	 * @param string $startTime
	 */
	public function setStartTime($startTime) {
		$this->startTime = $startTime;
	}

	/**
	 * @return string
	 */
	public function getStartTime() {
		return $this->startTime;
	}
}