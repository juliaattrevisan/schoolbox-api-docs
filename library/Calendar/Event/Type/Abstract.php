<?php
/**
 * Calendar_Event_Type_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Calendar_Event_Type_Abstract {

    /* These probably exist elsewhere.
     */
    const CAMPUS_ACCESS = 3;
    const GROUP_ACCESS = 2;

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

    /* Location, short description and long description of the event
     */
    protected $location = '';
    protected $note = '';
    protected $detail = '';

    /* An int from the event table corresponding to $eventType
     */
    protected $type = -1;

    /* Event author's name and id
     */
    protected $authorName = '';
    protected $authorId = -1;

    /* A hex string representing the colour of this event
     */
    protected $colour = '';

    /* Access level
     */
    protected $access = -1;

    /* Category id and name
     */
    protected $categoryId = -1;
    protected $categoryName = '';

    /* CSV separated list of campus ids relevant to this event.
     */
    protected $campusList = '';

    /* sid ><
     */
    protected $sid = -1;

    /* The class used to render this event object. This should be
     * just the class' name, not an actual instance of it
     */
    protected $rendererClass = '';

    /* Whether or not to display the modify link
     */
    protected $isEditable = true;

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
    public function __construct() {
        $this->setup();
    }

    /**
     * Use Visitor design pattern to render via an external class
     *
     * @abstract
     * @param $visitor
     */
    abstract public function accept($visitor);

    /**
     * Hook method for your own customisations
     *
     * Empty by default
     */
    protected function setup() {
    }

    /**
     * Find out if the currently logged in user is the owner (creator)
     * of this event
     *
     * @return bool
     */
    public function isOwner() {
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
    protected function isCampusAccess() {
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
    protected function isGroupAccess() {
        if ($this->getAccess() == self::GROUP_ACCESS) {
            return true;
        }

        return false;
    }

    /* Getters/Setters
     */

    /**
     * Set author
     *
     * @param $authorName
     */
    public function setAuthorName($authorName) {
        $this->authorName = $authorName;
    }

    /**
     * Get author
     *
     * @return null
     */
    public function getAuthorName() {
        return $this->authorName;
    }

    /**
     * Set author id
     *
     * @param $authorId
     */
    public function setAuthorId($authorId) {
        $this->authorId = $authorId;
    }

    /**
     * Get author id
     *
     * @return null
     */
    public function getAuthorId() {
        return $this->authorId;
    }

    /**
     * Set colour
     *
     * @param $colour
     */
    public function setColour($colour) {
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
    public function getColour() {
        return $this->colour;
    }

    /**
     * Set detail
     *
     * @param $detail
     */
    public function setDetail($detail) {
        $this->detail = $detail;
    }

    /**
     * Get detail
     *
     * @return null
     */
    public function getDetail() {
        return $this->detail;
    }

    /**
     * Set event type
     *
     * @param $eventType
     */
    public function setEventType($eventType) {
        $this->eventType = $eventType;
    }

    /**
     * Get event type
     *
     * @return null
     */
    public function getEventType() {
        return $this->eventType;
    }

    /**
     * Set location
     *
     * @param $location
     */
    public function setLocation($location) {
        $this->location = $location;
    }

    /**
     * Get location
     *
     * @return null
     */
    public function getLocation() {
        return $this->location;
    }

    /**
     * Set note
     *
     * @param $note
     */
    public function setNote($note) {
        $this->note = $note;
    }

    /**
     * Get note
     *
     * @return null
     */
    public function getNote() {
        return $this->note;
    }

    /**
     * Set type
     *
     * @param $type
     */
    public function setType($type) {
        $this->type = $type;
    }

    /**
     * Get type
     *
     * @return null
     */
    public function getType() {
        return $this->type;
    }

    /**
     * Set event id
     *
     * @param $eventId
     */
    public function setEventId($eventId) {
        $this->eventId = $eventId;
    }

    /**
     * Get event id
     *
     * @return null
     */
    public function getEventId() {
        return $this->eventId;
    }

    /**
     * Set access
     *
     * @param $access
     */
    public function setAccess($access) {
        $this->access = $access;
    }

    /**
     * Get access
     *
     * @return int
     */
    public function getAccess() {
        return $this->access;
    }

    /**
     * Set campus list
     *
     * @param $campusList
     */
    public function setCampusList($campusList) {
        $this->campusList = $campusList;
    }

    /**
     * Get campus list
     *
     * @return null
     */
    public function getCampusList() {
        return $this->campusList;
    }

    /**
     * Set end time
     *
     * @param $endTime
     */
    public function setEndTime($endTime) {
        $this->endTime = $endTime;
    }

    /**
     * Get end time
     *
     * @return Alaress_Date_Format
     */
    public function getEndTime() {
        return $this->endTime;
    }

    /**
     * Set start time
     *
     * @param $startTime
     */
    public function setStartTime($startTime) {
        $this->startTime = $startTime;
    }

    /**
     * Get start time
     *
     * @return Alaress_Date_Format
     */
    public function getStartTime() {
        return $this->startTime;
    }

    /**
     * Set sid
     *
     * @param $sid
     */
    public function setSid($sid) {
        $this->sid = $sid;
    }

    /**
     * Get sid
     *
     * @return int
     */
    public function getSid() {
        return $this->sid;
    }

    /**
     * Set category id
     *
     * @param $categoryId
     */
    public function setCategoryId($categoryId) {
        $this->categoryId = $categoryId;
    }

    /**
     * Get category id
     *
     * @return int
     */
    public function getCategoryId() {
        return $this->categoryId;
    }

    /**
     * Set category name
     *
     * @param $categoryName
     */
    public function setCategoryName($categoryName) {
        $this->categoryName = $categoryName;
    }

    /**
     * Get category name
     *
     * @return string
     */
    public function getCategoryName() {
        return $this->categoryName;
    }

	/**
	 * Set renderer class
	 *
	 * @param $rendererClass
	 */
	public function setRendererClass($rendererClass) {
		$this->rendererClass = $rendererClass;
	}

    /**
     * Get renderer class
     *
     * @return string
     */
    public function getRendererClass() {
        return $this->rendererClass;
    }

    /**
     * Set isEditable
     *
     * @param $isEditable
     */
    public function setIsEditable($isEditable) {
        $this->isEditable = $isEditable;
    }

    /**
     * Get isEditable
     *
     * @return bool
     */
    public function getIsEditable() {
        return $this->isEditable;
    }

	/**
	 * Set completed
	 *
	 * @param int $completed
	 */
	public function setCompleted($completed) {
		$this->completed = $completed;
	}

	/**
	 * Get completed
	 *
	 * @return int
	 */
	public function getCompleted() {
		return $this->completed;
	}

	/**
	 * Set connector id
	 *
	 * @param string $connectorId
	 */
	public function setConnectorId($connectorId) {
		$this->connectorId = $connectorId;
	}

	/**
	 * Get connector id
	 *
	 * @return string
	 */
	public function getConnectorId() {
		return $this->connectorId;
	}

	/**
	 * @param bool $allday
	 */
	public function setAllday($allday) {
		$this->allday = $allday;
	}

	/**
	 * @return bool
	 */
	public function getAllday() {
		return $this->allday;
	}
}
