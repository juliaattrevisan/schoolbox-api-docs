<?php

/**
 * Response
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC\ListGroup;

class Response
{

    /**
     * The next status for the Event, corresponding to its
     * crurent status and configuration
     *
     * @var int
     */
    private $nextStatus = 0;

    /**
     * The Event List Group Index corresponding to this
     * event's current status and configuration
     *
     * @var int
     */
    private $listGroupIndex = -1;

    /**
     * Constructor
     *
     * @param int $nextStatus
     * @param int $listGroupIndex
     */
    public function __construct($nextStatus, $listGroupIndex)
    {
        $this->setNextStatus($nextStatus);
        $this->setListGroupIndex($listGroupIndex);
    }

    /* Getters/Setters
     */

    /**
     * Set list group index
     *
     * @param int $listGroupIndex
     */
    public function setListGroupIndex($listGroupIndex)
    {
        $this->listGroupIndex = $listGroupIndex;
    }

    /**
     * Set list group index
     *
     * @return int
     */
    public function getListGroupIndex()
    {
        return $this->listGroupIndex;
    }

    /**
     * Set next status
     *
     * @param int $nextStatus
     */
    public function setNextStatus($nextStatus)
    {
        $this->nextStatus = $nextStatus;
    }

    /**
     * Get next status
     *
     * @return int
     */
    public function getNextStatus()
    {
        return $this->nextStatus;
    }
}
