<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Statistics\Scale;

abstract class Base implements IScale
{

    /**
     * Generates a one-node range containing only today's date
     *
     * @return array
     */
    protected function generateMinimumRange(): array
    {
        $range = [];

        $now = \Alaress_Date_Format::now();

        $range[$now->getDatabase()] = [1000*$now->getUnixTimeStamp(), 0, null];

        return $range;
    }


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

        if (!$start->getUnixTimeStamp() || !$end->getUnixTimeStamp()) {
            return $this->generateMinimumRange();
        }

        $endTimestamp = $end->getUnixTimeStamp();

        $range = [];

        /* Multiply by 1000 to get milliseconds conversion, because highcharts
         */
        while ($start->getUnixTimeStamp() <= $endTimestamp) {
            $range[$start->getDatabase()] = [1000*$start->getUnixTimeStamp(), 0, null];

            $start->addMinutes($this->getInterval());
        }

        return $range;
    }
}
