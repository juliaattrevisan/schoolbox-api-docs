<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\TimetableCalendar;

use DebugBar\DebugBarException;
use SimpleXMLElement;

/**
 * Converts a repeating pattern cyclic grid into a datetime-based schedule
 */
class CycleUnroller
{
    /**
     * We care about stuff that happened this many weeks before today
     */
    private const PAST_THRESHOLD = 1;


    /**
     * We care about stuff that happens this many weeks after today
     */
    private const FUTURE_THRESHOLD = 2;


    /**
     * Number of weeks in a year
     * Canonically, 52
     */
    private const WEEKS_IN_YEAR = 52;


    /**
     * Convert a cyclic grid of xml nodes into a schedule equivalent
     *
     * @param SimpleXMLElement $nodes
     * @return array
     * @throws DebugBarException
     */
    public function unroll(SimpleXmlElement $nodes): array
    {
        /* The current year. We won't care about other calendar years.
         */
        $year = date('Y');

        $stack = [];

        /* Find all the TimetableAllocation nodes that have an ISOYear
         * that matches the current calendar year
         *
         * Among each of those nodes, we only need to consider the nodes
         * that have an ISOWeek value between PAST_THRESHOLD <= now <= FUTURE_THRESHOLD
         * All other data is too far in the past or too far in the future to care about
         *
         * Intentionally avoid looking beyond the boundaries of the current year for now
         * 1 <= $currentWeek <= 52
         *
         * TODO You could expand upon this so that you can look at the adjacent years
         *  but according to Jez, there's an unlikely need for that...we can adjust
         *  the boundary calculations later
         */
        $currentWeek = (int)\Alaress_Date_Format::now()->getUsingFormat('W');

        $pastThreshold = max($currentWeek - self::PAST_THRESHOLD, 1);
        $futureThreshold = min($currentWeek + self::FUTURE_THRESHOLD, self::WEEKS_IN_YEAR);

        $timetableAllocations = $nodes->xpath("/iSAMS/TimetableManager/TimetableAllocations/TimetableAllocation[ISOYear = {$year} and Week >= {$pastThreshold} and Week <= {$futureThreshold}]");

        /* For each of those nodes, find the WeekId that corresponds to that
         * particular week so we can start populating its Days and Periods
         * TimetableAllocation WeekId corresponds to a Structure node <Week Id="...">
         * which contains Days and Periods
         */
        foreach ($timetableAllocations as $timetableAllocation) {
            $weekId = (string)$timetableAllocation->TimetableWeekId;

            $stack[(string)$timetableAllocation->Week] = [
                'weekId' => $weekId,
                'days' => []
            ];

            /* For each of the valid WeekId nodes that we found, calculate the datetime
             * that would correspond to each of the Periods in each of the Days contained
             * inside this WeekId node.
             *
             * There's an Ordinal field, which is essentially a sequencing int
             * We can use that to calculate an offset from the start of the week which we
             * can for now presume to be Monday. Localisation will mean that in some regions
             * it won't be Monday, but some other day, and we'll have to accommodate that.
             */
        }

        new \Alaress_Probe($timetableAllocations, $stack);


        /* For each of the Days and Periods, assign their corresponding startdatetime and
         * finishdatetime values
         */

        return $stack;
    }
}
