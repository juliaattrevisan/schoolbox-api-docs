<?php
/**
 * EventMetadata
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;


use Schoolbox\Collection;
use Schoolbox\Resource\Storage;

class EventMetadata {

	/**
	 * Primary key
	 * 
	 * @var null
	 */
	private $id = null;

	/**
	 * An instance of Calendar\EventType
	 *
	 * @var \Schoolbox\Calendar\EventType
	 */
	private $eventType = null;

	/**
	 * An instance of EventStatus
	 *
	 * @var EventStatus
	 */
	private $status = null;

	/**
	 * Whether RAMS is selected for this event
	 *
	 * @var bool
	 */
	private $ramsRequired = false;

	/**
	 * The latest comment about the event
	 *
	 * @var string
	 */
	private $comment = '';

	/**
	 * A collection of ActivityLog
	 *
	 * @var Collection
	 */
	protected $activityLog = null;

	/**
	 * A Collection of User objects
	 *
	 * @var Collection
	 */
	protected $staffAttendees = null;

	/**
	 * A Collection of User objects
	 *
	 * @var Collection
	 */
	protected $studentAttendees = null;

	/**
	 * A collection of brownhole files
	 *
	 * @var null
	 */
	protected $ramsFiles = null;

	/**
	 * A collection of resource bookings
	 *
	 * @var Collection
	 */
	protected $resourceBookings = null;

	/**
	 * Created at
	 *
	 * @var \Alaress_Date_Format
	 */
	private $createdAt = null;

	/**
	 * Updated at
	 *
	 * @var \Alaress_Date_Format
	 */
	private $updatedAt = null;

	/**
	 * Deleted at
	 *
	 * @var \Alaress_Date_Format
	 */
	private $deletedAt = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setStaffAttendees(new Collection());
		$this->setStudentAttendees(new Collection());
		$this->setRamsFiles(new Collection());
		$this->setResourceBookings(new Collection());

//		/** @var \EOTC\EventStatus $eventStatus */
//		$eventStatus = EventStatusMapper::getInstance()->findById(1);
//		$this->setStatus($eventStatus);
	}

	/* Getters/Setters
	 */

	/**
	 * Set created at
	 *
	 * @param \Alaress_Date_Format $createdAt
	 */
	public function setCreatedAt($createdAt) {
		$this->createdAt = $createdAt;
	}

	/**
	 * Get created at
	 *
	 * @return \Alaress_Date_Format
	 */
	public function getCreatedAt() {
		return $this->createdAt;
	}

	/**
	 * Set deleted at
	 *
	 * @param \Alaress_Date_Format $deletedAt
	 */
	public function setDeletedAt($deletedAt) {
		$this->deletedAt = $deletedAt;
	}

	/**
	 * Get deleted at
	 *
	 * @return \Alaress_Date_Format
	 */
	public function getDeletedAt() {
		return $this->deletedAt;
	}

	/**
	 * Set event type
	 *
	 * @param int $eventType
	 */
	public function setEventType($eventType) {
		$this->eventType = $eventType;
	}

	/**
	 * Get event type
	 *
	 * @return int
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
	 * Set rams required
	 *
	 * @param boolean $ramsRequired
	 */
	public function setRamsRequired($ramsRequired) {
		$this->ramsRequired = $ramsRequired;
	}

	/**
	 * Get rams required
	 *
	 * @return boolean
	 */
	public function getRamsRequired() {
		return $this->ramsRequired;
	}

	/**
	 * Set status
	 *
	 * @param EventStatus $status
	 */
	public function setStatus($status) {
		$this->status = $status;
	}

	/**
	 * Get status
	 *
	 * @return EventStatus
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * Set updated at
	 *
	 * @param null $updatedAt
	 */
	public function setUpdatedAt($updatedAt) {
		$this->updatedAt = $updatedAt;
	}

	/**
	 * Get updated at
	 *
	 * @return null
	 */
	public function getUpdatedAt() {
		return $this->updatedAt;
	}

	/**
	 * Set comment
	 *
	 * @param string $comment
	 */
	public function setComment($comment) {
		$this->comment = $comment;
	}

	/**
	 * Get comment
	 *
	 * @return string
	 */
	public function getComment() {
		return $this->comment;
	}

	/**
	 * Set activity log
	 *
	 * @param Collection $activityLog
	 */
	public function setActivityLog($activityLog) {
		$this->activityLog = $activityLog;
	}

	/**
	 * Get activity log
	 *
	 * @return Collection
	 */
	public function getActivityLog() {
		return $this->activityLog;
	}

	/**
	 * Set staff attendees
	 *
	 * @param Collection $staffAttendees
	 */
	public function setStaffAttendees($staffAttendees) {
		$this->staffAttendees = $staffAttendees;
	}

	/**
	 * Get staff attendees
	 *
	 * @return Collection
	 */
	public function getStaffAttendees() {
		return $this->staffAttendees;
	}

	/**
	 * Set student attendees
	 *
	 * @param Collection $studentAttendees
	 */
	public function setStudentAttendees($studentAttendees) {
		$this->studentAttendees = $studentAttendees;
	}

	/**
	 * Get student attendees
	 *
	 * @return Collection
	 */
	public function getStudentAttendees() {
		return $this->studentAttendees;
	}

	/**
	 * Set rams files
	 *
	 * @param Collection $ramsFiles
	 */
	public function setRamsFiles($ramsFiles) {
		$this->ramsFiles = $ramsFiles;
	}

	/**
	 * Get rams files
	 *
	 * @return Collection|Storage\Model[]
	 */
	public function getRamsFiles() {
		return $this->ramsFiles;
	}

	/**
	 * Set resource bookings
	 * @param Collection $resourceBookings
	 */
	public function setResourceBookings($resourceBookings) {
		$this->resourceBookings = $resourceBookings;
	}

	/**
	 * Get resource bookings
	 *
	 * @return Collection
	 */
	public function getResourceBookings() {
		return $this->resourceBookings;
	}
}