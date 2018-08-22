<?php
/**
 * Month
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Statistics\Accuracy;

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
     * Number of minutes to add when generating date placeholders in generateDateRange()
     *
     * @return int
     */
    public function getInterval(): int
    {
        return 31*24*60;
    }


    /**
     * @inheritdoc
     *
     * @param \Alaress_Date_Format $start
     * @param \Alaress_Date_Format $end
     * @return array
     */
    public function generateDateRange(\Alaress_Date_Format $start, \Alaress_Date_Format $end): array
    {
        $start->startOfMonth()->startOfDay();
        $endTimestamp = $end->startOfMonth()->startOfDay()->getUnixTimeStamp();

        $range = [];

        /* Multiply by 1000 to get milliseconds conversion, because highcharts
         */
        while ($start->getUnixTimeStamp() < $endTimestamp) {
            $range[$start->getDatabase()] = [1000*$start->getUnixTimeStamp(), 0];

            $start->addMinutes($this->getInterval());
        }

        return $range;
    }
}