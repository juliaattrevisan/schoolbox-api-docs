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
use Alaress\Date\Interval as IntervalObject;

class Interval implements IColumnType
{

    /**
     * Translates a value from a database field or other data store, into a
     * string, integer or object value for use in a model object.
     *
     * @param string            $value
     * @param \Schoolbox\Mapper $mapper
     *
     * @return IntervalObject|null
     */
    public function hydrate($value, $mapper = null)
    {
        if (!is_numeric($value)) {
            return null;
        }
        return new IntervalObject($value);
    }

    /**
     * Translates a value from a value in a model object into a value for a
     * database field or other data store.
     *
     * @param IntervalObject|DateInterval|int|string $value
     * @param \Schoolbox\Mapper $mapper
     *
     * @return int|null
     */
    public function dehydrate($value, $mapper = null)
    {
        if (is_numeric($value)) {
            return intval($value);
        }
        if ($value instanceof DateInterval) {
            $value = IntervalObject::createFromDateInterval($value);
        }
        if ($value instanceof IntervalObject) {
            $seconds = $value->getSecondTotal();
            if ($value->isInverted()) {
                $seconds *= -1;
            }
            return $seconds;
        } else {
            if (preg_match('/^(?P<i>-)?(?P<h>\\d+):(?P<m>\\d+):(?P<s>\\d+)$/', $value, $matches)) {
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
            if (isset($hours, $minutes, $seconds, $negative)) {
                $span = ($hours * 3600) + ($minutes * 60) + $seconds;
                if ($negative) {
                    $span *= -1;
                }
                return $span;
            }
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
