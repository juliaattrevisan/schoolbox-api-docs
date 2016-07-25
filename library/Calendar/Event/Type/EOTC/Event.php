<?php
/**
 * Standard EOTC event for display in the calendar
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Calendar\Event\Type\EOTC;

use Calendar\Event\Type\Day;
use Calendar\Event\Type\EventInterface;
use EOTC\EventMetadata;
use Schoolbox\Calendar\EventType;
use Schoolbox\Collection;
use Schoolbox\User;


class Event extends Day\Event implements EventInterface {

    /**
     * Renderer classes
     * @var array
     */
    protected $renderClasses = array(   'javascript'    => Renderer\Javascript::class,
                                        'html'          => Renderer\Html::class,
                                        'vcal'          => Renderer\VObject::class,
                                        'csv'           => Renderer\Csv::class,
    );

    /* Attendance flag
     */
    private $attendance = 0;

    /**
     * Some custom thing
     *
     * @var array
     */
    protected $custom = [];

    /**
     * @var User
     */
    protected $author;

    /**
     * @var User
     */
    protected $teacherInCharge;

    /**
     * @var EventType
     */
    protected $type;

    /**
     * @var Collection
     */
    protected $campusCollection;

    /**
     * @var EventMetadata
     */
    protected $metadata;

    /**
     * Can someone attend this event
     *
     * @return bool
     */
    public function canAttend() {
		return false;
    }

    /**
     * Event visibility; generates a CSS string
     *
     * @return string
     */
    public function getDerivedEventLevel() {
        $eventLevel = '';

        if ($this->isCampusAccess()) {
            if (stristr($this->getCampusList(), ',')) {
                foreach (explode(',', $this->getCampusList()) as $campus) {
                    $eventLevel .= ' campus' . $campus;
                }
            } else {
                $eventLevel .= ' campus' . $this->getCampusList();
            }
        }
		/* EOTC events are always campus, and aren't assigned to groups
		 */
		else {
			$eventLevel = 'personal';
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
        if ($this->getStartTime()->ne($this->getEndTime())) {
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
     * @param $attendance
     */
    public function setAttendance($attendance) {
        $this->attendance = $attendance;
    }

    /**
     * Get attendance
     *
     * @return string
     */
    public function getAttendance() {
        return $this->attendance;
    }

    /**
     * @return array
     */
    public function getCustom()
    {
        return $this->custom;
    }

    /**
     * @param  array $custom
     *
     * @return $this
     */
    public function setCustom($custom)
    {
        $this->custom = $custom;
        return $this;
    }

    /**
     * @return User
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * @param User $author
     */
    public function setAuthor($author)
    {
        $this->author = $author;
    }

    /**
     * @return User
     */
    public function getTeacherInCharge()
    {
        return $this->teacherInCharge;
    }

    /**
     * @param User $teacherInCharge
     */
    public function setTeacherInCharge($teacherInCharge)
    {
        $this->teacherInCharge = $teacherInCharge;
    }

    /**
     * @return EventType
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param EventType $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return Collection
     */
    public function getCampusCollection()
    {
        return $this->campusCollection;
    }

    /**
     * @param Collection $campusCollection
     */
    public function setCampusCollection($campusCollection)
    {
        $this->campusCollection = $campusCollection;
    }

    /**
     * @return EventMetadata
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @param EventMetadata $metadata
     */
    public function setMetadata($metadata)
    {
        $this->metadata = $metadata;
    }


}

