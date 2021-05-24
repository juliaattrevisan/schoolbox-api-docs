<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar\Relation;

/**
 * Class Category
 *
 * Relationship between an Event and a Folder
 *
 * @package Schoolbox\Calendar\Relation
 */
class Category
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
    private $categoryId;


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
     * Set category id
     *
     * @param int $categoryId
     * @return $this
     */
    public function setCategoryId(int $categoryId): self
    {
        $this->categoryId = $categoryId;
        return $this;
    }


    /**
     * Get category id
     *
     * @return int
     */
    public function getCategoryId(): int
    {
        return $this->categoryId;
    }
}
