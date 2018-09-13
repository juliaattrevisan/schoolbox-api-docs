<?php
/**
 * Month
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
     * Not presently used in this strat; we can shortcut it with a simpler interpretation
     * Classically you could use getInterval() and return PERIOD_LENGTH etc. but we can hardcode
     * an intended result instead for now
     *
     * @return int
     */
    public function getInterval(): int
    {
        return 0;
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

        /* Special format for month; there are only ever two data points because of our crontab deletion
         */
        $range = [
            $start->getDatabase() => [$start->getUnixTimeStamp()*1000, 0],
            $end->getDatabase() => [$endTimestamp*1000, 0]
        ];

        return $range;
    }
}
