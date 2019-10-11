<?php
/**
 * Month
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Comms\News\Statistics\Scale;

class Month extends Base
{

    /**
     * @return string
     */
    public function getSql(): string
    {
        return "
            CONCAT(SUBSTRING(occurs_at FROM 1 FOR 7), '-01 00:00:00') AS datetime,
            1000*UNIX_TIMESTAMP(CONCAT(SUBSTRING(occurs_at FROM 1 FOR 7), '-01 00:00:00')) AS timestamp,
        ";
    }


    /**
     * Not used for months; they don't increment uniformly, they have different numbers of days per month
     *
     * @return int
     */
    public function getInterval(): int
    {
        return 0;
    }

    /**
     * Generate a range of datetime values, starting at midnight of $start,
     * ending at midnight of $end, at intervals (a value in minutes)
     *
     * Month requires some extra trickery because not all months are the same length
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
         */
        while ($start->getUnixTimeStamp() <= $endTimestamp) {
            $range[$start->getDatabase()] = [1000*$start->getUnixTimeStamp(), 0, null];

            /* Arbitrarily add one month per iteration, rather than calculate based on seconds
             * Months aren't the same length and cannot be incremented uniformly, except like this
             */
            $start->addMonths(1);
        }

        return $range;
    }
}
