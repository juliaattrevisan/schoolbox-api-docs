<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar\Relation;

/**
 * Class Role
 *
 * Relationship between an Event and a Role
 *
 * @package Schoolbox\Calendar\Relation
 */
class Role
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
    private $roleId;


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
     * Set role id
     *
     * @param int $roleId
     * @return $this
     */
    public function setRoleId(int $roleId): self
    {
        $this->roleId = $roleId;
        return $this;
    }


    /**
     * Get role id
     *
     * @return int
     */
    public function getRoleId(): int
    {
        return $this->roleId;
    }
}
