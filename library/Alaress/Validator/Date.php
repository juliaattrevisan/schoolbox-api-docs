<?php

/**
 * Alaress_Validator_Date
 *
 * A validator for date arrays. For a date array to be valid, the year, month, day, hour, minute and second fields must
 * all be valid, and must be integer values - no passing dates like "12th", "Jun", etc.
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Alaress_Validator_Date extends Alaress_Validator_Abstract
{

    protected $value = array();

    /**
     * Validates whether this date is actually valid.
     *
     * @param array $value The date to check - has keys for year, month, day, hour, minute, second.
     *
     * @return bool
     */
    public function isValid($value)
    {
        $this->setValue($value);
        return $this->isValidYear()
            && $this->isValidMonth()
            && $this->isValidDay()
            && $this->isValidHour()
            && $this->isValidMinute()
            && $this->isValidSecond();
    }


    /**
     * Helper function used by isValid*() to determine if a value is a valid integer or integer-format string.
     *
     * @param int $val The value to check.
     * @param int $min If set, validates that $val is not less than this value.
     * @param int $max If set, validates that $val is not greater than this value.
     *
     * @return bool
     */
    protected function isInteger($val, $min = null, $max = null)
    {
        $val      = trim($val);
        $is_valid = preg_match('/^\d+$/', $val) ? true : false;
        if (isset($min)) {
            $is_valid = $is_valid && ($val >= intval($min));
        }
        if (isset($max)) {
            $is_valid = $is_valid && ($val <= intval($max));
        }
        return $is_valid;
    }


    /**
     * Is this date's year valid?
     *
     * It just needs to be a valid integer value - there's no bounds checking yet.
     *
     * @return bool
     */
    protected function isValidYear()
    {
        return $this->isInteger($this->value['year']);
    }


    /**
     * Is this date's year valid?
     *
     * It just needs to be a valid integer value - there's no bounds checking yet.
     *
     * @return bool
     */
    protected function isValidMonth()
    {
        return $this->isInteger($this->value['month'], 1, 12);
    }


    /**
     * Is this date's day valid?
     *
     * It needs to be a valid day for the date's month and year.
     *
     * @return bool
     */
    protected function isValidDay()
    {
        $day = $this->value['day'];
        return ($this->isInteger($day) && checkdate($this->value['month'], $day, $this->value['year']));
    }


    /**
     * Is this date's hour valid?
     *
     * Currently checks whether hour is between 0 and 23 - there's no daylight saving checking yet.
     *
     * @return bool
     */
    protected function isValidHour()
    {
        return $this->isInteger($this->value['hour'], 0, 23);
    }


    /**
     * Is this date's minute valid?
     *
     * Currently checks whether minute is between 0 and 59.
     *
     * @return bool
     */
    protected function isValidMinute()
    {
        return $this->isInteger($this->value['minute'], 0, 59);
    }


    /**
     * Is this date's second valid?
     *
     * Currently checks whether second is between 0 and 59.
     *
     * @return bool
     */
    protected function isValidSecond()
    {
        return $this->isInteger($this->value['second'], 0, 59);
    }
}
