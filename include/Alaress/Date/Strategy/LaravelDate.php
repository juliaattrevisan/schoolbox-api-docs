<?php
/**
 * Alaress_Date_Strategy_LaravelDate
 *
 * LaravelDate powered Alaress_Date_Format backend
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Jenssegers\Date\Date as LaravelDate;

class Alaress_Date_Strategy_LaravelDate extends Alaress_Date_Strategy_DateTime
{
    /**
     * @var LaravelDate $datetime
     */

    /**
     * Any warnings that might have been raised in creation of this object.
     *
     * @var array
     */
    protected $warnings = array();

    /**
     * Attempts to create a Laravel date out of $dateTime.
     *
     * * empty: creates as now
     * * DateTimeInterface or Alaress_Date_Format_Interface: clones from that
     *
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
        } elseif ($dateTime instanceof Alaress_Date_Format_Interface) {
            // Build out of date object's timestamp/timezone
            return LaravelDate::createFromTimestamp(
                $dateTime->getDateTimeObject()->getTimestamp(),
                $dateTime->getDateTimeObject()->getTimezone()
            );
        }
        throw new InvalidArgumentException('Could not create LaravelDate from $dateTime');
    }

    /**
     * @param DateTimeInterface|Alaress_Date_Format_Interface|string $dateTime
     * @param string                                                 $inputDateFormat
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
            $errors      = DateTime::getLastErrors();
            if ($errors['error_count']) {
                // I don't have a datetime, try again with trailing data allowed
                $newDateTime = DateTime::createFromFormat($inputDateFormat.'+', $dateTime);
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
     * Validates whether this date is actually valid.
     *
     * @return bool
     */
    public function isValid()
    {
        return empty($this->warnings);
    }

    /**
     * @return array
     */
    public function getWarnings()
    {
        return $this->warnings;
    }
}
