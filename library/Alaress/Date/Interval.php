<?php

/**
 * A date interval class which handles remainders more effectively than
 * DateInterval.
 *
 * It is always created from a number of seconds.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Date;

use DateTime;
use DateInterval;
use JsonSerializable;

class Interval implements JsonSerializable
{

    /**
     * Number of seconds in the interval.
     *
     * @var int
     */
    protected $seconds;

    /**
     * Whether the interval is inverted.
     *
     * @var bool
     */
    protected $invert = false;

    /**
     * Creates an Interval from a number of seconds.
     *
     * @param int $seconds
     */
    public function __construct($seconds)
    {
        if ($seconds < 0) {
            $seconds *= -1;
            $this->invert = true;
        }
        $this->seconds = $seconds;
    }

    /**
     * Creates a Interval object out of a DateInterval object.
     *
     * @param  DateInterval $interval The interval to create from.
     * @return static
     */
    public static function createFromDateInterval(DateInterval $interval)
    {
        $seconds = 0;
        if ($interval->y) {
            trigger_error('DateInterval contains y: assuming 365 days', E_USER_WARNING);
            $seconds += ($interval->y * 365 * 24 * 60 * 60);
        }
        if ($interval->m) {
            trigger_error('DateInterval contains m: assuming 30 days', E_USER_WARNING);
            $seconds += ($interval->m * 30 * 24 * 60 * 60);
        }
        if ($interval->d) {
            $seconds += ($interval->d * 24 * 60 * 60);
        }
        if ($interval->h) {
            $seconds += ($interval->h * 60 * 60);
        }
        if ($interval->i) {
            $seconds += ($interval->i * 60);
        }
        if ($interval->s) {
            $seconds += ($interval->s);
        }
        if ($interval->invert) {
            $seconds *= -1;
        }
        return new static($seconds);
    }

    /**
     * Creates an instance of this class out of a date string.
     *
     * @see    DateInterval::createFromDateString()
     * @param  string $dateString
     * @return static
     */
    public static function createFromDateString($dateString)
    {
        return static::createFromDateInterval(DateInterval::createFromDateString($dateString));
    }

    /**
     * Creates an instance of this class out of the difference between two
     * DateTime objects.
     *
     * @param  DateTime $from
     * @param  DateTime $to
     * @return static
     */
    public static function createFromDateTimeDifference(DateTime $from, DateTime $to)
    {
        return new static($to->getTimestamp() - $from->getTimestamp());
    }

    /**
     * Gets total number of days in this interval
     *
     * @return int
     */
    public function getDayTotal()
    {
        return intval($this->getHourTotal() / 24);
    }

    /**
     * Gets total number of hours in this interval
     *
     * @return int
     */
    public function getHourTotal()
    {
        return intval($this->getMinuteTotal() / 60);
    }

    /**
     * Gets remaining number of hours in this interval, after days are factored
     * out
     *
     * @return int
     */
    public function getHourRemainder()
    {
        return intval($this->getHourTotal() % 24);
    }

    /**
     * Gets total number of minutes in this interval
     *
     * @return int
     */
    public function getMinuteTotal()
    {
        return intval($this->getSecondTotal() / 60);
    }

    /**
     * Gets remaining number of minutes in this interval, after hours and days
     * are factored out
     *
     * @return int
     */
    public function getMinuteRemainder()
    {
        return $this->getMinuteTotal() % 60;
    }

    /**
     * Gets total number of seconds in this interval
     *
     * @return int
     */
    public function getSecondTotal()
    {
        return $this->seconds;
    }

    /**
     * Gets remaining number of seconds in this interval, after hours, days and
     * minutes are factored out
     *
     * @return int
     */
    public function getSecondRemainder()
    {
        return $this->getSecondTotal() % 60;
    }

    public function isInverted()
    {
        return $this->invert;
    }

    /**
     * Formats an instance of this class into a string.
     *
     * Uses the following formats
     * * %%: a literal % character
     * * %D:   day total
     * * %H:   hour total
     * * %02h: hour remainder, zero-padded to 2 chars
     * * %h:   hour remainder, no padding
     * * %I:   minute total
     * * %02i: minute remainder, zero-padded to 2 chars
     * * %i:   minute remainder, no padding
     * * %S:   second total
     * * %02s: second remainder, zero-padded to 2 chars
     * * %s:   second remainder, no padding
     * * %R:   "-" when negative, "+" when positive
     * * %r:   "-" when negative, "" when positive
     *
     * @param  string $format
     * @return string
     */
    public function format($format)
    {
        return strtr(
            $format,
            array(
                '%%'   => '%',
                '%D'   => $this->getDayTotal(),
                '%H'   => $this->getHourTotal(),
                '%02h' => sprintf('%02d', $this->getHourRemainder()),
                '%h'   => $this->getHourRemainder(),
                '%I'   => $this->getMinuteTotal(),
                '%02i' => sprintf('%02d', $this->getMinuteRemainder()),
                '%i'   => $this->getMinuteRemainder(),
                '%S'   => $this->getSecondTotal(),
                '%02s' => sprintf('%02d', $this->getSecondRemainder()),
                '%s'   => $this->getSecondRemainder(),
                '%R'   => ($this->isInverted() ? '-' : '+'),
                '%r'   => ($this->isInverted() ? '-' : ''),
            )
        );
    }


    /**
     * Convert this Interval to the format: (\d+)hours (\d+)min (\d+)sec
     * Primarily used in quiz
     *
     * @param bool $padHours
     * @return string
     */
    public function hms($padHours = false)
    {
        $format = '';

        if ($this->getHourTotal()) {
            // there is at least one whole hour
            $format .= ($padHours ? "%02h" : "%H") . "hrs ";
        }

        if ($this->getHourTotal() || $this->getMinuteRemainder()) {
            $format .= "%02imin ";
        }

        $format .= "%02ssec";

        return $this->format($format);
    }

    /**
     * Creates a DateInterval instance out of this object.
     *
     * You might want this when adjusting an existing date object, because this
     * object doesn't understand how to convert between daylight saving changes.
     *
     * @return DateInterval
     */
    public function getAsDateInterval()
    {
        $interval = new DateInterval($this->format('P%DDT%hH%iM%sS'));
        $interval->invert = $this->invert;
        return $interval;
    }

    /**
     * This class json serializes as a simple integer (the number of seconds in
     * the interval).
     *
     * @return int
     */
    public function jsonSerialize()
    {
        $seconds = $this->getSecondTotal();
        if ($this->isInverted()) {
            $seconds *= -1;
        }
        return intval($seconds);
    }
}
