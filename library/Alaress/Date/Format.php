<?php

/**
 * Alaress_Date_Format
 *
 * Facade class. This facilitates a layer of abstraction so you can switch in
 * and out of date processing classes via the $strategy class variable.
 *
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use Alaress\Date\IClock;
use Alaress\Json\SerializeTrait;
use Jenssegers\Date\Date as LaravelDate;

class Alaress_Date_Format implements Alaress_Validator_ValidatableInterface, IComparable, \Alaress\Json\Serializable
{
    use SerializeTrait;

    /**
     * @var static
     */
    static private $now;

    /**
     * @var LaravelDate
     */
    protected $datetime;

    /**
     * @var Alaress_Date_Locale_Interface
     */
    protected $locale;


    /**
     * The default/system timezone for dates
     * @var DateTimeZone
     */
    static private $defaultTimezone;

    /**
     * Any warnings that might have been raised in creation of this object.
     *
     * @var array
     */
    protected $warnings = array();

    const DATABASE_FORMAT = 'Y-m-d H:i:s';
    const DATABASE_FORMAT_MS = 'Y-m-d H:i:s.u';
    const FORMAT_DATETIME_UTC = 'Y-m-d\TH:i:s.u\Z';  // eg '2015-09-22T23:22:12.798Z'
    const DATABASE_DATE_FORMAT = 'Y-m-d';
    const DATABASE_TIME_FORMAT = 'H:i:s';
    const TIMESTAMP = 'U';
    const URL_FORMAT = 'd-m-Y';
    const AM = 'AM';
    const PM = 'PM';
    public const FORMAT_DATE_PICKER = 'd/m/Y h:ia'; // Used by JS DatePicker, eg '29/07/2014 11:59pm'

    const SUNDAY = 0;
    const MONDAY = 1;
    const TUESDAY = 2;
    const WEDNESDAY = 3;
    const THURSDAY = 4;
    const FRIDAY = 5;
    const SATURDAY = 6;

    /**
     * Returns an instance of static at the current time.
     * Method gives precedence to REQUEST_TIME, but accepts that outside HTTP requests, it doesn't exist.
     *
     * @param IClock $clock
     * @return static
     */
    public static function now(IClock $clock = null): self
    {
        if ($clock) {
            // always give precendence to a clock if passed in
            return new static($clock->time(), 'U');
        }

        if (in_array(PHP_SAPI, ['cli', 'phpdbg'], true)) {
            // if we're CLI, do not freeze time
            return new static(time(), 'U');
        }

        // down here, cache the clock at time of request
        if (!self::$now instanceof static) {

            if (isset($_SERVER['REQUEST_TIME'])) {
                $time = $_SERVER['REQUEST_TIME'];

            } else {
                // @codeCoverageIgnoreStart
                $time = time();
                // @codeCoverageIgnoreEnd
            }

            static::$now = new static($time, 'U');
        }

        // never return the actual object, always a clone
        return clone static::$now;
    }

    /**
     * Static constructor: create from Unix timestamp.
     *
     * @param int                                $timestamp
     * @param DateTimeZone|null                  $timezone
     * @param Alaress_Date_Locale_Interface|null $locale
     * @return static
     */
    public static function newFromTimestamp(
        int $timestamp,
        DateTimeZone $timezone = null,
        Alaress_Date_Locale_Interface $locale = null
    ) {
        if (!$locale instanceof Alaress_Date_Locale_Interface) {
            // @codeCoverageIgnoreStart
            $locale = new Alaress_Date_Locale_Config();
            // @codeCoverageIgnoreEnd
        }
        // Zero out unset parts - IMO that's the least surprising behavior
        return new static($timestamp, self::TIMESTAMP, $timezone, $locale);
    }

    /**
     * Static constructor: creates a new date, with the format based on
     * $locale->localFormat().
     *
     * @param string                        $dateTime
     * @param DateTimeZone                  $timezone Optional timezone
     * @param Alaress_Date_Locale_Interface $locale   Optional locale, falls back to
     *                                                new Alaress_Date_Locale_Config()
     *
     * @return static
     * @throws InvalidArgumentException If unable to build date
     */
    public static function newLocal(
        $dateTime,
        DateTimeZone $timezone = null,
        Alaress_Date_Locale_Interface $locale = null
    ) {
        if (!$locale instanceof Alaress_Date_Locale_Interface) {
            // @codeCoverageIgnoreStart
            $locale = new Alaress_Date_Locale_Config();
            // @codeCoverageIgnoreEnd
        }
        // Zero out unset parts - IMO that's the least surprising behavior
        return new static($dateTime, $locale->localFormat().'|', $timezone, $locale);
    }

    /**
     * Static constructor: creates a new date, with the format based on
     * $locale->localShortFormat().
     *
     * @param string                        $dateTime
     * @param DateTimeZone                  $timezone Optional timezone
     * @param Alaress_Date_Locale_Interface $locale   Optional locale, falls back to
     *                                                new Alaress_Date_Locale_Config()
     *
     * @return static
     */
    public static function newLocalShort(
        $dateTime,
        DateTimeZone $timezone = null,
        Alaress_Date_Locale_Interface $locale = null
    ) {
        if (!$locale instanceof Alaress_Date_Locale_Interface) {
            // @codeCoverageIgnoreStart
            $locale = new Alaress_Date_Locale_Config();
            // @codeCoverageIgnoreEnd
        }
        // Zero out unset parts - IMO that's the least surprising behavior.
        // If I create a date with no time, I'd expect its time to be 00:00:00
        return new static($dateTime, $locale->localShortFormat().'|', $timezone, $locale);
    }

    /**
     * Static constructor: creates a new date, with the format based on URL_FORMAT.
     *
     * @param string                        $dateTime
     * @param DateTimeZone                  $timezone Optional timezone
     * @param Alaress_Date_Locale_Interface $locale   Optional locale, falls back to
     *                                                new Alaress_Date_Locale_Config()
     *
     * @return static
     */
    public static function newUrlDate(
        $dateTime,
        DateTimeZone $timezone = null,
        Alaress_Date_Locale_Interface $locale = null
    ) {
        if (!$locale instanceof Alaress_Date_Locale_Interface) {
            // @codeCoverageIgnoreStart
            $locale = new Alaress_Date_Locale_Config();
            // @codeCoverageIgnoreEnd
        }
        // Zero out unset parts - IMO that's the least surprising behavior.
        return new static($dateTime, static::URL_FORMAT.'|', $timezone, $locale);
    }

    /**
     * Static constructor: creates a new date, with the format based on DATABASE_FORMAT.
     *
     * @param string                        $dateTime
     * @param DateTimeZone                  $timezone Optional timezone
     * @param Alaress_Date_Locale_Interface $locale   Optional locale, falls back to
     *                                                new Alaress_Date_Locale_Config()
     *
     * @return static
     */
    public static function newDatabaseDate(
        $dateTime,
        DateTimeZone $timezone = null,
        Alaress_Date_Locale_Interface $locale = null
    ) {
        if (!$locale instanceof Alaress_Date_Locale_Interface) {
            // @codeCoverageIgnoreStart
            $locale = new Alaress_Date_Locale_Config();
            // @codeCoverageIgnoreEnd
        }

        // PDO_SQLSRV returns the time with milliseconds on the end- (e.g. "2017-03-10 17:53:50.000")
        // this tries to work out which format ot use based on string length. Otherwise date function
        // complains about trailing data.

        if (strlen($dateTime) > 19) {
            // Example: '2017-03-10 17:53:50.000'
            $format = static::DATABASE_FORMAT_MS;
        } else {

            // Example: '2017-03-10 17:53:50'
            $format = static::DATABASE_FORMAT;
        }

        // Zero out unset parts - IMO that's the least surprising behavior.
        return new static($dateTime, $format.'|', $timezone, $locale);
    }


    /**
     * Static constructor: creates a new date parsed from natural language, if possible
     *
     * @param string $naturalLanguage
     * @throws InvalidArgumentException
     */
    public static function newNaturalDate($naturalLanguage)
    {
        // compensate for natural language
        $parseable = self::denaturalise(strtolower($naturalLanguage));

        $datetime = DateTime::createFromFormat('', $parseable);

        if (!$datetime) {
            try {
                $datetime = new DateTime(str_replace('/','-', $parseable));

            } catch (Exception $e) {
                throw new InvalidArgumentException("Could not parse '$naturalLanguage' into a datetime", $e->getCode(), $e);
            }
        }

        return new static($datetime);
    }


    /**
     * Attempt to normalise natural language into what strtotime() will be able to understand
     *
     * @param string $string
     * @return string
     */
    private static function denaturalise($string)
    {

        // normalise any whitespace
        $string = preg_replace('`\s+`', ' ', trim($string));

        // remove "^in " and " from now$"
        $string = str_replace(['in ', ' from now', ' at ', ' on ', ', '], ' ', $string);

        // replace all word-numbers with number-numbers
        $words = [
            'zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve'
        ];

        $string = str_replace($words, array_keys($words), $string);

        // known limitation: 23:00am fails to parse

        return $string;
    }


    /**
     * Set up datetime data for processing. Choose your strategy here!
     * Choose your locale as well; this would probably come from the Config
     * table rather than have a concrete instantiation
     *
     * @see Alaress_Date_Strategy_Abstract->setFromFormat() for param description
     *
     * @param string|int|object             $dateTime
     * @param string|array                  $dateFormat If array, try each in turn until it works
     * @param DateTimeZone                  $timezone The timezone of the date, or null to use default
     * @param Alaress_Date_Locale_Interface $locale
     * @throws InvalidArgumentException If unable to build date
     */
    public function __construct($dateTime = null, $dateFormat = null, DateTimeZone $timezone = null, $locale = null)
    {
        if($locale instanceof Alaress_Date_Locale_Interface) {
            $this->setLocale($locale);
        }
        else {
            $this->setLocale(new Alaress_Date_Locale_Config());
        }

        $this->setFromFormat($dateTime, $dateFormat, $timezone);
    }

    /**
     * What's the default timezone?
     * It should've been set by date_default_timezone_set.
     * This is static so we only need to create it once per execution
     *
     * @return DateTimeZone
     */
    protected function getDefaultTimezone()
    {
        if(is_null(self::$defaultTimezone)) {
            self::$defaultTimezone = new DateTimeZone(date_default_timezone_get());
        }
        return self::$defaultTimezone;
    }

    /**
     * Attempts to create a Laravel date out of $dateTime.
     *
     * * `empty($dateTime)`: creates as now
     * * `$dateTime instanceof DateTime or Alaress_Date_Format`:
     *   copies $dateTime's time, and timezone if $timezone is null
     *
     * @param mixed        $dateTime
     * @param DateTimeZone $timezone The timezone of the date, or null to use default
     *
     * @return LaravelDate|null
     */
    protected function buildLaravelDate($dateTime = null, DateTimeZone $timezone = null)
    {
        if (!isset($dateTime)) {
            // Nothing set? set it to now
            return LaravelDate::now($timezone ?: $this->getDefaultTimezone());
        }

        if ($dateTime instanceof DateTimeInterface) {
            if ($dateTime instanceof DateTimeImmutable) {
                // compensate for Carbon's inept reliance on \DateTime rather than \DateTimeInterface
                $dateTime = DateTime::createFromFormat('U', $dateTime->getTimestamp());
            }

            // Build out of timestamp and timezone
            if(!is_null($timezone)) {
                $dateTime->setTimezone($timezone);
            }
            return LaravelDate::instance($dateTime);
        }

        if ($dateTime instanceof Alaress_Date_Format) {
            // Build out of date object's timestamp/timezone
            return LaravelDate::createFromTimestamp(
                $dateTime->getDateTimeObject()->getTimestamp(),
                $timezone ?: clone $dateTime->getDateTimeObject()->getTimezone()
            );
        }
        return null;
    }

    /**
     * @param DateTimeInterface|Alaress_Date_Format|string $dateTime
     * @param string|array $inputDateFormat If array: try all of these formats, use first that doesn't fail
     * @param DateTimeZone $timezone The timezone of the date, or null to use default
     *
     * @return void
     * @throws InvalidArgumentException if no $inputDateFormat was set and $dateTime
     *                                  is a string, or if $dateTime is a string and
     *                                  it can't be parsed in $inputDateFormat
     */
    public function setFromFormat(
        $dateTime,
        $inputDateFormat,
        DateTimeZone $timezone = null
    ) {
        if (is_array($inputDateFormat)) {
            if (empty($inputDateFormat)) {
                $this->setFromFormat($dateTime, null, $timezone);
                return;
            }
            foreach ($inputDateFormat as $format) {
                try {
                    $this->setFromFormat($dateTime, $format, $timezone);
                    return;
                } catch (\Exception $e) {
                    // Suppress this exception, and try the next format
                }
            }
            throw new InvalidArgumentException('$dateTime did not match any $inputDateFormat');
        }

        $laravelDate = $this->buildLaravelDate($dateTime, $timezone);
        if (isset($laravelDate)) {
            $this->setDateTimeObject($laravelDate);
        } else {
            if (!isset($inputDateFormat)) {
                throw new InvalidArgumentException(
                    '$inputDateFormat must be supplied if $dateTime is not a date object'
                );
            }

            $newTimezone = $timezone ?: $this->getDefaultTimezone();
            if ($inputDateFormat === 'U') {
                // Timestamp special case handling - make it work with negative datetimes
                if (is_numeric($dateTime)) {
                    $laravelDate = LaravelDate::createFromTimestamp($dateTime, $newTimezone);
                } else {
                    throw new InvalidArgumentException(
                        '$dateTime must be numeric if $inputDateFormat is timestamp format'
                    );
                }
            } else {
                try {
                    $laravelDate = LaravelDate::createFromFormat($inputDateFormat, $dateTime, $newTimezone);
                } catch (InvalidArgumentException $e) {
                    // I don't have a datetime, try again with trailing data allowed
                    // This can also throw an InvalidArgumentException - allow that to fall on carpet
                    $laravelDate = LaravelDate::createFromFormat($inputDateFormat . '+', $dateTime, $newTimezone);
                }

                $errors = LaravelDate::getLastErrors();
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

                // Did I specify a timezone? If not, set it to the default
                if (!preg_match('/[eOPT]/', $inputDateFormat)) {
                    $laravelDate->setTimezone($newTimezone);
                }
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
        $this->getDateTimeObject()->year($year);
        return $this;
    }

    /**
     * @param $month
     * @return $this
     */
    public function setMonth($month)
    {
        $this->getDateTimeObject()->month($month);
        return $this;
    }

    /**
     * @param $day
     * @return $this
     */
    public function setDay($day)
    {
        $this->getDateTimeObject()->day($day);
        return $this;
    }

    /**
     * Calculates a 24 hour from a 12 hour and a meridiem.
     *
     * @param int    $hour     The hour in 12 hour time
     * @param string $meridiem The associated meridiem: "AM" or "PM"
     *
     * @return int|null The hour, or null if calculation failed.
     *                  Note this can return int(0) - you need to check whether
     *                  the result isset(), a !empty() or truthy check is not
     *                  sufficient.
     */
    protected function calculate24HourFrom12Hour($hour, $meridiem)
    {
        if ($hour < 1 || $hour > 12) {
            trigger_error(
                sprintf('hour "%s" must be between 1 and 12', $hour),
                E_USER_WARNING
            );
            return null;
        }
        switch (strtoupper($meridiem)) {
            case self::AM:
                // 12 AM: 0
                //  1 AM: 1
                // 11 AM: 11
                if ($hour == 12) {
                    $adjustedHour = 0;
                } else {
                    $adjustedHour = $hour;
                }
                break;
            case self::PM:
                // 12 PM: 12
                //  1 PM: 13
                // 11 PM: 23
                if ($hour == 12) {
                    $adjustedHour = $hour;
                } else {
                    $adjustedHour = $hour + 12;
                }
                break;
            default:
                // meridiem is wrong
                trigger_error(
                    sprintf('meridiem "%s" must be "AM" or "PM"', $meridiem),
                    E_USER_WARNING
                );
                return null;
        }
        return $adjustedHour;
    }

    /**
     * Sets a 12 hour time.
     *
     * @param int    $hour     The hour to set in 12 hour time
     * @param string $meridiem The associated meridiem: "AM" or "PM"
     *
     * @return $this
     */
    public function set12Hour($hour, $meridiem)
    {
        $adjustedHour = $this->calculate24HourFrom12Hour($hour, $meridiem);
        if (!isset($adjustedHour)) {
            trigger_error(
                'either hour or meridiem was invalid - not adjusting time',
                E_USER_WARNING
            );
            return $this;
        }
        return $this->setHour($adjustedHour);
    }

    /**
     * @param $hour
     * @return $this
     */
    public function setHour($hour)
    {
        $this->getDateTimeObject()->hour($hour);
        return $this;
    }

    /**
     * @param $minute
     * @return $this
     */
    public function setMinute($minute)
    {
        $this->getDateTimeObject()->minute($minute);
        return $this;
    }

    /**
     * @param $second
     * @return $this
     */
    public function setSecond($second)
    {
        $this->getDateTimeObject()->second($second);
        return $this;
    }


    /**
     * @param DateTimeZone|string $timezone
     * @return $this
     */
    public function setTimezone($timezone)
    {
        $this->getDateTimeObject()->setTimezone($timezone);
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
        $this->getDateTimeObject()->setDate($year, $month, $day);
        return $this;
    }

    /**
     * @param int $hour
     * @param int $min
     * @param int $sec
     * @return $this
     */
    public function setTime($hour, $min, $sec)
    {
        $this->getDateTimeObject()->setTime($hour, $min, $sec);
        return $this;
    }

    /**
     * 12 hour time equivalent of setTime()
     *
     * @param int    $hour
     * @param int    $min
     * @param int    $sec
     * @param string $meridiem
     * @return $this
     */
    public function set12HourTime($hour, $min, $sec, $meridiem)
    {
        $adjustedHour = $this->calculate24HourFrom12Hour($hour, $meridiem);
        if (!isset($adjustedHour)) {
            trigger_error(
                'either hour or meridiem was invalid - not adjusting time',
                E_USER_WARNING
            );
            return $this;
        }
        $this->getDateTimeObject()->setTime($adjustedHour, $min, $sec);
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
     * Add/subtract weekdays and reinitialise
     *
     * @param  $val
     * @return \Alaress_Date_Format
     */
    public function addWeekdays($val)
    {
        $this->getDateTimeObject()->addWeekdays($val);
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
    public function addYears($val)
    {
        $this->getDateTimeObject()->addYears($val);
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
     * 1975-12-25T14:15:16-05:00
     *
     * @return string
     */
    public function toIso8601String()
    {
        // Carbon's implementation of toIso8601String() is wrong.
        // It uses 'Y-m-d\TH:i:sO', should be 'Y-m-d\TH:i:sP';
        return $this->getUsingFormat('Y-m-d\TH:i:sP');
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
     * 1975-12-25T14:15:16-05:00
     *
     * @return string
     */
    public function toRfc3339String()
    {
        //
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
     * Equivalent of `DateTime->modify()`.
     *
     * @see http://php.net/manual/en/datetime.modify.php
     * @param string $modify
     * @return $this
     */
    public function modify($modify)
    {
        $this->getDateTimeObject()->modify($modify);
        return $this;
    }

    // ************************* Formatting *************************

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
        return $this->getUsingFormat($this->getLocale()->longFormat());
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
     * Use short format
     *
     * @return string
     */
    public function getShortDateOnly()
    {
        return $this->getUsingFormat($this->getLocale()->shortDateOnlyFormat());
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
     * Get the time only
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
     * Transform the datetime into a JSON schema date-time (RFC3339) string.
     *
     * @return string
     */
    public function getJson(): string
    {
        return $this->toRfc3339String();
    }

    /**
     * Transform the datetime into a unix timestamp
     *
     * @return int
     */
    public function getUnixTimeStamp(): int
    {
        return (int)$this->getUsingFormat(self::TIMESTAMP);
    }

    /**
     * Format used when passing dates around on the URL
     * @return string
     */
    public function getURLDate(){
        return $this->getUsingFormat(self::URL_FORMAT);
    }


    /**
     * Extract the year of this date
     *
     * @return int
     */
    public function getYear()
    {
        // TODO this is returning a string, not an int. Need to add cast here and remove cast from usages if appropriate.
        return $this->getUsingFormat('Y');
    }

    /**
     * Extract the month of this date.
     *
     * @param bool $leadingZero True if leading zero is fetched, false if not
     *                          Defaults to false.
     *
     * @return int
     */
    public function getMonth($leadingZero = false)
    {
        return $this->getUsingFormat($leadingZero ? 'm' : 'n');
    }

    /**
     * Extract the day of this date.
     *
     * @param bool $leadingZero True if leading zero is fetched, false if not
     *                          Defaults to false.
     *
     * @return int
     */
    public function getDay($leadingZero = false)
    {
        return $this->getUsingFormat($leadingZero ? 'd' : 'j');
    }

    /**
     * Gets the year half of this date.
     *
     * * Jan-Jun: 1
     * * Jul-Dec: 2
     *
     * @return int
     */
    public function getYearHalf()
    {
        return $this->getUsingFormat('m') <= 6 ? 1 : 2;
    }

    /**
     * Extract the numeric representation of the day of the week, starting at 1
     * for Monday, through to 7 for Sunday.
     *
     * @return Integer
     */
    public function getWeekDayNumber()
    {
        return $this->getUsingFormat('N');
    }

    /**
     * Extracts the 12-hour time of this date.
     *
     * @param bool $leadingZero True if leading zero is fetched, false if not
     *                          Defaults to false.
     *
     * @return int
     */
    public function get12Hour($leadingZero = false)
    {
        return $this->getUsingFormat($leadingZero ? 'h' : 'g');
    }

    /**
     * Extracts the 24-hour time of this date.
     *
     * @param bool $leadingZero True if leading zero is fetched, false if not
     *                          Defaults to false.
     *
     * @return int
     */
    public function getHour($leadingZero = false)
    {
        return $this->getUsingFormat($leadingZero ? 'H' : 'G');
    }


    /**
     * Alias of `$this->getHour()`.
     *
     * @see $this->getHour()
     * @param bool $leadingZero
     * @return int
     */
    public function get24Hour($leadingZero = false)
    {
        return $this->getHour($leadingZero);
    }

    /**
     * Extracts the minute of this date.
     *
     * @param bool $leadingZero True if leading zero is fetched, false if not
     *                          Defaults to false.
     *
     * @return int
     */
    public function getMinute($leadingZero = false)
    {
        $result = $this->getUsingFormat('i');
        if (!$leadingZero) {
            return $this->stripLeadingZero($result);
        }
        return $result;
    }

    /**
     * Extracts the second part of this date.
     *
     * @param bool $leadingZero True if leading zero is fetched, false if not
     *                          Defaults to false.
     *
     * @return int
     */
    public function getSecond($leadingZero = false)
    {
        $result = $this->getUsingFormat('s');
        if (!$leadingZero) {
            return $this->stripLeadingZero($result);
        }
        return $result;
    }

    /**
     * @return DateTimeZone
     */
    public function getTimezone()
    {
        return $this->getDateTimeObject()->getTimezone();
    }

    /**
     * Strips a leading zero - used by getMinute() and getSecond() as there's
     * no format for minute and second with no leading zero.
     *
     * @param string $result
     *
     * @return string
     */
    protected function stripLeadingZero($result)
    {
        return preg_replace('/^0/', '', $result);
    }

    /**
     * Extracts the meridiem of this date.
     *
     * @return string
     */
    public function getMeridiem()
    {
        return $this->getUsingFormat('A');
    }

    /**
     * Is this date an AM time?
     *
     * @return bool
     */
    public function isAM()
    {
        // "AM" or "PM" could be locale sensitive, so determine mathematically
        // 0:00  - 11:59: AM
        // 12:00 - 23:59: PM
        return $this->get24Hour() < 12;
    }

    /**
     * Is this date a PM time?
     *
     * @return bool
     */
    public function isPM()
    {
        // Is this locale sensitive?
        return !$this->isAM();
    }

    /**
     * Is this date the given meridiem?
     *
     * @param string $meridiem self::AM or self::PM or "AM" or "PM"
     *
     * @return bool
     * @throws InvalidArgumentException if $meridiem isn't valid
     */
    public function isMeridiem($meridiem)
    {
        switch (strtoupper($meridiem)) {
            case self::AM:
                return $this->isAM();
            case self::PM:
                return $this->isPM();
            default:
                throw new InvalidArgumentException('$meridiem must be "AM" or "PM"');
        }
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
     * @return string
     */
    public function getDatePicker(): string
    {
        return $this->getUsingFormat(self::FORMAT_DATE_PICKER);
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
     * Sets the datetime object in this wrapper
     *
     * @param LaravelDate $dateObject
     */
    protected function setDateTimeObject($dateObject)
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

    /**
     * This isn't a null date
     *
     * @return false
     */
    public function isNull()
    {
        return false;
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
     * @param Alaress_Date_Format $second
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
        //Set weekend from localisation, should be done on construction but locale is only reliable on use
        $this->getDateTimeObject()->setWeekendDays($this->getLocale()->weekendDays());
        return $this->getDateTimeObject()->isWeekday();
    }

    /**
     * @return bool
     */
    public function isWeekend()
    {
        //Set weekend from localisation, should be done on construction but locale is only reliable on use
        $this->getDateTimeObject()->setWeekendDays($this->getLocale()->weekendDays());
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
     * Is this date object's date component in the future?
     *
     * @return bool
     */
    public function isFutureDate()
    {
        if ($this->isToday()) {
            return false;
        }
        return $this->isFuture();
    }

    /**
     * @return bool
     */
    public function isPast()
    {
        return $this->getDateTimeObject()->isPast();

    }

    /**
     * Is this date object's date component in the past?
     *
     * @return bool
     */
    public function isPastDate()
    {
        if ($this->isToday()) {
            return false;
        }
        return $this->isPast();
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
     * @return int -1 if $this < $other, 0 if $this == $other, +1 if $this > $other
     */
    public function compare($other)
    {
        if ($this->lt($other)) {
            return -1;
        }
        if ($this->gt($other)) {
            return 1;
        }
        return 0;
    }

    /**
     * Compare two Alaress_Date_Format objects by a specified format
     *
     * @param Alaress_Date_Format $other
     * @param string $format
     * @return int
     */
    private function compareByFormat(Alaress_Date_Format $other, $format)
    {
        $thisDate  = $this->getUsingFormat($format);
        $otherDate = $other->getUsingFormat($format);
        if ($thisDate === $otherDate) {
            return 0;
        }
        if ($thisDate < $otherDate) {
            return -1;
        }
        if ($thisDate > $otherDate) {
            return 1;
        }
        return 0;
    }

    /**
     * Compares dates of two Alaress_Date_Format objects, ignoring the time
     * and timezone components completely.
     *
     * @param Alaress_Date_Format $other
     *
     * @return int -1 if $this < $other, 0 if $this == $other, +1 if $this > $other
     */
    public function compareDateOnly(Alaress_Date_Format $other)
    {
        return $this->compareByFormat($other, 'Ymd');
    }

    /**
     * Compare the year of two Alaress_Date_Format objects.
     *
     * @param Alaress_Date_Format $other
     * @return int
     */
    public function compareYearOnly(Alaress_Date_Format $other)
    {
        return $this->compareByFormat($other, 'Y');
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

    /** DIFF */


    /**
     * @param Alaress_Date_Format $diff
     * @param bool                $absolute
     * @return int
     */
    public function diffInYears(Alaress_Date_Format $diff, $absolute = true)
    {
        return $this->getDateTimeObject()->diffInYears($diff->getDateTimeObject(), $absolute);
    }


    /**
     * @param Alaress_Date_Format $diff
     * @param bool                $absolute
     * @return int
     */
    public function diffInMonths(Alaress_Date_Format $diff, $absolute = true)
    {
        return $this->getDateTimeObject()->diffInMonths($diff->getDateTimeObject(), $absolute);
    }


    /**
     * @param Alaress_Date_Format $diff
     * @param bool                $absolute
     * @return int
     */
    public function diffInWeeks(Alaress_Date_Format $diff, $absolute = true)
    {
        return $this->getDateTimeObject()->diffInWeeks($diff->getDateTimeObject(), $absolute);
    }


    /**
     * @param Alaress_Date_Format $diff
     * @param bool                $absolute
     * @return int
     */
    public function diffInDays(Alaress_Date_Format $diff, $absolute = true)
    {
        return $this->getDateTimeObject()->diffInDays($diff->getDateTimeObject(), $absolute);
    }


    /**
     * @param Alaress_Date_Format $diff
     * @param bool                $absolute
     * @return int
     */
    public function diffInWeekdays(Alaress_Date_Format $diff, $absolute = true)
    {
        return $this->getDateTimeObject()->diffInWeekdays($diff->getDateTimeObject(), $absolute);
    }


    /**
     * @param Alaress_Date_Format $diff
     * @param bool                $absolute
     * @return int
     */
    public function diffInWeekendDays(Alaress_Date_Format $diff, $absolute = true)
    {
        return $this->getDateTimeObject()->diffInWeekendDays($diff->getDateTimeObject(), $absolute);
    }


    /**
     * @param Alaress_Date_Format $diff
     * @param bool                $absolute
     * @return int
     */
    public function diffInHours(Alaress_Date_Format $diff, $absolute = true)
    {
        return $this->getDateTimeObject()->diffInHours($diff->getDateTimeObject(), $absolute);
    }


    /**
     * @param Alaress_Date_Format $diff
     * @param bool                $absolute
     * @return int
     */
    public function diffInMinutes(Alaress_Date_Format $diff, $absolute = true)
    {
        return $this->getDateTimeObject()->diffInMinutes($diff->getDateTimeObject(), $absolute);
    }


    /**
     * @param Alaress_Date_Format $diff
     * @param bool                $absolute
     * @return int
     */
    public function diffInSeconds(Alaress_Date_Format $diff, $absolute = true)
    {
        return $this->getDateTimeObject()->diffInSeconds($diff->getDateTimeObject(), $absolute);

    }


    /**
     * {@inheritdoc}
     */
    public function getRelativeTime($now = null, $stripModifiers = false)
    {
        // I can't test this method accurately by comparing with now, as that
        // can and will change through the test.
        if (!isset($now)) {
            // @codeCoverageIgnoreStart
            return $this->getDateTimeObject()->diffForHumans(null, $stripModifiers);
            // @codeCoverageIgnoreEnd
        }
        // Convert now to a carbon object, if I can, otherwise assume now
        return $this->getDateTimeObject()->diffForHumans($this->buildLaravelDate($now), $stripModifiers);
    }

    /**
     * Get a relative day and time
     * e.g today at 2:00pm, tomorrow at 8:00am, Saturday at 3:30pm (if within a week)
     * Defaults back to short date if date is a week or further from $now
     *
     * @param Alaress_Date_Format $now
     * @return string
     */
    public function getRelativeDay(Alaress_Date_Format $now = null)
    {
        if (!$now instanceof Alaress_Date_Format) {
            $now = Alaress_Date_Format::now();
        }

        $nowFloored = clone $now;
        $nowFloored->startOfDay();

        $thisFloored = clone $this;
        $thisFloored->startOfDay();

        $diffInDays = $thisFloored->diffInDays($nowFloored);

        if ($this->isSameDay($now)) {
            $relativeTime = 'today';
        } elseif ($this->isTomorrow()) {
            $relativeTime = 'tomorrow';
        } elseif ($this->isYesterday()) {
            $relativeTime = 'yesterday';
        } elseif ($diffInDays < 7) {

            $relativeTime = '';

            if ($this->isPast()) {
                $relativeTime = 'last ';
            }

            $relativeTime .= $this->getUsingFormat('l');

        } else {
            // TODO: Use getDateOnly here and only include year when not the current year
            $relativeTime = $this->getShortDateOnly();
        }

        // Add the time if the date is within 30 days and isn't 23:59:59
        if ($diffInDays <= 30) {
            $relativeTime .= ' at ' . $this->getTimeOnly();
        }

        return $relativeTime;
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
        return $this->toIso8601String();
    }

    /**
     * Implements the clone method for this object, by cloning internal datetime object aswell
     */
    public function __clone() {
        $this->datetime = clone $this->datetime;
    }


    /**
     * @return array
     */
    public function getDefaultJsonMap(): array
    {
        return [
            'database',
            'unixTimestamp',
            'local',
            'json',
        ];
    }

}
