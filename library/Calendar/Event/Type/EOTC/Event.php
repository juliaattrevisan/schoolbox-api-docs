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


class Event extends Day\Event implements EventInterface {

    /**
     * Renderer classes
     * @var array
     */
    protected $renderClasses = array(   'javascript'    => Renderer\Javascript::class,
                                        'html'          => Renderer\Html::class,
                                        'vcal'          => Renderer\VObject::class,
                                        'csv'          => Renderer\Csv::class,
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
	 * Render via external class
	 *
	 * @param \Calendar_Event_Type_EOTC_Renderer_Javascript $visitor
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
}
