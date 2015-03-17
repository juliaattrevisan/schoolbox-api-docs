<?php

/**
 * Alaress_Date_Format
 *
 * Facade class. This facilitates a layer of abstraction so you can switch in
 * and out of date processing classes via the $strategy class variable.
 *
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Jenssegers\Date\Date as LaravelDate;

class Alaress_Date_Format implements Alaress_Validator_ValidatableInterface
{
    /**
     * @var LavarelDate
     */
    protected $datetime;

    /**
     * @var DateTimeZone
     */
    protected $timezone;

    /**
     * @var Alaress_Date_Locale_Interface
     */
    protected $locale;

    /**
     * Any warnings that might have been raised in creation of this object.
     *
     * @var array
     */
    protected $warnings = array();

    const DATABASE_FORMAT = 'Y-m-d H:i:s';
    const DATABASE_DATE_FORMAT = 'Y-m-d';
    const DATABASE_TIME_FORMAT = 'H:i:s';
    const TIMESTAMP = 'U';

    /**
     * Returns an instance of self at the current time.
     * Method gives precedence to REQUEST_TIME, but accepts that outside HTTP requests, it doesn't exist.
     *
     * @return static
     */
    public static function now()
    {
        if (isset($_SERVER['REQUEST_TIME'])) {
            $time = $_SERVER['REQUEST_TIME'];
        } else {
            $time = time();
        }

        return new static($time, 'U');
    }

    /**
     * Set up datetime data for processing. Choose your strategy here!
     * Choose your locale as well; this would probably come from the Config
     * table rather than have a concrete instantiation
     *
     * @see Alaress_Date_Strategy_Abstract->setFromFormat() for param description
     *
     * @param string|object $dateTime
     * @param string $dateFormat
     * @param DateTimeZone $timezone The timezone of the date, or null to use default
     */
    public function __construct($dateTime = null, $dateFormat = null, DateTimeZone $timezone = null, $locale = null)
    {

        if (!($timezone instanceof DateTimeZone)) {
            $timezone = new DateTimeZone(date_default_timezone_get());
        }

        if($locale instanceof Alaress_Date_Locale_Interface) {
            $this->setLocale($locale);
        }
        else {
            $this->setLocale(new Alaress_Date_Locale_Config());
        }



        $this->setTimezone($timezone);
        $this->setFromFormat($dateTime, $dateFormat);

    }

    /**
     * Attempts to create a Laravel date out of $dateTime.
     *
     * * empty: creates as now
     * *
     * @param mixed $dateTime
     *
     * @return LaravelDate
     * @throws InvalidArgumentException if date couldn't be built
     */
    protected function buildLaravelDate($dateTime = null)
    {
        if (!isset($dateTime)) {
            // Nothing set? set it to now
            return LaravelDate::now($this->timezone);
        } elseif ($dateTime instanceof DateTime || $dateTime instanceof DateTimeInterface) {
            // Build out of timestamp and timezone
            return LaravelDate::createFromTimestamp(
                $dateTime->getTimestamp(),
                $dateTime->getTimezone()
            );
        } elseif ($dateTime instanceof Alaress_Date_Format) {
            // Build out of date object's timestamp/timezone
            return LaravelDate::createFromTimestamp(
                $dateTime->getDateTimeObject()->getTimestamp(),
                $dateTime->getDateTimeObject()->getTimezone()
            );
        }
        throw new InvalidArgumentException('Could not create LaravelDate from $dateTime');
    }

    /**
     * @param DateTimeInterface|Alaress_Date_Format|string $dateTime
     * @param string $inputDateFormat
     *
     * @return void
     * @throws InvalidArgumentException if no $inputDateFormat was set and $dateTime
     *                                  is a string
     * @throws RuntimeException         if $dateTime is a string and it can't be
     *                                  parsed in $inputDateFormat
     */
    public function setFromFormat($dateTime, $inputDateFormat)
    {
        try {
            $newDateTime = $this->buildLaravelDate($dateTime);
            $this->setDateTimeObject($newDateTime);
        } catch (InvalidArgumentException $e) {
            if (!isset($inputDateFormat)) {
                throw new InvalidArgumentException(
                    '$inputDateFormat must be supplied if $dateTime is not a date object'
                );
            }

            $newDateTime = DateTime::createFromFormat($inputDateFormat, $dateTime);
            $errors = DateTime::getLastErrors();
            if ($errors['error_count']) {
                // I don't have a datetime, try again with trailing data allowed
                $newDateTime = DateTime::createFromFormat($inputDateFormat . '+', $dateTime);
                if (!($newDateTime instanceof DateTime)) {
                    // I still don't have a datetime, exception out
                    $errors = DateTime::getLastErrors();
                    throw new InvalidArgumentException(
                        sprintf(
                            'LaravelDate creation failed with %d errors: (%s)',
                            $errors['error_count'],
                            implode(', ', $errors['errors'])
                        )
                    );
                }
            }
            if ($errors['warning_count']) {
                // I have a datetime, but it's not what I expected. Warn the user.
                // This date will also fail isValid()
                $this->warnings = $errors['warnings'];
                trigger_error(
                    sprintf(
                        'LaravelDate creation raised %d warnings: (%s)',
                        $errors['warning_count'],
                        implode(', ', $errors['warnings'])
                    ),
                    E_USER_WARNING
                );
            }
            $laravelDate = LaravelDate::instance($newDateTime);

            // Did I specify a timezone? If not, set it to the default
            if (!preg_match('/[eOPT]/', $inputDateFormat)) {
                $laravelDate->setTimezone($this->timezone);
            }
            $this->setDateTimeObject($laravelDate);
        }
    }

    /**
     * Set Locale configuration object
     *
     * @param  Alaress_Date_Locale_Interface $val
     * @return void
     */
    public function setLocale($val)
    {
        $this->locale = $val;
    }

    /**
     * Get Locale configuration object
     *
     * @return Alaress_Date_Locale_Interface
     */
    public function getLocale()
    {
        return $this->locale;
    }


    /**
     * @param $year
     * @return $this
     */
    public function setYear($year)
    {
        $this->getDateTimeObject()->setYear($year);
        return $this;
    }

    /**
     * @param $month
     * @return $this
     */
    public function setMonth($month)
    {
        $this->getDateTimeObject()->setYear($month);
        return $this;
    }

    /**
     * @param $day
     * @return $this
     */
    public function setDay($day)
    {
        $this->getDateTimeObject()->setYear($day);
        return $this;
    }

    /**
     * @param $hour
     * @return $this
     */
    public function setHour($hour)
    {
        $this->getDateTimeObject()->setYear($hour);
        return $this;
    }

    /**
     * @param $minute
     * @return $this
     */
    public function setMinute($minute)
    {
        $this->getDateTimeObject()->setYear($minute);
        return $this;
    }

    /**
     * @param $second
     * @return $this
     */
    public function setSecond($second)
    {
        $this->getDateTimeObject()->setYear($second);
        return $this;
    }


    /**
     * @param DateTimeZone $timezone
     * @return $this
     */
    public function setTimezone($timezone)
    {
        $this->timezone = $timezone;
        return $this;
    }

    /**
     * @param $year
     * @param $month
     * @param $day
     * @param $hour
     * @param $min
     * @param $sec
     * @return $this
     */
    public function setDateTime($year, $month, $day, $hour, $min, $sec)
    {
        $this->getDateTimeObject()->setDateTime($year, $month, $day, $hour, $min, $sec);
        return $this;
    }

    /**
     * @param $year
     * @param $month
     * @param $day
     * @return $this
     */
    public function setDate($year, $month, $day)
    {
        $this->getDateTimeObject()->setDateTime($year, $month, $day);
        return $this;
    }

    /**
     * @param $hour
     * @param $min
     * @param $sec
     * @return $this
     */
    public function setTime($hour, $min, $sec)
    {
        $this->getDateTimeObject()->setDateTime($hour, $min, $sec);
        return $this;
    }

    /**
     * @param $timestamp
     * @return $this
     */
    public function setTimestamp($timestamp)
    {
        $this->getDateTimeObject()->setTimestamp($timestamp);
        return $this;
    }

    /**
     * Add/subtract seconds and reinitialise
     *
     * @param $val
     * @return $this
     */
    public function addSeconds($val)
    {
        $this->getDateTimeObject()->addSeconds($val);
        return $this;
    }

    /**
     * Add/subtract minutes and reinitialise
     *
     * @param $val
     * @return \Alaress_Date_Format
     */
    public function addMinutes($val)
    {
        $this->getDateTimeObject()->addMinutes($val);
        return $this;
    }

    /**
     * Add/subtract hours and reinitialise
     *
     * @param $val
     * @return \Alaress_Date_Format
     */
    public function addHours($val)
    {
        $this->getDateTimeObject()->addHours($val);
        return $this;
    }

    /**
     * Add/subtract days and reinitialise
     *
     * @param  $val
     * @return \Alaress_Date_Format
     */
    public function addDays($val)
    {
        $this->getDateTimeObject()->addDays($val);
        return $this;
    }

    /**
     * @param $val
     * @return $this
     */
    public function addMonths($val)
    {
        $this->getDateTimeObject()->addMonths($val);
        return $this;
    }

    /**
     * @param $val
     * @return $this
     */
    public function addWeeks($val)
    {
        $this->getDateTimeObject()->addWeeks($val);
        return $this;
    }

    /** FORMATS **/


    /**
     * 1975-12-25T14:15:16-05:00
     * @return string
     */
    public function toAtomString()
    {
        return $this->getDateTimeObject()->toAtomString();

    }

    /**
     * Thursday, 25-Dec-1975 14:15:16 EST
     *
     * @return string
     */
    public function toCookieString()
    {
        return $this->getDateTimeObject()->toCookieString();

    }

    /**
     * 1975-12-25T14:15:16-0500
     *
     * @return string
     */
    public function toIso8601String()
    {
        return $this->getDateTimeObject()->toIso8601String();

    }

    /**
     * Thu, 25 Dec 75 14:15:16 -0500
     *
     * @return string
     */
    public function toRfc822String()
    {
        return $this->getDateTimeObject()->toRfc822String();

    }

    /**
     * Thu, 25 Dec 75 14:15:16 -0500
     *
     * @return string
     */
    public function toRfc850String()
    {
        return $this->getDateTimeObject()->toRfc850String();

    }

    /**
     * Thu, 25 Dec 75 14:15:16 -0500
     *
     * @return string
     */
    public function toRfc1036String()
    {
        return $this->getDateTimeObject()->toRfc1036String();

    }

    /**
     * Thu, 25 Dec 1975 14:15:16 -0500
     *
     * @return string
     */
    public function toRfc1123String()
    {
        return $this->getDateTimeObject()->toRfc1123String();

    }

    /**
     * Thu, 25 Dec 1975 14:15:16 -0500
     *
     * @return string
     */
    public function toRfc2822String()
    {
        return $this->getDateTimeObject()->toRfc2822String();
    }

    /**
     * @return string
     */
    public function toRfc3339String()
    {
        // 1975-12-25T14:15:16-05:00
        return $this->getDateTimeObject()->toRfc3339String();

    }

    /**
     * Thu, 25 Dec 1975 14:15:16 -0500
     *
     * @return string
     */
    public function toRssString()
    {
        return $this->getDateTimeObject()->toRssString();

    }

    /**
     * 1975-12-25T14:15:16-05:00
     *
     * @return string
     */
    public function toW3cString()
    {
        return $this->getDateTimeObject()->toW3cString();

    }


    /** MODIFIERS **/

    /**
     * Resets the time to 00:00:00
     *
     * @return $this
     */
    public function startOfDay()
    {
        $this->getDateTimeObject()->startOfDay();
        return $this;
    }

    /**
     * Resets the time to 23:59:59
     *
     * @return $this
     */
    public function endOfDay()
    {
        $this->getDateTimeObject()->endOfDay();
        return $this;
    }

    /**
     * Resets the date to the first day of the month and the time to 00:00:00
     *
     * @return $this
     */
    public function startOfMonth()
    {
        $this->getDateTimeObject()->startOfMonth();
        return $this;

    }

    /**
     * Resets the date to end of the month and time to 23:59:59
     *
     * @return $this
     */
    public function endOfMonth()
    {
        $this->getDateTimeObject()->endOfMonth();
        return $this;

    }

    /**
     * Resets the date to the first day of the year and the time to 00:00:00
     *
     * @return $this
     */
    public function startOfYear()
    {
        $this->getDateTimeObject()->startOfYear();
        return $this;

    }

    /**
     * Resets the date to end of the century and time to 23:59:59
     *
     * @return $this
     */
    public function endOfYear()
    {
        $this->getDateTimeObject()->endOfYear();
        return $this;

    }

    /**
     * Resets the date to the first day of the ISO-8601 week (Monday) and the time to 00:00:00
     *
     * @return $this
     */
    public function startOfWeek()
    {
        $this->getDateTimeObject()->startOfWeek();
        return $this;

    }

    /**
     * Resets the date to end of the ISO-8601 week (Sunday) and time to 23:59:59
     *
     * @return $this;
     */
    public function endOfWeek()
    {
        $this->getDateTimeObject()->endOfWeek();
        return $this;

    }

    /**
     * Modify to the next occurance of a given day of the week.
     * If no dayOfWeek is provided, modify to the next occurance
     * of the current day of the week.  Use the supplied consts
     * to indicate the desired dayOfWeek, ex. static::MONDAY.
     *
     * @param int $dayOfWeek
     * @return $this
     */
    public function next($dayOfWeek)
    {
        $this->getDateTimeObject()->next($dayOfWeek);
        return $this;

    }

    /**
     * Modify to the previous occurance of a given day of the week.
     * If no dayOfWeek is provided, modify to the previous occurance
     * of the current day of the week.  Use the supplied consts
     * to indicate the desired dayOfWeek, ex. static::MONDAY.
     *
     * @param int $dayOfWeek
     * @return $this
     */
    public function previous($dayOfWeek)
    {
        $this->getDateTimeObject()->previous($dayOfWeek);
        return $this;
    }


    /**
     * Transform the datetime into a localised version
     *
     * @return string
     */
    public function getLocal()
    {
        return $this->getUsingFormat($this->getLocale()->localFormat());
    }

    /**
     * Transform the datetime into a localised short version
     *
     * @return string
     */
    public function getLocalShort()
    {
        return $this->getUsingFormat($this->getLocale()->localShortFormat());
    }

    /**
     * Use long format
     *
     * @return string
     */
    public function getLong()
    {
        return $this->getUsingFormat($this->getLocale()->longDateFormat());
    }

    /**
     * Use long format
     *
     * @return string
     */
    public function getLongDateOnly()
    {
        return $this->getUsingFormat($this->getLocale()->longDateOnlyFormat());
    }

    /**
     * Use short format
     *
     * @return string
     */
    public function getShort()
    {
        return $this->getUsingFormat($this->getLocale()->shortFormat());
    }

    /**
     * Get the date only
     *
     * @return string
     */
    public function getDateOnly()
    {
        return $this->getUsingFormat($this->getLocale()->dateOnlyFormat());
    }

    /**
     * Get the date only
     *
     * @return string
     */
    public function getTimeOnly()
    {
        return $this->getUsingFormat($this->getLocale()->timeOnlyFormat());
    }

    /**
     * Transform the datetime into the format specified in the config table
     * Do some error checking
     *
     * @return string
     */
    public function getDatabase()
    {
        return $this->getUsingFormat(self::DATABASE_FORMAT);
    }

    /**
     * Transform the datetime into a database date
     *
     * @return string
     */
    public function getDatabaseDate()
    {
        return $this->getUsingFormat(self::DATABASE_DATE_FORMAT);
    }

    /**
     * Transform the datetime into a database time
     *
     * @return string
     */
    public function getDatabaseTime()
    {
        return $this->getUsingFormat(self::DATABASE_TIME_FORMAT);
    }

    /**
     * Transform the datetime into a unix timestamp
     *
     * @return string
     */
    public function getUnixTimeStamp()
    {
        return $this->getUsingFormat(self::TIMESTAMP);
    }

    /**
     * Extract the numeric representation of the day of the week
     *
     * @return Integer
     */
    public function getWeekDayNumber()
    {
        return $this->getUsingFormat('N');
    }

    /**
     * Transform the datetime into a calendar date format
     *
     * @return string
     */
    public function getCalendarDate()
    {
        return $this->getUsingFormat($this->getLocale()->calendarDate());
    }

    /**
     * Transform the datetime into a calendar date format
     *
     * @return string
     */
    public function getCalendarDateTime()
    {
        return $this->getUsingFormat($this->getLocale()->calendarDateTime());
    }

    /**
     * Delegation method to get the datetime in an arbitrary format
     *
     * @param  $format
     * @return string
     */
    public function getUsingFormat($format)
    {
        return $this->getDateTimeObject()->format($format);
    }

    /**
     * Gets datetime object out of this wrapper
     *
     * @return LaravelDate
     */
    public function getDateTimeObject()
    {
        return $this->datetime;
    }

    /**
     * Gets datetime object out of this wrapper
     *
     * @param LaravelDate $dateObject
     */
    public function setDateTimeObject($dateObject)
    {
        $this->datetime = $dateObject;
    }


    /**
     * Returns whether this date is valid
     *
     * @return bool
     */
    public function isValid()
    {
        return empty($this->warnings);
    }

    /** COMPARIONS */

    /**
     * @param Alaress_Date_Format $second
     * @return mixed
     */
    public function eq($second)
    {
        return $this->getDateTimeObject()->eq($second->getDateTimeObject());
    }

    /**
     * @param Alaress_Date_Format $second
     * @return mixed
     */
    public function ne($second)
    {
        return $this->getDateTimeObject()->ne($second->getDateTimeObject());

    }

    /**
     * @param Alaress_Date_Format $second
     * @return mixed
     */
    public function gt($second)
    {
        return $this->getDateTimeObject()->gt($second->getDateTimeObject());

    }

    /**
     * @param Alaress_Date_Format $second
     * @return mixed
     */
    public function gte($second)
    {
        return $this->getDateTimeObject()->gte($second->getDateTimeObject());

    }

    /**
     * @param $second
     * @return bool
     */
    public function lt($second)
    {
        return $this->getDateTimeObject()->lt($second->getDateTimeObject());

    }

    /**
     * @param Alaress_Date_Format $second
     * @return bool
     */
    public function lte($second)
    {
        return $this->getDateTimeObject()->lte($second->getDateTimeObject());

    }

    /**
     * @param Alaress_Date_Format $first
     * @param Alaress_Date_Format $second
     * @param bool $equal
     * @return bool
     */
    public function between($first, $second, $equal = true)
    {
        return $this->getDateTimeObject()->between($first->getDateTimeObject(), $second->getDateTimeObject(), $equal);

    }


    /**
     * @return bool
     */
    public function isWeekday()
    {
        return $this->getDateTimeObject()->isWeekday();
    }

    /**
     * @return bool
     */
    public function isWeekend()
    {
        return $this->getDateTimeObject()->isWeekend();
    }

    /**
     * @return bool
     */
    public function isYesterday()
    {
        return $this->getDateTimeObject()->isYesterday();
    }

    /**
     * @return bool
     */
    public function isToday()
    {
        return $this->getDateTimeObject()->isToday();

    }

    /**
     * @return bool
     */
    public function isTomorrow()
    {
        return $this->getDateTimeObject()->isTomorrow();

    }

    /**
     * @return bool
     */
    public function isFuture()
    {
        return $this->getDateTimeObject()->isFuture();

    }

    /**
     * @return bool
     */
    public function isPast()
    {
        return $this->getDateTimeObject()->isPast();

    }

    /**
     * @return bool
     */
    public function isLeapYear()
    {
        return $this->getDateTimeObject()->isLeapYear();

    }

    /**
     * @param Alaress_Date_Format $date
     * @return bool
     */
    public function isSameDay($date)
    {
        return $this->getDateTimeObject()->isSameDay($date->getDateTimeObject());

    }


    /**
     * Compare function for comparing with another date object.
     *
     * @param Alaress_Date_Format $other
     *
     * @return bool
     */
    public function compare($other)
    {
        return $this->eq($other);
    }

    /**
     * Compares dates of two Alaress_Date_Format objects, ignoring the time
     * component completely.
     *
     * @param Alaress_Date_Format $other
     *
     * @return bool
     */
    public function compareDateOnly($other)
    {
        return $this->isSameDay($other);
    }


    /**
     * Is this date object at the start of the day?
     *
     * Currently this check ignores seconds.
     *
     * @return bool
     */
    public function isAtStartOfDay()
    {
        return $this->getUsingFormat('Hi') == '0000';
    }

    /**
     * Is this date object at the end of the day?
     *
     * Currently this check ignores seconds.
     *
     * @return bool
     */
    public function isAtEndOfDay()
    {
        return $this->getUsingFormat('Hi') == '2359';
    }

    /**
     * {@inheritdoc}
     */
    public function getRelativeTime($now = null, $stripModifiers = false)
    {
        if (!isset($now)) {
            return $this->getDateTimeObject()->diffForHumans(null, $stripModifiers);
        }
        // Convert now to a carbon object, if I can, otherwise assume now
        try {
            return $this->getDateTimeObject()->diffForHumans($this->buildLaravelDate($now), $stripModifiers);
        } catch (InvalidArgumentException $e) {
            return $this->getDateTimeObject()->diffForHumans(null, $stripModifiers);
        }
    }

    /**
     * Outputs this string as a recognisable string representation of a date,
     * including timezone data.
     *
     * This is mainly for Twig - this makes its date filter work intuitively
     * with Alaress_Date_Format objects.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getUsingFormat('c');
    }
}
