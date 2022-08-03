<?php

/**
 * Implementation of our standard day event in Schoolbox.
 * Note this events can be campus, group or personal events.
 *
 * Other event systems also use psuedo versions of this event timetable.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\Day;

use Calendar\Event\Type\EventBase;
use Calendar\Event\Type\EventInterface;

class Event extends EventBase
{
    /**
     * These constants define the levels of access an day event can be
     */
    const CAMPUS_ACCESS   = 3;
    const GROUP_ACCESS    = 2;
    const PERSONAL_ACCESS = 1;

    /**
     * Event location
     *
     * @var string
     */
    protected $location = '';

    /**
     * Day events have levels based on the above constants
     * Campus/School = 3
     * Group = 2
     * Personal = 1
     *
     * @var int
     */
    protected $access = -1;

    /**
     * Category which the event is related to
     * @var int
     */
    protected $categoryId = -1;

    /**
     * Name of category this event is related to
     * @var string
     */
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
     * Whether or not this Task has been completed
     *
     * @var bool
     */
    protected $completed = false;

    /**
     * Wether this event is a task
     *
     * @var bool
     */
    protected $isTask = false;

    /**
     * Whether the event can be attended
     * Should be bool, but setting this to mixed to make phpstan happy
     * @var mixed
     */
    private $attendance = false;

    /**
     * URL path to further information or detail for event
     * @var string
     */
    protected $path;


    /**
     * @var int|null
     */
    protected $maxAttendees;


    /**
     * @var int|null
     */
    protected $numAttendees;


    /**
     * Whether the current user is invited to attend an event
     *
     * @var int|null
     */
    protected $attendanceStatus;


    /**
     * Can someone attend this event
     *
     * @return bool
     */
    public function canAttend()
    {
        return $this->getAttendance();
    }


    /**
     * Event visibility; generates a CSS string
     *
     * @return string
     */
    public function getFilterClasses()
    {
        $eventLevel = '';

        if ($this->isCampusAccess()) {
            if (stristr($this->getCampusList(), ',')) {
                foreach (explode(',', $this->getCampusList()) as $campus) {
                    $eventLevel .= ' campus' . $campus;
                }
            } else {
                $eventLevel .= ' campus' . $this->getCampusList();
            }
        } else {
            if ($this->isGroupAccess()) {
                $allLevels = array();

                foreach ($this->getCategoryCollection() as $currentCategory) {
                    $allLevels[] = 'group' . $currentCategory->getCategoryId();
                }

                $eventLevel = implode(' ', $allLevels);
            } else {
                $eventLevel = 'personal';
            }
        }

        return $eventLevel;
    }

    /* Getters/Setters
     */

    /**
     * Set attendance
     *
     * @param mixed $attendance
     */
    public function setAttendance($attendance)
    {
        $this->attendance = $attendance;
    }

    /**
     * Get attendance
     *
     * @return mixed
     */
    public function getAttendance()
    {
        return $this->attendance;
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

    //Getters and Setters

    /**
     * Set location
     *
     * @param string $location
     */
    public function setLocation($location)
    {
        $this->location = $location;
    }

    /**
     * Get location
     *
     * @return string|null
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * Set access
     *
     * @param int $access
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
     * Set campus list, comma separated
     *
     * @param string $campusList
     */
    public function setCampusList($campusList)
    {
        $this->campusList = $campusList;
    }

    /**
     * Get campus list, comma separated
     *
     * @return string
     */
    public function getCampusList()
    {
        return $this->campusList;
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

    /**
     * Set category name
     *
     * @param string $categoryName
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
     * Set completed
     *
     * @param bool $completed
     */
    public function setCompleted(bool $completed)
    {
        $this->completed = $completed;
    }

    /**
     * Get completed
     *
     * @return bool
     */
    public function getCompleted(): bool
    {
        return $this->completed;
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

    /**
     * @param boolean $isTask
     */
    public function setIsTask($isTask)
    {
        $this->isTask = $isTask;
    }

    /**
     * @return boolean
     */
    public function isTask()
    {
        return $this->isTask;
    }

    /**
     * @return string
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getMaxAttendees(): ?int
    {
        return $this->maxAttendees;
    }

    /**
     * @param int|null $maxAttendees
     * @return $this
     */
    public function setMaxAttendees(?int $maxAttendees): EventInterface
    {
        $this->maxAttendees = $maxAttendees;
        return $this;
    }


    /**
     * @return string[]
     */
    protected function getRenderClasses(): array
    {
        return [
            self::RENDER_FORMAT_JSON => Renderer\Javascript::class,
            self::RENDER_FORMAT_VCAL => Renderer\VObject::class,
            self::RENDER_FORMAT_CSV  => Renderer\Csv::class,
        ];
    }
}
