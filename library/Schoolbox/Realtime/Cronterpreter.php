<?php

/**
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
    /**
     * @param string $timeFormat
     *  - PHP time format string e.g. 'g:ia'.
     *    This value should come straight out of the `config` db table for name 'date_time'.
     * @param string $weekendDays
     *  - Comma-separated string of integers representing days (0 = Sunday, 6 = Saturday).
     *    This value should come straight out of the `config` db table for name 'locale_weekend'.
     */
    public function __construct(private string $timeFormat, private string $weekendDays)
    {
        $this->setTimeFormat($timeFormat);
        $this->setWeekendDays($weekendDays);
    }

    /**
     * @return string
     */
    public function getTimeFormat(): string
    {
        return $this->timeFormat;
    }

    /**
     * This *should* be set to config.date_time
     *
     * @param string $timeFormat - PHP time format string e.g. 'g:ia'.
     * @return static
     */
    public function setTimeFormat(string $timeFormat): static
    {
        $this->timeFormat = $timeFormat;
        return $this;
    }

    /**
     * @return string
     */
    public function getWeekendDays(): string
    {
        return $this->weekendDays;
    }

    /**
     * @param string $weekendDays
     *  - Comma-separated string of integers representing days (0 = Sunday, 6 = Saturday).
     * @return static
     */
    public function setWeekendDays(string $weekendDays): static
    {
        $this->weekendDays = $weekendDays;
        return $this;
    }

    /**
     * @param string $crontab
     * @throws InvalidArgumentException
     * @return string
     */
    public function interpret(string $crontab): string
    {
        $cron = new CronExpression($crontab);
        $selectedDayIndexes = explode(',', $cron->getExpression(CronExpression::WEEKDAY));

        $desc  = $this->convertDayIndexesToText($selectedDayIndexes);
        $desc .= sprintf(' at %s', date($this->getTimeFormat(), strtotime(
            $cron->getExpression(CronExpression::HOUR) . ":" .
            $cron->getExpression(CronExpression::MINUTE)
        )));

        return $desc;
    }

    /**
     * @param array $dayIndexes - Array of integers representing days of the week (0 = Sunday, 6 = Saturday).
     * @return string
     */
    protected function convertDayIndexesToText(array $dayIndexes): string
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

        $weekendDayIndexes = array_map('intval', explode(',', $this->weekendDays));
        $weekdayIndexes = array_diff(array_keys($dayNameByIndex), $weekendDayIndexes); // All days that aren't weekend days.
        $dayIndexesAsKeys = array_fill_keys($dayIndexes, null);
        $nDaysSelected = count($dayIndexes);
        $desc = '';

        if ($nDaysSelected === 7) {
            // The selected day list contains all days.
            $desc = 'weekends and weekdays';
        } else {
            if ($this->arrayKeysExist($dayIndexesAsKeys, $weekdayIndexes)) {
                // The selected day list contains all weekdays.
                $desc = 'weekdays';
                $selectedOtherDayIndexes = array_diff($dayIndexes, $weekdayIndexes);
            } elseif ($this->arrayKeysExist($dayIndexesAsKeys, $weekendDayIndexes)) {
                // The selected day list contains both weekend days.
                $desc = 'weekends';
                $selectedOtherDayIndexes = array_diff($dayIndexes, $weekendDayIndexes);
            } else {
                // The selected day list does not contain both weekend days and does not contain all weekday days.
                $selectedOtherDayIndexes = $dayIndexes;
            }

            if (count($selectedOtherDayIndexes) > 0) {
                $terms = ($desc === '') ? [] : [$desc];

                foreach ($selectedOtherDayIndexes as $weekdayIndex) {
                    $terms[] = $dayNameByIndex[$weekdayIndex];
                }

                $desc = $this->createGrammaticalList($terms);
            }
        }

        return $desc;
    }

    /**
     * Return true if all keys in $keys exist in $array.  Return false otherwise.
     * Note if $keys is empty, false is returned.
     * @param array $array
     * @param array $keys
     * @return bool
     */
    protected function arrayKeysExist(array $array, array $keys): bool
    {
        if (count($keys) === 0) {
            return false;
        }

        foreach ($keys as $key) {
            if (!array_key_exists($key, $array)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create a string like "$term1, $term2 and $term3"
     * from an array of terms (strings).
     *
     * @param array $terms
     * @return string
     */
    protected function createGrammaticalList(array $terms): string
    {
        switch (count($terms)) {
            case 0:
                return '';
            case 1:
                return $terms[0];
            default:
                $lastTerm = array_pop($terms);
                return implode(', ', $terms) . " and $lastTerm";
        }
    }
}
