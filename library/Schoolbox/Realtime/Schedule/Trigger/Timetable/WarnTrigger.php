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
use Alaress_Date_Format as ADF;
use OutOfBoundsException;
use Schoolbox\Campus;

class WarnTrigger extends TimetableTrigger {

    /**
     * @param Campus $campus
     * @param IClock $clock
     */
    public function __construct($campus, $clock = null)
    {
        parent::__construct($campus, self::TARGET_WARN, $clock);
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
     * @return ADF
     */
    protected function beforeSchool(array $definition, ADF $now)
    {
        return $this->extractPeriodStart($definition, 0, $now)->addSeconds(-1 * self::START_WARNING_WINDOW);
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
        $warn = clone $start;
        $warn->addSeconds(-1 * self::START_WARNING_WINDOW);

        if ($now->lte($warn)) {
            return $warn;
        }

        throw new OutOfBoundsException("Date not relevant");
    }

}
