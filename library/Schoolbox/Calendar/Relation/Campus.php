<?php

/**
 * Sid
 *
 * I spoke with Boomerang about this.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar\Relation;

class Campus
{

    /**
     * eventId to which this sid belongs
     *
     * @var null
     */
    private $eventId = null;

    /**
     * Sid value
     *
     * @var null
     */
    private $campusId = null;

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /* Getters/Setters
     */

    /**
     * Set event id
     *
     * @param null $eventId
     * @return $this
     */
    public function setEventId($eventId)
    {
        $this->eventId = $eventId;
        return $this;
    }

    /**
     * Get event id
     *
     * @return null
     */
    public function getEventId()
    {
        return $this->eventId;
    }

    /**
     * Set campus id
     *
     * @param null $campusId
     * @return $this
     */
    public function setCampusId($campusId)
    {
        $this->campusId = $campusId;
        return $this;
    }

    /**
     * Get campus id
     *
     * @return null
     */
    public function getCampusId()
    {
        return $this->campusId;
    }
}
