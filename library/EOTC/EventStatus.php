<?php

/**
 * EventStatus
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC;

class EventStatus
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * A unidirectional map of css classes belong to each status
     *
     * @var array
     */
    private $cssMap = array(
        1 => 'status-draft',
        2 => 'status-in-principle',
        3 => 'status-rams-approved',
        4 => 'status-rams-confirmed',
        5 => 'status-approved',
        6 => 'status-rejected',
    );

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Determine if the event status is between $status1 and $status2
     * Status ids are sequential
     *
     * @param int $status1 A const from EventStatusMapper
     * @param int $status2 A const from EventStatusMapper
     * @return bool
     */
    public function between($status1, $status2)
    {
        return ($this->getId() >= $status1) && ($this->getId() <= $status2);
    }

    /**
     * Determine whether this event is in Draft
     *
     * @return bool
     */
    public function isDraft()
    {
        if ($this->getId() == EventStatusMapper::STATUS_DRAFT) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether this event is In Principle
     *
     * @return bool
     */
    public function isInPrinciple()
    {
        if ($this->getId() == EventStatusMapper::STATUS_INPRINCIPLE) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether this event is Details Completed
     *
     * @return bool
     */
    public function isDetailsCompleted()
    {
        if ($this->getId() == EventStatusMapper::STATUS_DETAILSCOMPLETED) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether this event is Awaiting Final Approval
     *
     * @return bool
     */
    public function isAwaitingFinalApproval()
    {
        if ($this->getId() == EventStatusMapper::STATUS_AWAITINGFINALAPPROVAL) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether this event is Final Approved
     *
     * @return bool
     */
    public function isApproved()
    {
        if ($this->getId() == EventStatusMapper::STATUS_APPROVED) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether this event is Final Approved
     *
     * @return bool
     */
    public function isRejected()
    {
        if ($this->getId() == EventStatusMapper::STATUS_REJECTED) {
            return true;
        }

        return false;
    }

    /**
     * Get the css style associated with this status
     *
     * @return mixed
     */
    public function getStyle()
    {
        return $this->cssMap[$this->getId()];
    }

    /* Getters/Setters
     */

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
     * @param string $name
     * @return self $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}
