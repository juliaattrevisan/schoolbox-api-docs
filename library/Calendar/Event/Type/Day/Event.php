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
     * Wether the event can be attended
     * @var bool
     */
    private $attendance = false;

    /**
     * URL path to further information or detail for event
     * @var string
     */
    protected $path;


    /**
     * Can someone attend this event
     *
     * @return bool
     */
    public function canAttend() {
        return $this->getAttendance();
    }


    /**
     * Event visibility; generates a CSS string
     *
     * @return string
     */
    public function getFilterClasses() {
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
     * @param bool $attendance
     */
    public function setAttendance($attendance) {
        $this->attendance = $attendance;
    }

    /**
     * Get attendance
     *
     * @return bool
     */
    public function getAttendance() {
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

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }


    /**
     * @return string[]
     */
    protected function getRenderClasses(): array
    {
        return [
            self::RENDER_FORMAT_JSON => Renderer\Javascript::class,
            self::RENDER_FORMAT_HTML => Renderer\Html::class,
            self::RENDER_FORMAT_VCAL => Renderer\VObject::class,
            self::RENDER_FORMAT_CSV  => Renderer\Csv::class,
        ];
    }
}
