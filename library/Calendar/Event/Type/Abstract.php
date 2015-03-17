<?php

/**
 * Calendar_Event_Type_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Calendar_Event_Type_Abstract
{

    /* These probably exist elsewhere.
     */
    const CAMPUS_ACCESS   = 3;
    const GROUP_ACCESS    = 2;
    const PERSONAL_ACCESS = 1;

    /* Inheritable class vars
     */
    protected $eventId = -1;
    protected $startTime = null;
    protected $endTime = null;

    /* Unholy selection of field names: eventType and type
     *
     * eventType is a human-friendly description of an event's type;
     * e.g. Event, Excursion, Report etc. and comes from a lookup table
     */
    protected $eventType = '';

    /**
     * Event location
     *
     * @var string
     */
    protected $location = '';

    /**
     * Event title
     *
     * @var string
     */
    protected $title = '';

    /**
     * Event description, typically
     *
     * @var string
     */
    protected $detail = '';

    /* An int from the event table corresponding to $eventType
     */
    protected $type = -1;

    /**
     * The ID of the calendar connector this event came from
     *
     * @var int
     */
    protected $connectorId;

    /**
     * Author's name
     *
     * @var string
     */
    protected $authorName = '';

    /**
     * Author's id
     *
     * @var int
     */
    protected $authorId = -1;

    /* A hex string representing the colour of this event
     * This should be deprecated
     */
    protected $colour = '';

    /**
     * Access level
     *
     * @var int
     */
    protected $access = -1;

    /* Category id and name
     * This should be deprecated
     */
    protected $categoryId = -1;
    protected $categoryName = '';

    /**
     * CSV separated list of campus ids relevant to this event.
     *
     * @var string
     */
    protected $campusList = '';

    /**
     * Associated Groups (Type 2)
     *
     * @var null
     */
    protected $categoryCollection = null;

    /**
     * The class used to render this event object. This should be
     * just the class' name, not an actual instance of it
     *
     * @var string
     */
    protected $rendererClass = '';

    /**
     * Whether or not to display the modify link
     *
     * @var bool
     */
    protected $isEditable = true;

    /**
     * The date when the event was published (made visible to users)
     * Stored always as an Alaress_Date_Format() instance
     *
     * @var Alaress_Date_Format
     */
    protected $publishDate = null;

    /**
     * A generic variable for custom stuff
     *
     * @var array
     */
    protected $custom = array();

    /**
     * Is this an all day event?
     *
     * @var bool
     */
    protected $allday = false;

    /**
     * Whether or not this Task has been completed
     *
     * @var int
     */
    protected $completed = 0;

    /**
     * Constructor
     *
     * Set up default settings
     */
    public function __construct()
    {
        $this->setup();
    }

    /**
     * Use Visitor design pattern to render via an external class
     *
     * @abstract
     *
     * @param $visitor
     */
    abstract public function accept($visitor);

    /**
     * Hook method for your own customisations
     *
     * Empty by default
     */
    protected function setup()
    {
    }

    /**
     * Find out if the currently logged in user is the owner (creator)
     * of this event
     *
     * @return bool
     */
    public function isOwner()
    {
        if ($this->getAuthorId() == $_SESSION['id']) {
            return true;
        }

        return false;
    }

    /**
     * Is campus access or not
     *
     * @return bool
     */
    protected function isCampusAccess()
    {
        if ($this->getAccess() == self::CAMPUS_ACCESS) {
            return true;
        }

        return false;
    }

    /**
     * is group access or not
     *
     * @return bool
     */
    protected function isGroupAccess()
    {
        if ($this->getAccess() == self::GROUP_ACCESS) {
            return true;
        }

        return false;
    }

    /**
     * Get a string representing the full span of time for this event
     * This is used in the calendar dashboard
     *
     * @return string
     */
    public function getEventSpan()
    {
        if ($this->getStartTime()->getDatabaseDate() != $this->getEndTime()->getDatabaseDate()) {
            if ($this->getAllDay()) {
                return $this->getStartTime()->getCalendarDate() . ' &ndash; ' . $this->getEndTime()->getCalendarDate();
            }
            return $this->getStartTime()->getCalendarDateTime() . ' &ndash; ' . $this->getEndTime(
            )->getCalendarDateTime();
        } else {
            if ($this->getAllDay()) {
                return $this->getStartTime()->getCalendarDate();
            }
            return $this->getStartTime()->getCalendarDateTime() . ' &ndash; ' . $this->getEndTime()->getTimeOnly();
        }
    }

    /**
     * Gets date of event in format like this: Thursday, 19th December, 2:00AM
     *
     * @return string
     */
    public function getFullDate()
    {
        $startDate = $this->getStartTime()->getLongDateOnly();
        if (!$this->getAllDay()) {
            $formattedTime = $startDate . ', ' . $this->getTime();
        } else {
            $formattedTime = $startDate;
        }
        return $formattedTime;
    }

    /**
     * Special time formattng
     *
     * @return string
     */
    public function getTime()
    {
        if ($this->getAllDay()) {
            return false;
        } else {
            return $this->getStartTime()->getTimeOnly();
        }
    }

    /* Getters/Setters
     */

    /**
     * Set author
     *
     * @param $authorName
     */
    public function setAuthorName($authorName)
    {
        $this->authorName = $authorName;
    }

    /**
     * Get author
     *
     * @return null
     */
    public function getAuthorName()
    {
        return $this->authorName;
    }

    /**
     * Set author id
     *
     * @param $authorId
     */
    public function setAuthorId($authorId)
    {
        $this->authorId = $authorId;
    }

    /**
     * Get author id
     *
     * @return null
     */
    public function getAuthorId()
    {
        return $this->authorId;
    }

    /**
     * Set colour
     *
     * @param $colour
     */
    public function setColour($colour)
    {
        if ($colour[0] != '#') {
            $this->colour = '#' . $colour;
        } else {
            $this->colour = $colour;
        }
    }

    /**
     * Get colour
     *
     * @return null
     */
    public function getColour()
    {
        return $this->colour;
    }

    /**
     * Set detail
     *
     * @param $detail
     */
    public function setDetail($detail)
    {
        $this->detail = $detail;
    }

    /**
     * Get detail
     *
     * @return null
     */
    public function getDetail()
    {
        return $this->detail;
    }

    /**
     * Set event type
     *
     * @param $eventType
     */
    public function setEventType($eventType)
    {
        $this->eventType = $eventType;
    }

    /**
     * Get event type
     *
     * @return null
     */
    public function getEventType()
    {
        return $this->eventType;
    }

    /**
     * Set location
     *
     * @param $location
     */
    public function setLocation($location)
    {
        $this->location = $location;
    }

    /**
     * Get location
     *
     * @return null
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * Set title
     *
     * @param $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * Get title
     *
     * @return null
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set type
     *
     * @param $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Get type
     *
     * @return null
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set event id
     *
     * @param $eventId
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
     * Set access
     *
     * @param $access
     */
    public function setAccess($access)
    {
        $this->access = $access;
    }

    /**
     * Get access
     *
     * @return int
     */
    public function getAccess()
    {
        return $this->access;
    }

    /**
     * Set campus list
     *
     * @param $campusList
     */
    public function setCampusList($campusList)
    {
        $this->campusList = $campusList;
    }

    /**
     * Get campus list
     *
     * @return null
     */
    public function getCampusList()
    {
        return $this->campusList;
    }

    /**
     * Set end time
     *
     * @param $endTime
     */
    public function setEndTime($endTime)
    {
        $this->endTime = $endTime;
    }

    /**
     * Get end time
     *
     * @return Alaress_Date_Format
     */
    public function getEndTime()
    {
        return $this->endTime;
    }

    /**
     * Set start time
     *
     * @param $startTime
     */
    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;
    }

    /**
     * Get start time
     *
     * @return Alaress_Date_Format
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * Set category id
     *
     * @param $categoryId
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

    /**
     * Set category name
     *
     * @param $categoryName
     */
    public function setCategoryName($categoryName)
    {
        $this->categoryName = $categoryName;
    }

    /**
     * Get category name
     *
     * @return string
     */
    public function getCategoryName()
    {
        return $this->categoryName;
    }

    /**
     * Set renderer class
     *
     * @param $rendererClass
     */
    public function setRendererClass($rendererClass)
    {
        $this->rendererClass = $rendererClass;
    }

    /**
     * Get renderer class
     *
     * @return string
     */
    public function getRendererClass()
    {
        return $this->rendererClass;
    }

    /**
     * Set isEditable
     *
     * @param $isEditable
     */
    public function setIsEditable($isEditable)
    {
        $this->isEditable = $isEditable;
    }

    /**
     * Get isEditable
     *
     * @return bool
     */
    public function getIsEditable()
    {
        return $this->isEditable;
    }

    /**
     * Set completed
     *
     * @param int $completed
     */
    public function setCompleted($completed)
    {
        $this->completed = $completed;
    }

    /**
     * Get completed
     *
     * @return int
     */
    public function getCompleted()
    {
        return $this->completed;
    }

    /**
     * Set connector id
     *
     * @param string $connectorId
     */
    public function setConnectorId($connectorId)
    {
        $this->connectorId = $connectorId;
    }

    /**
     * Get connector id
     *
     * @return string
     */
    public function getConnectorId()
    {
        return $this->connectorId;
    }

    /**
     * @param bool $allday
     */
    public function setAllday($allday)
    {
        $this->allday = $allday;
    }

    /**
     * @return bool
     */
    public function getAllday()
    {
        return $this->allday;
    }

    /**
     * Set publish date object
     *
     * @param Alaress_Date_Format $publishDate
     */
    public function setPublishDate($publishDate)
    {
        $this->publishDate = new Alaress_Date_Format($publishDate ? : '1970-01-01 00:00:00', 'Y-m-d H:i:s');
    }

    /**
     * Get publish date
     *
     * @return Alaress_Date_Format
     */
    public function getPublishDate()
    {
        return $this->publishDate;
    }

    /**
     * Can someone attend this event? Defaults to false, subclasses can override.
     *
     * @return bool
     */
    public function canAttend()
    {
        return false;
    }

    /**
     * Set custom
     *
     * @param string $custom
     */
    public function setCustom($custom)
    {
        $this->custom = $custom;
    }

    /**
     * Get custom
     *
     * @return string
     */
    public function getCustom()
    {
        return $this->custom;
    }

    /**
     * Set category collection
     *
     * @param null $categoryCollection
     */
    public function setCategoryCollection($categoryCollection)
    {
        $this->categoryCollection = $categoryCollection;
    }

    /**
     * Get category collection
     *
     * @return null
     */
    public function getCategoryCollection()
    {
        return $this->categoryCollection;
    }
}
