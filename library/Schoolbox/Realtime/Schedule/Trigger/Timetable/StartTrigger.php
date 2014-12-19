<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule\Trigger\Timetable;


use Alaress\Date\IClock;
use Schoolbox\Campus;

class StartTrigger extends TimetableTrigger {

    /**
     * @param Campus $campus
     * @param IClock $clock
     */
    public function __construct($campus, $clock = null)
    {
        parent::__construct($campus, self::TARGET_START, $clock);
    }


    /**
     * Return an array of callables that are appropriate for this trigger
     *
     * @return callable[]
     */
    public function getCallbacks()
    {
        return array(array($this->notifier, 'processStart'));
    }


    /**
     * @param array $definition
     * @param int   $timestamp
     * @return int
     */
    protected function beforeSchool($definition, $timestamp)
    {
        return strtotime($definition['start'][0], $timestamp) - self::START_WARNING_WINDOW;
    }


    /**
     * @param int $start
     * @param int $end
     * @param int $timestamp
     * @return int
     */
    protected function testPeriod($start, $end, $timestamp)
    {
        // if we're not yet inside this period, return its start time
        if ($timestamp <= $start - self::START_WARNING_WINDOW) {
            return $start - self::START_WARNING_WINDOW;
        }

        return 0;
    }

}