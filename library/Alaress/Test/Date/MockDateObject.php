<?php

/**
 * Trait for loading date objects with a mock locale that does not attempt to
 * access the database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Test\Date;

use Alaress_Date_Format;
use Alaress_Date_Locale_Interface;
use Alaress_Date_Locale_Static;
use DateTimeZone;

trait MockDateObject
{
    /**
     * Builds a date object which defaults to using a static locale.
     *
     * @see Alaress_Date_Format->__construct()
     *
     * @return Alaress_Date_Format
     */
    protected static function buildDateObject(
        $dateTime = null,
        $dateFormat = null,
        DateTimeZone $timezone = null,
        Alaress_Date_Locale_Interface $locale = null
    ) {
        return new Alaress_Date_Format(
            $dateTime,
            $dateFormat,
            $timezone,
            ($locale instanceof Alaress_Date_Locale_Interface ? $locale : new Alaress_Date_Locale_Static())
        );
    }
}
