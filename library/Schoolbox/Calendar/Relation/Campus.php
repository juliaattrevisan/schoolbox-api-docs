<?php
/**
 * Sid
 *
 * I spoke with Boomerang about this.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
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
     */
    public function setEventId($eventId)
    {
        $this->eventId = $eventId;
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
     */
    public function setCampusId($campusId)
    {
        $this->campusId = $campusId;
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