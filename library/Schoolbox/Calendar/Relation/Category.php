<?php
/**
 * Sid
 *
 * I spoke with Boomerang about this.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Calendar\Relation;

class Category
{

    /**
     * eventId to which this sid belongs
     *
     * @var int
     */
    private $eventId = null;

    /**
     * Sid value
     *
     * @var int
     */
    private $categoryId = null;

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
     * @param int $eventId
     */
    public function setEventId($eventId)
    {
        $this->eventId = $eventId;
    }

    /**
     * Get event id
     *
     * @return int
     */
    public function getEventId()
    {
        return $this->eventId;
    }

    /**
     * Set category id
     *
     * @param int $categoryId
     */
    public function setCategoryId($categoryId)
    {
        $this->categoryId = $categoryId;
    }

    /**
     * Get category id
     *
     * @return int
     */
    public function getCategoryId()
    {
        return $this->categoryId;
    }
}