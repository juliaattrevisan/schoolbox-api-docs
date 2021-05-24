<?php

/**
 * EventMetadata
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC;

use EOTC\ActivityLog\Log as ActivityLog;
use Schoolbox\Calendar\EventType;
use Schoolbox\Collection;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\ResourceBooking\Booking\Model as ResourceBooking;

class EventMetadata
{

    /**
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
     * @var bool
     */
    private $ramsRequired = false;

    /**
     * @var string
     */
    private $comment = '';

    /**
     * @var Collection|ActivityLog[]
     */
    protected $activityLog = null;

    /**
     * @var Collection|User[]
     */
    protected $staffAttendees = null;

    /**
     * @var Collection|User[]
     */
    protected $studentAttendees = null;

    /**
     * @var Collection|Storage\Model[]
     */
    protected $ramsFiles = null;

    /**
     * @var Collection|ResourceBooking[]
     */
    protected $resourceBookings = null;

    /**
     * @var bool
     */
    protected $permissionSlipRequired = false;

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
    public function __construct()
    {
        $this->setStaffAttendees(new Collection());
        $this->setStudentAttendees(new Collection());
        $this->setRamsFiles(new Collection());
        $this->setResourceBookings(new Collection());
    }

    /* Getters/Setters
     */

    /**
     * @param \Alaress_Date_Format $createdAt
     * @return self $this
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param \Alaress_Date_Format $deletedAt
     * @return self $this
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    /**
     * @param EventType $eventType
     * @return self $this
     */
    public function setEventType($eventType)
    {
        $this->eventType = $eventType;
        return $this;
    }

    /**
     * @return EventType $eventType
     */
    public function getEventType()
    {
        return $this->eventType;
    }

    /**
     * @param int $id
     * @return self $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param bool $ramsRequired
     * @return self $this
     */
    public function setRamsRequired($ramsRequired)
    {
        $this->ramsRequired = $ramsRequired;
        return $this;
    }

    /**
     * @return bool
     */
    public function getRamsRequired()
    {
        return $this->ramsRequired;
    }

    /**
     * @param EventStatus $status
     * @return self $this
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return EventStatus
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param \Alaress_Date_Format $updatedAt
     * @return self $this
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param string $comment
     * @return self $this
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @param Collection $activityLog
     * @return self $this
     */
    public function setActivityLog($activityLog)
    {
        $this->activityLog = $activityLog;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getActivityLog()
    {
        return $this->activityLog;
    }

    /**
     * @param Collection $staffAttendees
     * @return self $this
     */
    public function setStaffAttendees($staffAttendees)
    {
        $this->staffAttendees = $staffAttendees;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getStaffAttendees()
    {
        return $this->staffAttendees;
    }

    /**
     * @param Collection $studentAttendees
     * @return self $this
     */
    public function setStudentAttendees($studentAttendees)
    {
        $this->studentAttendees = $studentAttendees;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getStudentAttendees()
    {
        return $this->studentAttendees;
    }

    /**
     * @param Collection $ramsFiles
     * @return self $this
     */
    public function setRamsFiles($ramsFiles)
    {
        $this->ramsFiles = $ramsFiles;
        return $this;
    }

    /**
     * @return Collection|Storage\Model[]
     */
    public function getRamsFiles()
    {
        return $this->ramsFiles;
    }

    /**
     * @param Collection $resourceBookings
     * @return self $this
     */
    public function setResourceBookings($resourceBookings)
    {
        $this->resourceBookings = $resourceBookings;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getResourceBookings()
    {
        return $this->resourceBookings;
    }

    /**
     * @return bool
     */
    public function getPermissionSlipRequired()
    {
        return $this->permissionSlipRequired;
    }

    /**
     * @param bool $permissionSlipRequired
     * @return self $this
     */
    public function setPermissionSlipRequired($permissionSlipRequired)
    {
        $this->permissionSlipRequired = $permissionSlipRequired;
        return $this;
    }
}
