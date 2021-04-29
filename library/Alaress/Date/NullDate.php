<?php

use Alaress\Date\IClock;

/**
 * Null date class - this represents a null date object.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 * @deprecated Please just use scalar null
 */
class Alaress_Date_NullDate extends Alaress_Date_Format
{
    /**
     * Constructs a null date from whatever got passed in.
     * @deprecated Please just use scalar null
     */
    public function __construct()
    {
        // set a locale so that locale based formatting doesn't fatal error
        $this->setLocale(new Alaress_Date_Locale_Static());
    }


    /**
     * @param IClock|null $clock
     * @return Alaress_Date_NullDate
     */
    public static function now(IClock $clock = null): Alaress_Date_Format
    {
        // No point caching and cloning this as this class's construction is light
        return new Alaress_Date_NullDate();
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
     * This is a null date: thus it will always return true
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
     * @inheritDoc
     */
    public function getJson(): string
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
