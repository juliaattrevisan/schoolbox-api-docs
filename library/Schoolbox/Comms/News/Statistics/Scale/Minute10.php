<?php

/**
 * Minute10
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Statistics\Scale;

class Minute10 extends Base
{

    /**
     * @return string
     */
    public function getSql(): string
    {
        return '
            FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(occurs_at)/(10*60))*(10*60)) AS datetime,
            1000*FLOOR(UNIX_TIMESTAMP(occurs_at)/(10*60))*(10*60) AS timestamp,
        ';
    }


    /**
     * Number of minutes to add when generating date placeholders in generateDateRange()
     *
     * @return int
     */
    public function getInterval(): int
    {
        return 10;
    }
}
