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

class WarnTrigger extends TimetableTrigger
{

    /**
     * @param Campus $campus
     * @param IClock $clock
     */
    public function __construct($campus, $clock = null)
    {
        parent::__construct($campus, self::TARGET_WARN, $clock);
    }


    /**
     * @param ADF $adf
     * @return ADF
     */
    protected function applyWindow(ADF $adf)
    {
        return $adf->addSeconds(-1 * self::START_WARNING_WINDOW);
    }


    /**
     * Return an array of callables that are appropriate for this trigger
     *
     * @return callable[]
     */
    public function getCallbacks()
    {
        return array(array($this->notifier, 'processWarn'));
    }


    /**
     * @param array $definition
     * @param ADF   $now
     * @throws OutOfBoundsException
     * @return ADF
     */
    protected function beforeSchool(array $definition, ADF $now)
    {
        $startMinusWindow = $this->applyWindow($this->extractPeriodStart($definition, 0, $now));

        // Before the day starts
        if ($startMinusWindow->gt($now)) {
            return $this->applyWindow($this->extractPeriodStart($definition, 0, $now));
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
        // if we're not yet inside this period, return its start time minus the warning window
        $warn = $this->applyWindow(clone $start);

        if ($now->lte($warn)) {
            return $warn;
        }

        throw new OutOfBoundsException("Date not relevant");
    }
}
