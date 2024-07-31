<?php


/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime;

use Cron\CronExpression;
use InvalidArgumentException;
use Schoolbox\Config\Loader as ConfigLoader;

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
     * @param ConFigLoader $config
     */
    public function __construct(protected ConfigLoader $config)
    {
        $this->setTimeFormat($this->config->get('date_time'));

        $this->weekendDayIndexes = array_map('intval', explode(',', $this->config->get('locale_weekend')));
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

        $weekdayIndexes = array_diff(array_keys($dayNameByIndex), $this->weekendDayIndexes); // All days that aren't weekend days.

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
            } elseif ($this->arrayKeysExist($selectedDayIndexesAsKeys, $this->weekendDayIndexes)) {
                // The selected day list contains both weekend days.
                $desc = 'weekends';
                $selectedOtherDayIndexes = array_diff($selectedDayIndexes, $this->weekendDayIndexes);
            } else {
                // The selected day list does not contain both weekend days and does not contain all weekday days.
                $selectedOtherDayIndexes = $selectedDayIndexes;
            }

            if (count($selectedOtherDayIndexes) > 0) {
                $terms = ($desc === '') ? [] : [$desc];

                foreach ($selectedOtherDayIndexes as $weekdayIndex) {
                    $terms[] = $dayNameByIndex[$weekdayIndex];
                }

                $desc = implode(', ', $terms);
            }
        }

        $desc .= sprintf(' at %s', date($this->getTimeFormat(), strtotime(
            $cron->getExpression(CronExpression::HOUR) . ":" .
                $cron->getExpression(CronExpression::MINUTE)
        )));

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
