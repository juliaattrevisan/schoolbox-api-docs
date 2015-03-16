<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule\Trigger\Timetable;


use Alaress\Date\Clock;
use Alaress\Date\IClock;
use Alaress\Scheduler\Trigger\AbstractTrigger;
use External_Timetable;
use Schoolbox\Campus;
use Schoolbox\Realtime\Schedule\Event;
use Schoolbox\Realtime\Timetable\TimetableNotifier;
use Schoolbox\Realtime\WorkNotifier;

abstract class TimetableTrigger extends AbstractTrigger
{
//    const START_WARNING_WINDOW = 300; // 5 minutes
    const START_WARNING_WINDOW = 0;
    const TARGET_START = 'start';
    const TARGET_END   = 'end';

    /** @var Campus */
    protected $campus;

    /** @var array */
    protected $cache;

    /** @var WorkNotifier */
    protected $notifier;

    /**
     * @param Campus $campus the object to be acted on
     * @param string $target the mode in which to run (start or end)
     * @param IClock $clock
     */
    public function __construct($campus, $target, $clock = null)
    {
        $this->cache    = array();
        $this->campus   = $campus;
        $this->label    = $campus->getId() . ".$target";
        $this->event    = new Event(null, $target, $campus, null);
        $this->notifier = new TimetableNotifier();
        $this->handler  = new External_Timetable();
        $this->clock    = $clock ?: new Clock();
    }

    /**
     * Return the timestamp of the next timetable event in this trigger's campus
     *
     * @return int
     */
    public function getTimestamp()
    {
        return $this->getTimetable($this->clock->time());
    }


    /**
     * @param int  $timestamp
     * @param bool $lookAhead
     * @return int
     */
    protected function getTimetable($timestamp, $lookAhead = true)
    {
        $definition = $this->getTimetableDefinition($timestamp);
        $len  = count($definition['number']);

        // before the day starts
        if (strtotime($definition['start'][0], $timestamp) > $timestamp) {
            return $this->beforeSchool($definition, $timestamp);
        }

        // hunt down the next start or end time, accounting for being *between* classes
        for ($i = 1; $i < $len; ++$i) {
            $start = strtotime($definition['start'][$i], $timestamp);
            $end   = strtotime($definition['end'][$i], $timestamp);

            if ($x = $this->testPeriod($start, $end, $timestamp)) {
                return $x;
            }

            // we're off the end of this period? NEEEXT!
        }

        // You're off the edge of the map here, matey. Here there be monsters.
        if (!$lookAhead) {
            return PHP_INT_MAX;
        }

        // try again with the new time, but do not attempt to look ahead next time
        return $this->getTimetable($this->nextValidTimestamp($timestamp), false);

    }


    /**
     * @param array $definition
     * @param int $timestamp
     * @return int
     */
    protected abstract function beforeSchool($definition, $timestamp);


    /**
     * @param int $start
     * @param int $end
     * @param int $timestamp
     * @return int
     */
    protected abstract function testPeriod($start, $end, $timestamp);

    /**
     * Since this will be called n times a day, it's far more efficient to cache it
     *
     * @param int $timestamp
     * @return array
     */
    protected function getTimetableDefinition($timestamp)
    {
        $key = date('Y.m.d', $timestamp);

        if (empty($this->cache[$key])) {

            // only allow a max of 3 different days in cache
            if (count($this->cache) > 2) {
                krsort($this->cache);
                array_pop($this->cache);
            }

            $campusId          = $this->campus->getId();
            $dayNumber         = $this->handler->getDayNumber($campusId, $timestamp);
            $this->cache[$key] = $this->handler->getTimetableDefinition(null, $campusId, $dayNumber);
        }

        return $this->cache[$key];
    }


    /**
     * @param int  $timestamp
     * @return int
     */
    protected function nextValidTimestamp($timestamp)
    {
        // we got here? that means we're off the end of the timetable,
        // so we want to schedule the first period of the next timetabled day

        // set our timestamp to 1am~ish on the next weekday from $now
        // we can't just go "tomorrow", because if the ExternalTimetable sees
        // a non-school day, it will return the generic 'day 0' timetable,
        // which we don't want
        $timestamp -= (date('H', $timestamp) - 1) * 3600;

        do {
            // increment in days until we're off the weekend
            $timestamp += 86400;
        } while (date('N', $timestamp) > 5);

        // return new timestamp
        return $timestamp;
    }


}