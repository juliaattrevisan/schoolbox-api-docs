<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\TimetableCalendar;

use SimpleXMLElement;

/**
 * Converts a repeating pattern cyclic grid into a datetime-based schedule
 */
class CycleUnroller
{
    /**
     * Convert a cyclic grid of xml nodes into a schedule equivalent
     *
     * @param SimpleXMLElement $nodes
     * @return array
     */
    public function unroll(SimpleXmlElement $nodes): array
    {
        $stack = [];

        /* Find all the TimetableAllocation nodes that have an ISOYear
         * that matches the current calendar year
         */

        /* Among each of those nodes, we only need to consider the nodes
         * that have an ISOWeek value between PAST_THRESHOLD <= now <= FUTURE_THRESHOLD
         * All other data is too far in the past or too far in the future to care about
         */

        /* For each of those nodes, find the WeekId that corresponds to that
         * particular week so we can start populating its Days and Periods
         * TimetableAllocation WeekId corresponds to a Structure node <Week Id="...">
         * which contains Days and Periods
         */

        /* For each of the valid WeekId nodes that we found, calculate the datetime
         * that would correspond to each of the Periods in each of the Days contained
         * inside this WeekId node.
         *
         * There's an Ordinal field, which is essentially a sequencing int
         * We can use that to calculate an offset from the start of the week which we
         * can for now presume to be Monday. Localisation will mean that in some regions
         * it won't be Monday, but some other day, and we'll have to accommodate that.
         */

        /* For each of the Days and Periods, assign their corresponding startdatetime and
         * finishdatetime values
         */

        return $stack;
    }
}
