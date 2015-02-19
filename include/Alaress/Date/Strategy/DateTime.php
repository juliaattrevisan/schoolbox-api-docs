<?php
/**
 * Alaress_Date_Strategy_DateTime
 *
 * DateTime powered Alaress_Date_Format backend, retaining
 * Alaress_Date_Format's warts but ditching the special case date parsing in
 * Alaress_Date_Strategy_Abstract.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Alaress_Date_Strategy_DateTime extends Alaress_Date_Strategy_Abstract
{
    /**
     * Is this a valid datetime?
     *
     * @var bool
     */
    protected $isValid = false;

    /**
     * @var DateTimeZone
     */
    protected $timezone;

    /**
     * Constructor
     *
     * @param DateTimeZone $defaultTimezone The default timezone for this strategy
     */
    public function __construct(DateTimeZone $defaultTimezone)
    {
        $this->timezone = $defaultTimezone;
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
        $this->isValid = true;
        if ($dateTime instanceof DateTime) {
            $newDateTime = DateTime::createFromFormat('U', $dateTime->getTimestamp());
            $newDateTime->setTimezone($dateTime->getTimezone());
            $this->setDateTimeObject($newDateTime);
        } elseif ($dateTime instanceof DateTimeInterface) {
            $newDateTime = DateTime::createFromFormat('U', $dateTime->getTimestamp());
            $newDateTime->setTimezone($dateTime->getTimezone());
            $this->setDateTimeObject($newDateTime);
        } elseif ($dateTime instanceof Alaress_Date_Format_Interface) {
            $newDateTime = DateTime::createFromFormat('U', $dateTime->getDateTimeObject()->getTimestamp());
            $newDateTime->setTimezone($dateTime->getDateTimeObject()->getTimezone());
            $this->setDateTimeObject($newDateTime);
        } else {
            if (!isset($inputDateFormat)) {
                throw new InvalidArgumentException(
                    '$inputDateFormat must be supplied if $dateTime is not a date object'
                );
            }
            // Discard setDateComponents and setTimeComponents
            $dateTime = DateTime::createFromFormat($inputDateFormat, $dateTime);

            // Did I specify a timezone? If not, set it to the default
            if (!preg_match('/[eOPT]/', $inputDateFormat)) {
                $dateTime->setTimezone($this->timezone);
            }

            // Check for errors or warnings
            // Question: how tolerant of bad data do we want to be?
            // On bad data:
            // * Exception? Null date? current date?
            $errors = DateTime::getLastErrors();
            if ($errors['error_count']) {
                // I didn't get a datetime at all.
                $this->isValid = false;
                throw new RuntimeException(
                    sprintf(
                        'DateTime creation failed with %d errors: (%s)',
                        $errors['error_count'],
                        implode(', ', $errors['errors'])
                    )
                );
            }
            if ($errors['warning_count']) {
                // I have a datetime, but it's not what I expected. Warn the user.
                // This date will also fail isValid()
                $this->isValid = false;
                trigger_error(
                    sprintf(
                        'DateTime creation raised %d warnings: (%s)',
                        $errors['warning_count'],
                        implode(', ', $errors['warnings'])
                    ),
                    E_USER_WARNING
                );
            }
            $this->setDateTimeObject($dateTime);
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
