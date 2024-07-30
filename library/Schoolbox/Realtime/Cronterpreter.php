<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
    public function __construct($timeFormat)
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
        $dayNameByIndex = [
            0 => 'Sundays',
            1 => 'Mondays',
            2 => 'Tuesdays',
            3 => 'Wednesdays',
            4 => 'Thursdays',
            5 => 'Fridays',
            6 => 'Saturdays'
        ];
        $weekendIndexes = $this->getConfiguredWeekendDays();
        $weekdayIndexes = array_diff(array_keys($dayNameByIndex), $weekendIndexes); // All days that aren't weekend days.

        $cron = new CronExpression($crontab);
        $selectedDayIndexes = explode(',', $cron->getExpression(CronExpression::WEEKDAY));
        $selectedDayIndexesAsKeys = array_fill_keys($selectedDayIndexes, null);
        $nDaysSelected = count($selectedDayIndexes);
        $desc = '';

        if ($nDaysSelected === 7) {
            // The selected day list contains all days.
            $desc = 'weekends and weekdays';
        } else {
            if ($this->arrayKeysExist($selectedDayIndexesAsKeys, $weekdayIndexes)) {
                // The selected day list contains all weekdays.
                $desc = 'weekdays';
                $selectedOtherDayIndexes = array_diff($selectedDayIndexes, $weekdayIndexes);
            } elseif ($this->arrayKeysExist($selectedDayIndexesAsKeys, $weekendIndexes)) {
                // The selected day list contains both weekend days.
                $desc = 'weekends';
                $selectedOtherDayIndexes = array_diff($selectedDayIndexes, $weekendIndexes);
            } else {
                // The selected day list does not contain both weekend days and does not contain all weekday days.
                $selectedOtherDayIndexes = $selectedDayIndexes;
            }

            if (count($selectedOtherDayIndexes) > 0) {
                $terms = ($desc === '') ? [] : [$desc];

                foreach ($selectedOtherDayIndexes as $weekdayIndex) {
                    $terms[] = $dayNameByIndex[$weekdayIndex];
                }

                $desc = $this->createOxfordCommaListStringFromArray($terms);
            }
        }

        $desc .= sprintf(' at %s', date($this->getTimeFormat(), strtotime(
            $cron->getExpression(CronExpression::HOUR) . ":" .
                $cron->getExpression(CronExpression::MINUTE)
        )));

        return $desc;
    }

    /**
     * @param array $array
     * @param array $keys
     * @return bool
     */
    private function arrayKeysExist(array $array, array $keys): bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $array)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create a string like "$term1, $term2, and $term3"
     * from an array of terms (strings).
     *
     * @param array $terms
     * @return string
     */
    private function createOxfordCommaListStringFromArray(array $terms): string
    {
        switch (count($terms)) {
            case 0:
                return '';
            case 1:
                return $terms[0];
            case 2:
                return "{$terms[0]} and {$terms[1]}";
            default:
                $lastIndex = count($terms) - 1;
                $terms[$lastIndex] = "and {$terms[$lastIndex]}";
                return implode(', ', $terms);
        }
    }

    /**
     * Return a two-element array containing the day indexes that make up the weekend,
     * obtained from the config locale_weekend value.  Possible indexes are 0 for Sunday, through 6 for Saturday.
     * @return array
     */
    private function getConfiguredWeekendDays(): array
    {
        return [0, 6];
    }
}
