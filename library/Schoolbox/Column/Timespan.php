<?php

/**
 * Timespan column type, stored as an integer.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column;

use DateInterval;

class Timespan implements IColumnType
{

    /**
     * Translates a value from a database field or other data store, into a
     * string, integer or object value for use in a model object.
     *
     * @param null|int            $value
     * @param \Schoolbox\Mapper $mapper
     *
     * @return DateInterval|null
     */
    public function hydrate($value, $mapper = null)
    {
        if (!is_numeric($value)) {
            return null;
        }
        $negative = false;
        if ($value < 0) {
            $negative = true;
            $value    = abs($value);
        }
        $hours    = floor($value / 3600);
        $minutes  = floor($value / 60) % 60;
        $seconds  = $value % 60;
        $interval = new DateInterval("PT{$hours}H{$minutes}M{$seconds}S");
        if ($negative) {
            $interval->invert = 1;
        }
        return $interval;
    }

    /**
     * Translates a value from a value in a model object into a value for a
     * database field or other data store.
     *
     * @param null|int|DateInterval|string $value
     * @param \Schoolbox\Mapper $mapper
     *
     * @return int|null
     */
    public function dehydrate($value, $mapper = null)
    {
        $hours    = null;
        $minutes  = null;
        $seconds  = null;
        $negative = false;
        if (is_numeric($value)) {
            return intval($value);
        }
        if ($value instanceof DateInterval) {
            $hours    = $value->format('%h');
            $minutes  = $value->format('%i');
            $seconds  = $value->format('%s');
            $negative = $value->invert;
        } elseif (preg_match('/^(?P<i>-)?(?P<h>\\d+):(?P<m>\\d+):(?P<s>\\d+)$/', $value, $matches)) {
            // H:MM:SS
            $hours    = $matches['h'];
            $minutes  = $matches['m'];
            $seconds  = $matches['s'];
            $negative = !empty($matches['i']);
        } elseif (preg_match('/^(?P<i>-)?(?P<h>\\d+):(?P<m>\\d+)$/', $value, $matches)) {
            // H:MM
            $hours    = $matches['h'];
            $minutes  = $matches['m'];
            $seconds  = 0;
            $negative = !empty($matches['i']);
        }
        if (isset($hours)) {
            $span = ($hours * 3600) + ($minutes * 60) + $seconds;
            if ($negative) {
                $span *= -1;
            }
            return $span;
        }
        return null;
    }


    /**
     * @inheritDoc
     */
    public function isScalar(): bool
    {
        return false;
    }
}
