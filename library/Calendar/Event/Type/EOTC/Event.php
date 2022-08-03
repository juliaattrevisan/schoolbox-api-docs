<?php

/**
 * Standard EOTC event for display in the calendar
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\EOTC;

use Calendar\Event\Type\Day;

class Event extends Day\Event
{
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
     * Can someone attend this event
     *
     * @return bool
     */
    public function canAttend()
    {
        return false;
    }

    /**
     * Event visibility; generates a CSS string
     *
     * @return string
     */
    public function getDerivedEventLevel()
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
            /* EOTC events are always campus, and aren't assigned to groups
             */
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
    public function getTime()
    {
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

        if (!$this->getStartTime()->isAtStartOfDay()) {
            $formattedTime = $this->getStartTime()->getTimeOnly();

            if (!$this->getEndTime()->isAtStartOfDay()) {
                $formattedTime .= "&ndash;" . $this->getEndTime()->getTimeOnly();
            }
        }


        return $formattedTime;
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
