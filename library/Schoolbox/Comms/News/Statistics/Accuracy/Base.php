<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Statistics\Accuracy;

abstract class Base implements IAccuracy
{

    /**
     * Generate a range of datetime values, starting at midnight of $start,
     * ending at midnight of $end, at intervals (a value in minutes)
     *
     * @param \Alaress_Date_Format $start
     * @param \Alaress_Date_Format $end
     * @return array
     */
    public function generateDateRange(\Alaress_Date_Format $start, \Alaress_Date_Format $end): array
    {
        $start->startOfDay();
        $endTimestamp = $end->getUnixTimeStamp();

        $range = [];

        /* Multiply by 1000 to get milliseconds conversion, because highcharts
         *
         * We don't need to placehold the last element in the range, which is the most recent interval
         * It should only show when there's data there, rather than assumed to be zero, because its
         * state is currently volatile and inconclusive unlike its historical brethren.
         */
        while ($start->getUnixTimeStamp() < ($endTimestamp-(60*$this->getInterval()))) {
            $range[$start->getDatabase()] = [1000*$start->getUnixTimeStamp(), 0];

            $start->addMinutes($this->getInterval());
        }

        return $range;
    }
}