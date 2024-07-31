<?php


/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime;

use Alaress_Date_Locale_Config;
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
    protected string $timeFormat;

    /**
     * @var array
     */
    private array $weekendDayIndexes = [];

    /**
     * @param Alaress_Date_Locale_Config $localeConfig
     */
    public function __construct(protected Alaress_Date_Locale_Config $localeConfig)
    {
        $this->setTimeFormat($this->localeConfig->timeOnlyFormat());
        $this->weekendDayIndexes = $this->localeConfig->weekendDays();
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
    public function convertDayIndexesToText(array $dayIndexes): string
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

        $weekdayIndexes = array_diff(array_keys($dayNameByIndex), $this->weekendDayIndexes); // All days that aren't weekend days.
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
            } elseif ($this->arrayKeysExist($dayIndexesAsKeys, $this->weekendDayIndexes)) {
                // The selected day list contains both weekend days.
                $desc = 'weekends';
                $selectedOtherDayIndexes = array_diff($dayIndexes, $this->weekendDayIndexes);
            } else {
                // The selected day list does not contain both weekend days and does not contain all weekday days.
                $selectedOtherDayIndexes = $dayIndexes;
            }

            if (count($selectedOtherDayIndexes) > 0) {
                $terms = ($desc === '') ? [] : [$desc];

                foreach ($selectedOtherDayIndexes as $weekdayIndex) {
                    $terms[] = $dayNameByIndex[$weekdayIndex];
                }

                $desc = implode(', ', $terms);
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
    private function arrayKeysExist(array $array, array $keys): bool
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
}
