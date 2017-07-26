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

use Schoolbox\Calendar\EventType;
use Schoolbox\Collection;
use Schoolbox\Resource\Storage;

class EventMetadata {

	/**
	 * Primary key
	 * 
	 * @var int
	 */
	private $id = null;

	/**
	 * @var EventType
	 */
	private $eventType = null;

	/**
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
	 * @var Collection
	 */
	protected $ramsFiles = null;

	/**
	 * A collection of resource bookings
	 *
	 * @var Collection
	 */
	protected $resourceBookings = null;

    /**
     * Permission slip required
     *
     * @var int
     */
	protected $permissionSlipRequired = 0;

	/**
	 * @var \Alaress_Date_Format
	 */
	private $createdAt = null;

	/**
     * @var \Alaress_Date_Format
	 */
	private $updatedAt = null;

	/**
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
	}

	/* Getters/Setters
	 */

	/**
	 * Set created at
	 *
	 * @param \Alaress_Date_Format $createdAt
     * @return self $this
	 */
	public function setCreatedAt($createdAt) {
		$this->createdAt = $createdAt;
		return $this;
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
     * @return self $this
	 */
	public function setDeletedAt($deletedAt) {
		$this->deletedAt = $deletedAt;
		return $this;
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
     * @return self $this
	 */
	public function setEventType($eventType) {
		$this->eventType = $eventType;
        return $this;
	}

	/**
	 * Get event type
	 *
	 * @return EventTYpe $eventType
	 */
	public function getEventType() {
		return $this->eventType;
	}

	/**
	 * Set id
	 *
	 * @param int $id
     * @return self $this
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
	 * Set rams required
	 *
	 * @param bool $ramsRequired
     * @return self $this
	 */
	public function setRamsRequired($ramsRequired) {
		$this->ramsRequired = $ramsRequired;
		return $this;
	}

	/**
	 * Get rams required
	 *
	 * @return bool
	 */
	public function getRamsRequired() {
		return $this->ramsRequired;
	}

	/**
	 * Set status
	 *
	 * @param EventStatus $status
     * @return self $this
	 */
	public function setStatus($status) {
		$this->status = $status;
		return $this;
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
	 * @param \Alaress_Date_Format $updatedAt
     * @return self $this
	 */
	public function setUpdatedAt($updatedAt) {
		$this->updatedAt = $updatedAt;
		return $this;
	}

	/**
	 * Get updated at
	 *
	 * @return \Alaress_Date_Format
	 */
	public function getUpdatedAt() {
		return $this->updatedAt;
	}

	/**
	 * Set comment
	 *
	 * @param string $comment
     * @return self $this
	 */
	public function setComment($comment) {
		$this->comment = $comment;
		return $this;
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
     * @return self $this
	 */
	public function setActivityLog($activityLog) {
		$this->activityLog = $activityLog;
		return $this;
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
     * @return self $this
	 */
	public function setStaffAttendees($staffAttendees) {
		$this->staffAttendees = $staffAttendees;
		return $this;
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
     * @return self $this
	 */
	public function setStudentAttendees($studentAttendees) {
		$this->studentAttendees = $studentAttendees;
		return $this;
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
     * @return self $this
	 */
	public function setRamsFiles($ramsFiles) {
		$this->ramsFiles = $ramsFiles;
		return $this;
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
     *
	 * @param Collection $resourceBookings
     * @return self $this
	 */
	public function setResourceBookings($resourceBookings) {
		$this->resourceBookings = $resourceBookings;
		return $this;
	}

	/**
	 * Get resource bookings
	 *
	 * @return Collection
	 */
	public function getResourceBookings() {
		return $this->resourceBookings;
	}

    /**
     * @return int
     */
    public function getPermissionSlipRequired()
    {
        return $this->permissionSlipRequired;
    }

    /**
     * @param int $permissionSlipRequired
     * @return self $this
     */
    public function setPermissionSlipRequired($permissionSlipRequired)
    {
        $this->permissionSlipRequired = $permissionSlipRequired;
        return $this;
    }
}