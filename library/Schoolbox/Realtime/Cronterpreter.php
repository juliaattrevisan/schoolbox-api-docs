<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime;

use Cron\CronExpression;
use InvalidArgumentException;

/**
 * Cron + Interpreter: makes crontab human-readable
 *
 * Class Cronterpreter
 *
 * @package Schoolbox\Realtime
 */
class Cronterpreter 
{

    /** @var string */
    protected $timeFormat;


    /**
     * @param string $timeFormat
     */
    function __construct($timeFormat)
    {
        $this->setTimeFormat($timeFormat);
    }


    /**
     * @return string
     */
    public function getTimeFormat()
    {
        return $this->timeFormat;
    }


    /**
     * This *should* be set to config.date_time
     *
     * @param string $timeFormat
     * @return static
     */
    public function setTimeFormat($timeFormat)
    {
        $this->timeFormat = $timeFormat;
        return $this;
    }


    /**
     * @param string $crontab
     * @throws InvalidArgumentException
     * @return string
     */
    public function interpret($crontab)
    {
        $cron = CronExpression::factory($crontab);

        // parse out a human-readable form of the cron
        $test = [
            0 => 'Sundays',
            1 => 'Mondays',
            2 => 'Tuesdays',
            3 => 'Wednesdays',
            4 => 'Thursdays',
            5 => 'Fridays',
            6 => 'Saturdays'
        ];

        $days = explode(',', $cron->getExpression(CronExpression::WEEKDAY));

        if (!array_diff(array_keys($test), $days)) {
            // no diff: every day
            $fq = "every day";

        } elseif (!array_diff($days, array_slice(array_keys($test), 1, 5))) {
            // matches middle 5: weekdays
            $fq = "on weekdays";

        } elseif (!array_diff($days, [0, 6])) {
            // matches first and last: weekend
            $fq = "on weekends";

        } else {
            // something else
            $fq = 'on ' . implode(', ', array_intersect_key($test, array_flip($days)));

            if (($pos = strrpos($fq, ',')) !== false) {
                $fq = substr_replace($fq, ' &', $pos, 1);
            }
        }

        $fq .= sprintf(' at %s', date($this->getTimeFormat(), strtotime(
                $cron->getExpression(CronExpression::HOUR) . ":" .
                $cron->getExpression(CronExpression::MINUTE)
            ))
        );

        return $fq;
    }

}
