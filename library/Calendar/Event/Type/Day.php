<?php
/**
 * Calendar_Event_Type_Day
 *
 * Uses the Visitor pattern to render via an external class, finally decoupling
 * the data structure from rendering methodology in a highly granular environment
 * without needing to use singletons.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Event_Type_Day extends Calendar_Event_Type_Abstract {

    /* Renderer
     */
    protected $rendererClass = 'Calendar_Event_Type_Day_Renderer_Javascript';

    /* Attendance flag
     */
    private $attendance = 0;

	/**
	 * Render via external class
	 *
	 * @param \Calendar_Event_StudentBirthday_Renderer $visitor
	 *
	 * @return mixed
	 */
    public function accept($visitor) {
        return $visitor->visit($this);
    }

    /**
     * Can someone attend this event
     *
     * @return bool
     */
    public function canAttend() {
        if (is_numeric($this->getAttendance()) && ($this->getAttendance() > 0)) {
            return true;
        }

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
        } else {
            if ($this->isGroupAccess()) {
                $eventLevel = 'group' . $this->getSid();
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
        if ($this->getStartTime()->getDatabaseDate() != $this->getEndTime()->getDatabaseDate()) {
            return '';
        }
        $startFormat = $this->getStartTime()->getUsingFormat('g:iA');
        $endFormat = $this->getEndTime()->getUsingFormat('g:iA');

        $formattedTime = rtrim(
            ($startFormat != "12:00AM" ? $startFormat : "") .
                ($endFormat != "12:00AM" ? " &ndash; " . $endFormat : "")
        );

        return $formattedTime;
    }


    public function getFullDate() {

        $formattedTime = $this->getTime();
        $startDate = $this->getStartTime()->getUsingFormat('l, jS F');
        if ($formattedTime) {
            $formattedTime = $startDate . ', ' . $formattedTime;
        } else {
            $formattedTime = $startDate;
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
}
