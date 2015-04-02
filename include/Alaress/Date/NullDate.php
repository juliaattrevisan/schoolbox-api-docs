<?php
/**
 * Null date class - this represents a null date object.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Alaress_Date_NullDate extends Alaress_Date_Format
{
    /**
     * Constructs a null date from whatever got passed in.
     */
    public function __construct()
    {
        // ignore everything
    }

    /**
     * A null date is a valid date, I guess?
     *
     * @return true
     */
    public function isValid()
    {
        return true;
    }

    /**
     * This is a null date
     *
     * @return true
     */
    public function isNull()
    {
        return true;
    }

    /**
     * This is a null date - performing any sort of date operations will blow
     * up in your face.
     *
     * @throws BadMethodCallException
     */
    public function getDateTimeObject()
    {
        throw new BadMethodCallException('No operations may be performed on a null date');
    }

    /**
     * String representation is always an empty string.
     *
     * @return string
     */
    public function __toString()
    {
        return '';
    }
}
