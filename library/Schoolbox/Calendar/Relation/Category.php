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

class Category
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
     * Set category id
     *
     * @param null $categoryId
     */
    public function setCategoryId($categoryId)
    {
        $this->categoryId = $categoryId;
    }

    /**
     * Get category id
     *
     * @return null
     */
    public function getCategoryId()
    {
        return $this->categoryId;
    }
}