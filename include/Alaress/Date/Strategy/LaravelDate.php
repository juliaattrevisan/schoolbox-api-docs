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
        $this->isValid = true;
        if (!isset($dateTime)) {
            // Nothing set? set it to now
            $newDateTime = LaravelDate::now($this->timezone);
            $this->setDateTimeObject($newDateTime);
        } elseif ($dateTime instanceof DateTime) {
            $newDateTime = LaravelDate::createFromTimestamp($dateTime->getTimestamp(), $this->timezone);
            $this->setDateTimeObject($newDateTime);
        } elseif ($dateTime instanceof DateTimeInterface) {
            $newDateTime = LaravelDate::createFromTimestamp($dateTime->getTimestamp(), $this->timezone);
            $this->setDateTimeObject($newDateTime);
        } elseif ($dateTime instanceof Alaress_Date_Format_Interface) {
            $newDateTime = LaravelDate::createFromTimestamp(
                $dateTime->getDateTimeObject()->getTimestamp(),
                $dateTime->getDateTimeObject()->getTimezone()
            );
            $this->setDateTimeObject($newDateTime);
        } else {
            if (!isset($inputDateFormat)) {
                throw new InvalidArgumentException(
                    '$inputDateFormat must be supplied if $dateTime is not a date object'
                );
            }

            try {
                // LaravelDate throws exceptions on error, but not warning.
                $newDateTime = LaravelDate::createFromFormat($inputDateFormat, $dateTime);
            } catch (InvalidArgumentException $e) {
                // Try again with trailing data allowed.
                // Let exception fall through if it still isn't valid.
                $newDateTime = LaravelDate::createFromFormat($inputDateFormat.'+', $dateTime);
            }

            // Check for warnings, but ignore trailing data warning?
            $errors = LaravelDate::getLastErrors();
            if ($errors['warning_count']) {
                // I have a datetime, but it's not what I expected. Warn the user.
                // This date will also fail isValid()
                $this->isValid = false;
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
                $newDateTime->setTimezone($this->timezone);
            }
            $this->setDateTimeObject($newDateTime);
        }
    }

    /**
     * Validates whether this date is actually valid.
     *
     * @return bool
     */
    public function isValid()
    {
        return $this->isValid;
    }
}
