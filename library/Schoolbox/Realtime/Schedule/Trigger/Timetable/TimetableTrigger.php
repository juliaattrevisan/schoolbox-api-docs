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
    const START_WARNING_WINDOW = 300; // 5 minutes
    const TARGET_WARN = 'warn';
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

        // Before the day starts

        if (strtotime($definition['start'][0], $timestamp) > $timestamp) {
            return $this->beforeSchool($definition, $timestamp);
        }

        // Give a separate warning message 5 minutes before the start next period

        for ($i = 1; $i < $len; ++$i) {
            $start = strtotime($definition['start'][$i], $timestamp);

            if ($x = $this->testPeriod($start, $end, $timestamp)) {
                $warning_time = $x;
                break;
            }
        }

        // Hunt down the next start or end time, accounting for being *between* classes

        for ($i = 1; $i < $len; ++$i) {
            $start = strtotime($definition['start'][$i], $timestamp);
            $end   = strtotime($definition['end'][$i], $timestamp);

            if ($x = $this->testPeriod($start, $end, $timestamp)) {
                $start_end_time = $x;
                break;
            }
        }

        // Work out of we should return the the period start/end time or the 5 minute warning time (whichever is earlier)
        // This could probably be truncated somehow

        if (isset($start_end_time ) && isset($warning_time)) {
            if ($warning_time < $start_end_time ) {
                return $warning_time;
            } else {
                return $start_end_time;
            }
        } else if (isset($start_end_time )) {
            return $start_end_time ;
        } else if (isset($warning_time)) {
            return $warning_time;
        }

        // You're off the edge of the map here, matey. Here there be monsters. Are they big scary monsters, or more
        // Monsters Inc. style monsters?

        if (!$lookAhead) {
            return PHP_INT_MAX;
        }

        // Try again with the new time, but do not attempt to look ahead next time

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

            $dayNumber         = $this->handler->getDayNumber($this->campus, $timestamp);
            $this->cache[$key] = $this->handler->getTimetableDefinition(null, $this->campus, $dayNumber);
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