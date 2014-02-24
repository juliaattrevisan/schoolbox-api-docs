<?php
/**
 * Timespan column type.
 *
 * Stored as a TIME or VARCHAR column, and retrieved as a DateInterval object.
 * (A VARCHAR column may be needed because max value of a TIME column is
 *  838:59:59.)
 * Could store as an integer value, though I'm not sure I like that.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Column;
use DateInterval;

class Timespan implements IColumnType {

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
        if (preg_match('/^(?P<h>\\d+):(?P<m>\\d{2}):(?P<s>\\d{2})$/', $value, $matches)) {
            return new DateInterval("PT{$matches['h']}H{$matches['m']}M{$matches['s']}S");
        }
        return null;
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
        if ($value instanceof DateInterval) {
            return $value->format('%H:%I:%S');
        }
        elseif (preg_match('/^(?P<h>\\d+):(?P<m>\\d+):(?P<s>\\d+)$/', $value, $matches)) {
            // H:MM:SS
            return sprintf('%02d:%02d:%02d', $matches['h'], $matches['m'], $matches['s']);
        }
        elseif (preg_match('/^(?P<h>\\d+):(?P<m>\\d+)$/', $value, $matches)) {
            // HH:MM
            // If a value's over 60 then what can be done?
            return sprintf('%02d:%02d:00', $matches['h'], $matches['m']);
        }
        return null;
    }

}