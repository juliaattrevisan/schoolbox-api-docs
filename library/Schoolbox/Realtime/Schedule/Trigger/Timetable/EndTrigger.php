<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Trigger\Timetable;

use Alaress\Date\IClock;
use Alaress_Date_Format as ADF;
use OutOfBoundsException;
use Schoolbox\Campus;

class EndTrigger extends TimetableTrigger
{

    /**
     * @param Campus $campus
     * @param IClock $clock
     */
    public function __construct($campus, $clock = null)
    {
        parent::__construct($campus, self::TARGET_END, $clock);
    }


    /**
     * Return an array of callables that are appropriate for this trigger
     *
     * @return callable[]
     */
    public function getCallbacks()
    {
        return array(array($this->notifier, 'processEnd'));
    }


    /**
     * @param array $definition
     * @param ADF   $now
     * @throws OutOfBoundsException
     * @return ADF
     */
    protected function beforeSchool(array $definition, ADF $now)
    {
        if ($this->extractPeriodStart($definition, 0, $now)->gt($now)) {
            return $this->extractPeriodEnd($definition, 0, $now);
        }

        throw new OutOfBoundsException("Not before school");
    }


    /**
     * @param ADF $start
     * @param ADF $end
     * @param ADF $now
     * @throws OutOfBoundsException if this period is not relevant
     * @return ADF
     */
    protected function testPeriod(ADF $start, ADF $end, ADF $now)
    {
        // if we're inside this period, return its end
        if ($now->lt($end) && $now->gt($start)) {
            return $end;
        }

        throw new OutOfBoundsException("Date not relevant");
    }
}
