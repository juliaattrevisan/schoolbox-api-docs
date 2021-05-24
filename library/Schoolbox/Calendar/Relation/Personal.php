<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar\Relation;

/**
 * Class Personal
 *
 * Relationship between an Event and a User
 *
 * @package Schoolbox\Calendar\Relation
 */
class Personal
{

    /**
     * eventId to which this sid belongs
     *
     * @var int
     */
    private $eventId;


    /**
     * Sid value
     *
     * @var int
     */
    private $userId;


    /* Getters/Setters
     */


    /**
     * Set event id
     *
     * @param int $eventId
     * @return $this
     */
    public function setEventId(int $eventId): self
    {
        $this->eventId = $eventId;
        return $this;
    }


    /**
     * Get event id
     *
     * @return int
     */
    public function getEventId(): int
    {
        return $this->eventId;
    }


    /**
     * Set user id
     *
     * @param int $userId
     * @return $this
     */
    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }


    /**
     * Get user id
     *
     * @return int
     */
    public function getUserId(): int
    {
        return $this->userId;
    }
}
