<?php

/**
 * Day
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Statistics\Scale;

class Day extends Base
{

    /**
     * @return string
     */
    public function getSql(): string
    {
        return "
            CONCAT(SUBSTRING(occurs_at FROM 1 FOR 11), '00:00:00') AS datetime,
            1000*UNIX_TIMESTAMP(CONCAT(SUBSTRING(occurs_at FROM 1 FOR 11), '00:00:00')) AS timestamp,
        ";
    }


    /**
     * Number of minutes to add when generating date placeholders in generateDateRange()
     *
     * @return int
     */
    public function getInterval(): int
    {
        return 24 * 60;
    }
}
