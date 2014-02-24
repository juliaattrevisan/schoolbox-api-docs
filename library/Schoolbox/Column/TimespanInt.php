<?php
/**
 * Timespan column type, stored as an integer.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Column;
use DateInterval;

class TimespanInt extends Timespan {

    /**
     * Translates a value from a database field or other data store, into a
     * string, integer or object value for use in a model object.
     *
     * @param string            $value
     * @param \Schoolbox\Mapper $mapper
     *
     * @return DateInterval
     */
    public function hydrate($value, $mapper = null) {
        if (!is_numeric($value)) {
            return null;
        }
        $hours   = floor($value / 3600);
        $minutes = floor($value / 60) % 60;
        $seconds = $value % 60;
        return new DateInterval("PT{$hours}H{$minutes}M{$seconds}S");
    }

    /**
     * Translates a value from a value in a model object into a value for a
     * database field or other data store.
     *
     * @param DateInterval      $value
     * @param \Schoolbox\Mapper $mapper
     *
     * @return string
     */
    public function dehydrate($value, $mapper = null) {
        $hours   = null;
        $minutes = null;
        $seconds = null;
        if ($value instanceof DateInterval) {
            $hours   = $value->format('%h');
            $minutes = $value->format('%i');
            $seconds = $value->format('%s');
        }
        elseif (preg_match('/^(?P<h>\\d+):(?P<m>\\d+):(?P<s>\\d+)$/', $value, $matches)) {
            // H:MM:SS
            $hours   = $matches['h'];
            $minutes = $matches['m'];
            $seconds = $matches['s'];
        }
        elseif (preg_match('/^(?P<h>\\d+):(?P<m>\\d+)$/', $value, $matches)) {
            // H:MM
            $hours   = $matches['h'];
            $minutes = $matches['m'];
            $seconds = 0;
        }
        if (isset($hours)) {
            return ($hours * 3600) + ($minutes * 60) + $seconds;
        }
        return null;
    }

}