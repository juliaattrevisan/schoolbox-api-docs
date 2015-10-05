<?php
/**
 * Implementation of our standard day event in Schoolbox.
 * Note this events can be campus, group or personal events.
 *
 * Other event systems also use psuedo versions of this event timetable.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Calendar\Event\Type\Day;

use Calendar\Event\Type\EventBase;
use Calendar\Event\Type\EventInterface;


class Event extends EventBase implements EventInterface {

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
     * Renderer classes
     */
    protected $renderClasses = array(   'javascript'    => Renderer\Javascript::class,
                                        'html'          => Renderer\Html::class,
                                        'vcal'          => Renderer\VObject::class,
                                        'csv'           => Renderer\Csv::class,
                                    );

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

    /**
     * Some cosmetic derivation. If the start and end time are both 12:00AM,
     * this will return an empty string. Oddly I wonder if it's possible that
     * the start time is 12:00AM and the end time is something different.
     *
     * @return string
     */
    public function getTime() {
        //
        // All day event, or does it start and end on a different day?
        // Show a blank string.
        if ($this->getAllday()) {
            return '';
        }
        if ($this->getStartTime()->getDateOnly() != $this->getEndTime()->getDateOnly()) {
            return '';
        }

        $formattedTime = '';

        if(!$this->getStartTime()->isAtStartOfDay()) {
            $formattedTime = $this->getStartTime()->getTimeOnly();

            if(!$this->getEndTime()->isAtStartOfDay()) {
                $formattedTime .= "&ndash;". $this->getEndTime()->getTimeOnly();
            }
        }

        return $formattedTime;
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


}
