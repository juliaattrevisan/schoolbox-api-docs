<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Trigger\Timetable;

use _PHPStan_e04cc8dfb\Nette\InvalidArgumentException;
use Alaress\Date\Clock;
use Alaress\Date\IClock;
use Alaress\Scheduler\Event\EventInterface;
use Alaress_Date_Format as ADF;
use Alaress\Scheduler\Trigger\AbstractTrigger;
use BadMethodCallException;
use External_Timetable;
use External_TimetableInterface;
use OutOfBoundsException;
use Schoolbox\Campus;
use Schoolbox\Realtime\Schedule\Event\Event;
use Schoolbox\Realtime\Timetable\TimetableNotifier;
use Schoolbox\Realtime\WorkNotifier;

abstract class TimetableTrigger extends AbstractTrigger
{
    const START_WARNING_WINDOW = 300; // 5 minutes
    const TARGET_WARN  = 'warn';
    const TARGET_START = 'start';
    const TARGET_END   = 'end';

    /** @var Campus */
    protected $campus;

    /** @var array */
    protected $cache;

    /** @var WorkNotifier */
    protected $notifier;

    /** @var External_TimetableInterface */
    protected $handler;

    /** @var IClock */
    protected $clock;

    /**
     * @param Campus $campus the object to be acted on
     * @param string $target the mode in which to run (start or end)
     * @param IClock $clock
     */
    public function __construct($campus, $target, $clock = null)
    {
        $this->setCache([]);
        $this->setCampus($campus);
        $this->setLabel($campus->getId() . ".$target");
        $this->setEvent(new Event(null, $target, $campus, null));
        $this->setNotifier(new TimetableNotifier());
        $this->setHandler(new External_Timetable());
        $this->setClock($clock ?: new Clock()); // 1sec/sec from epoch if nothing specified
    }

    /**
     * Return the timestamp of the next timetable event in this trigger's campus
     *
     * @return int
     */
    public function getTimestamp()
    {
        return $this->getTimetable(ADF::now($this->clock));
    }


    /**
     * @param ADF  $now
     * @param bool $lookAhead
     * @return int
     */
    protected function getTimetable(ADF $now, $lookAhead = true)
    {
        // retrieve the timetable definition relevant to our current time
        $definition = $this->getTimetableDefinition($now);

        // if there is no timetable definition available, you're off the edge of the map.
        if (!$definition) {
            return PHP_INT_MAX;
        }

        try {
            return $this->beforeSchool($definition, $now)->getUnixTimeStamp();
        } catch (OutOfBoundsException $e) {
            // noop... carry on, as you were
        }

        // find a relevant time
        $relevantTime = $this->getRelevantTime($definition, $now);

        if (!$relevantTime->isNull()) {
            // if it's not null, return it
            return $relevantTime->getUnixTimeStamp();
        }

        // You're off the edge of the map here, matey. Here there be monsters. Are they big scary monsters, or more
        // Monsters Inc. style monsters? They're big scary monsters, to be sure.

        if (!$lookAhead) {
            return PHP_INT_MAX;
        }

        // Try again with the new time, but do not attempt to look ahead next time
        return $this->getTimetable($this->nextValidTimestamp($now), false);
    }


    /**
     * Give a separate warning message 5 minutes before the start next period
     *
     * @param array $definition
     * @param ADF   $now
     * @return ADF
     */
    protected function getWarningTime(array $definition, $now)
    {
        $len  = count($definition['number']);

        for ($i = 1; $i < $len; ++$i) {
            try {
                $start = $this->extractPeriodStart($definition, $i, $now);
            } catch (\InvalidArgumentException $e) {
                /* Unknown label/periodIndex combo
                 */
                continue;
            }

            try {
                return $this->testPeriod($start, new \Alaress_Date_NullDate(), $now);
            } catch (BadMethodCallException $e) {
                continue;
            } catch (OutOfBoundsException $e) {
                continue;
            }
        }

        return new \Alaress_Date_NullDate();
    }


    /**
     * Hunt down the next start or end time, accounting for being *between* classes
     *
     * @param array $definition
     * @param ADF    $now
     * @return ADF
     */
    protected function getStartEndTime(array $definition, $now)
    {
        $len  = count($definition['number']);

        for ($i = 1; $i < $len; ++$i) {
            try {
                $start = $this->extractPeriodStart($definition, $i, $now);
                $end   = $this->extractPeriodEnd($definition, $i, $now);
            } catch (\InvalidArgumentException $e) {
                /* Unknown label/periodIndex combo
                 */
                continue;
            }

            try {
                return $this->testPeriod($start, $end, $now);
            } catch (OutOfBoundsException $e) {
                continue;
            }
        }

        return new \Alaress_Date_NullDate();
    }


    /**
     * Work out of we should return the the period start/end time or the 5 minute warning time (whichever is earlier)
     *
     * @param array $definition
     * @param ADF   $now
     * @return ADF
     */
    protected function getRelevantTime(array $definition, ADF $now)
    {
        $warningTime  = $this->getWarningTime($definition, $now);
        $startEndTime = $this->getStartEndTime($definition, $now);

        // if they're both null, just return null
        if ($warningTime->isNull() && $startEndTime->isNull()) {
            return $warningTime;
        }

        // otherwise, if just warning is null, return startEnd
        if ($warningTime->isNull()) {
            return $startEndTime;
        }

        // conversely, if just startEnd is null, return warning
        if ($startEndTime->isNull()) {
            return $warningTime;
        }

        // neither is null: return the earlier of the two
        return $warningTime->lt($startEndTime) ? $warningTime : $startEndTime;
    }


    /**
     * @param array $definition
     * @param int   $periodIndex
     * @param ADF   $now
     * @return ADF
     * @throws \InvalidArgumentException
     */
    protected function extractPeriodStart(array $definition, $periodIndex, ADF $now)
    {
        return $this->extractPeriodTime($definition, $periodIndex, 'start', $now);
    }


    /**
     * @param array $definition
     * @param int   $periodIndex
     * @param ADF   $now
     * @return ADF
     * @throws \InvalidArgumentException
     */
    protected function extractPeriodEnd(array $definition, $periodIndex, ADF $now)
    {
        return $this->extractPeriodTime($definition, $periodIndex, 'end', $now);
    }


    /**
     * @param array  $definition
     * @param int    $periodIndex
     * @param string $label
     * @param ADF    $now
     * @return ADF
     * @throws \InvalidArgumentException
     */
    protected function extractPeriodTime(array $definition, $periodIndex, $label, ADF $now)
    {
        if (!isset($definition[$label][$periodIndex])) {
            throw new \InvalidArgumentException(sprintf(
                'Timetable definition keys ($label %s, $periodIndex %s) does not exist in $definition struct',
                $label,
                $periodIndex
            ));
        }

        return new ADF(strtotime($definition[$label][$periodIndex], $now->getUnixTimeStamp()), 'U');
    }


    /**
     * @param array $definition
     * @param ADF   $now
     * @return ADF
     */
    abstract protected function beforeSchool(array $definition, ADF $now);


    /**
     * @param ADF $start
     * @param ADF $end
     * @param ADF $now
     * @throws OutOfBoundsException if this period is not relevant
     * @return ADF
     */
    abstract protected function testPeriod(ADF $start, ADF $end, ADF $now);


    /**
     * Since this will be called n times a day, it's far more efficient to cache it
     *
     * @param ADF $now
     * @return array which is a timetable definition for the day associated with the given ADF
     */
    protected function getTimetableDefinition(ADF $now)
    {
        $key = $now->getUsingFormat('Y.m.d');

        if (empty($this->cache[$key])) {
            // only allow a max of 3 different days in cache
            if (count($this->cache) > 2) {
                krsort($this->cache);
                array_pop($this->cache);
            }

            $dayNumber         = $this->handler->getDayNumber($this->campus, $now->getUnixTimeStamp());
            $this->cache[$key] = $this->handler->getTimetableDefinition(null, $this->campus, $dayNumber);
        }

        return $this->cache[$key];
    }


    /**
     * Return an ADF that represents ~1am on the next available weekday
     *
     * @param ADF $now
     * @return ADF
     */
    protected function nextValidTimestamp(ADF $now)
    {
        // because dates are not immutable, clone it before we play with it
        $now = clone($now);

        // we got here? that means we're off the end of the timetable,
        // so we want to schedule the first period of the next timetabled day

        // set our timestamp to 1am~ish on the next weekday from $now
        // we can't just go "tomorrow", because if the ExternalTimetable sees
        // a non-school day, it will return the generic 'day 0' timetable,
        // which we don't want
        $now->setHour(1);

        do {
            // increment in days until we're off the weekend
            $now->addDays(1);
        } while ($now->isWeekend());

        // return new timestamp
        return $now;
    }


    /**
     * @param Campus $campus
     * @return TimetableTrigger
     */
    public function setCampus(Campus $campus)
    {
        $this->campus = $campus;
        return $this;
    }


    /**
     * @param array $cache
     * @return static
     */
    public function setCache(array $cache)
    {
        $this->cache = $cache;
        return $this;
    }


    /**
     * @param IClock $clock
     * @return $this
     */
    protected function setClock(IClock $clock)
    {
        $this->clock = $clock;
        return $this;
    }


    /**
     * @param External_TimetableInterface $handler
     * @return $this
     */
    protected function setHandler(External_TimetableInterface $handler)
    {
        $this->handler = $handler;
        return $this;
    }


    /**
     * @param WorkNotifier $notifier
     * @return $this
     */
    protected function setNotifier(WorkNotifier $notifier)
    {
        $this->notifier = $notifier;
        return $this;
    }


    /**
     * @param EventInterface $event
     * @return $this
     */
    protected function setEvent(EventInterface $event)
    {
        $this->event = $event;
        return $this;
    }


    /**
     * @param string $label
     * @return $this
     */
    protected function setLabel($label)
    {
        $this->label = $label;
        return $this;
    }
}
