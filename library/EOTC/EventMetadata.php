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
	 * @var int
	 */
	private $eventType = -1;

	/**
	 * An instance of EventStatus
	 *
	 * @var int
	 */
	private $status = 1;

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
	 * @var null
	 */
	protected $activityLog = null;

	/**
	 * A Collection of User objects
	 *
	 * @var null
	 */
	protected $staffAttendees = null;

	/**
	 * A Collection of User objects
	 *
	 * @var null
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
	 * @var null
	 */
	protected $resourceBookings = null;

	/**
	 * Created at
	 *
	 * @var null
	 */
	private $createdAt = null;

	/**
	 * Updated at
	 *
	 * @var null
	 */
	private $updatedAt = null;

	/**
	 * Deleted at
	 *
	 * @var null
	 */
	private $deletedAt = null;

	/**
	 * Construtor
	 */
	public function __construct() {
		$this->setStaffAttendees(new \Schoolbox\Collection());
		$this->setStudentAttendees(new \Schoolbox\Collection());
		$this->setRamsFiles(new \Schoolbox\Collection());
		$this->setResourceBookings(new \Schoolbox\Collection());
	}

	/* Getters/Setters
	 */

	/**
	 * Set created at
	 *
	 * @param null $createdAt
	 */
	public function setCreatedAt($createdAt) {
		$this->createdAt = $createdAt;
	}

	/**
	 * Get created at
	 *
	 * @return null
	 */
	public function getCreatedAt() {
		return $this->createdAt;
	}

	/**
	 * Set deleted at
	 *
	 * @param null $deletedAt
	 */
	public function setDeletedAt($deletedAt) {
		$this->deletedAt = $deletedAt;
	}

	/**
	 * Get deleted at
	 *
	 * @return null
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
	 * @param int $status
	 */
	public function setStatus($status) {
		$this->status = $status;
	}

	/**
	 * Get status
	 *
	 * @return int
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
	 * @param null $activityLog
	 */
	public function setActivityLog($activityLog) {
		$this->activityLog = $activityLog;
	}

	/**
	 * Get activity log
	 *
	 * @return null
	 */
	public function getActivityLog() {
		return $this->activityLog;
	}

	/**
	 * Set staff attendees
	 *
	 * @param null $staffAttendees
	 */
	public function setStaffAttendees($staffAttendees) {
		$this->staffAttendees = $staffAttendees;
	}

	/**
	 * Get staff attendees
	 *
	 * @return null
	 */
	public function getStaffAttendees() {
		return $this->staffAttendees;
	}

	/**
	 * Set student attendees
	 *
	 * @param null $studentAttendees
	 */
	public function setStudentAttendees($studentAttendees) {
		$this->studentAttendees = $studentAttendees;
	}

	/**
	 * Get student attendees
	 *
	 * @return null
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
	 * @param null $resourceBookings
	 */
	public function setResourceBookings($resourceBookings) {
		$this->resourceBookings = $resourceBookings;
	}

	/**
	 * Get resource bookings
	 *
	 * @return null
	 */
	public function getResourceBookings() {
		return $this->resourceBookings;
	}
}