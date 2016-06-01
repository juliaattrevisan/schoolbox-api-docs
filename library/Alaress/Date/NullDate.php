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
        // set a locale so that locale based formatting doesn't fatal error
        $this->setLocale(new Alaress_Date_Locale_Static());
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
     * This is a null date - it always formats as an empty string.
     *
     * @param string $format
     *
     * @return string
     */
    public function getUsingFormat($format)
    {
        return '';
    }

    /**
     * This is a null date - performing any sort of date operations will blow
     * up in your face.
     *
     * Note that `getUsingFormat()` bypasses this and returns an empty string.
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

    /**
     * Overrides parent's clone - this contains no date, so clone nothing.
     */
    public function __clone()
    {
    }
}
