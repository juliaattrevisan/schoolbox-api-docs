<?php
/**
 * Alaress_Date_Strategy_Abstract
 *
 * Stock-standard way of doing it, for backwards compatibility when not using
 * PHP 5.3 which has that wacky DateTime object.
 *
 * This is the superclass. It could have been merged with Alaress_Date_V52
 * but this keeps things a bit more reusable and easier to read/subclass for
 * other usage
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Alaress_Date_Strategy_Abstract implements Alaress_Date_Format_Interface, Alaress_Validator_ValidatableInterface {
	private $year = '0000';
	private $month = '00';
	private $day = '00';
	private $hour = '00';
	private $minute = '00';
	private $second = '00';
	private $meridiem = '';
	private $dateSeparator = '-';
	private $inputDateFormat = 'd/m/y';

	/**
	 * @var DateTime
	 */
	protected $datetime;

	/**
	 * @var Alaress_Date_Locale_Interface
	 */
	private $locale = null;

	public function setFromFormat($dateTime, $inputDateFormat) {
		if ($dateTime instanceof DateTime) {
			$this->setDateTimeObject($dateTime);
		}

		/* Support for unix timestamp variant by converting it to a db format
		 */
		elseif ($inputDateFormat == 'U') {
            $this->setByTimestamp($dateTime);
		}
		elseif ($inputDateFormat !== null) {
			$this->setInputDateFormat($inputDateFormat);
			$date_object = DateTime::createFromFormat($inputDateFormat, $dateTime);
			if ($date_object instanceof DateTime) {
				$this->setDateTimeObject($date_object);
				return;
			}

            /* Snap out all the bits, exit if no separator could be found (this
             * indicates an illegal dateTime format anyway)
             */

            /* Components of the datetime string.
             *
             * The datetime string follows this format:
             * 14/08/2011 12:50 AM
             *
             * parts[0] is the date
             * parts[1] is the time
             * parts[2] is the meridiem (am/pm)
             */
            $parts = explode(' ', $dateTime);

            for($i = 0; $i < 3; ++$i) {
                if(!isset($parts[$i])) {
                    $parts[$i] = '';
                }
            }

            /* Remember date components
             * Remember time components
             */
            $this->setDateComponents($parts[0]);
            $this->setTimeComponents($parts[1], $parts[2]);
        }
	}

	/**
	 * Get a datetime string from the components
	 * bug in mktime.. https://bugs.php.net/bug.php?id=47806 causes this to return 1999 when year = 0
	 *
	 * @return string
	 */
	protected function getDateTime() {
        if ($this->getYear() + $this->getDay() + $this->getMonth() > 0) {
            return mktime((int)$this->getHour(), (int)$this->getMinute(), (int)$this->getSecond(), (int)$this->getMonth(), (int)$this->getDay(), (int)$this->getYear());
        }
        else {
            return 0;
         }
    }

	/**
	 * Transform the datetime into an arbitrary format
	 *
	 * @param  $format
	 * @return string
	 */
	public function getUsingFormat($format) {
		if ($this->getDateTimeObject() instanceof DateTime) {
			return $this->getDateTimeObject()->format($format);
		}
		return date($format, $this->getDateTime());
	}

	/**
	 * Figure out which date components are located where, based on the
	 * input dateFormat specified. The separator is always $matches[2]
	 *
	 * Permitted formats:
	 *
	 * y-m-d h:i:s
	 * d/m/y h:i:s z
	 * m/d/y h:i
	 * d-m-y h:i:s
	 *
	 * @param  $dateComponent
	 */
	private function setDateComponents($dateComponent) {
		preg_match('/([dym])([\-\/])([dm])[\-\/]([dmy])/', $this->getInputDateFormat(), $matches);

		$partMap = array(
			1 => 0, // First date part
			3 => 1, // Second date part
			4 => 2, // Third date part
		);

		$this->setDateSeparator($matches[2]);

		/* Explode out the date and remember values
		 */
		$dateParts = explode($this->getDateSeparator(), $dateComponent);

		$this->setDay($dateParts[$partMap[array_search('d', $matches)]]);
		$this->setMonth($dateParts[$partMap[array_search('m', $matches)]]);
		$this->setYear($dateParts[$partMap[array_search('y', $matches)]]);
	}

	/**
	 * Remember the time components
	 *
	 * @param  $timeComponent
	 * @param  $meridiemComponent
	 * @return void
	 */
	private function setTimeComponents($timeComponent, $meridiemComponent) {
		$time = explode(':', $timeComponent);

		if (count($time) >= 2) {
			$hour = $time[0];
			$minute = $time[1];
            if(isset($time[2])) {
			    $second = $time[2];
            }

			$meridiem = strtolower($meridiemComponent);

			/* Convert to 24-hour format
			 */
			if ($meridiem == 'pm' && $hour < 12 ) {
				$hour = $hour + 12;
			}
			else if($meridiem == 'am' && $hour == 12) {
				$hour = 0;
			}

			/* Save components
			 */
			$this->setHour($hour);
			$this->setMinute($minute);

			if (isset($second)) {
				$this->setSecond($second);
			}

			if (strlen($meridiemComponent)) {
				$this->setMeridiem($meridiem);
			}
		}
	}

    /**
     * Set date by unix timestamp
     * @param $timestamp
     * @return void
     */
    private function setByTimestamp($timestamp) {
        $this->setDay(date('j', $timestamp));
        $this->setMonth(date('n', $timestamp));
        $this->setYear(date('Y', $timestamp));
        $this->setHour(date('H', $timestamp));
        $this->setMinute(date('i', $timestamp));
        $this->setSecond(date('s', $timestamp));
    }

	/**
	 * Set input date format
	 *
	 * @param  $val
	 * @return void
	 */
	private function setInputDateFormat($val) {
		$this->inputDateFormat = $val;
	}

	/**
	 * Get input date format
	 *
	 * @return string
	 */
	private function getInputDateFormat() {
		return $this->inputDateFormat;
	}

	/**
	 * Set date separator
	 *
	 * @param  $val
	 * @return void
	 */
	private function setDateSeparator($val) {
		$this->dateSeparator = $val;
	}

	/**
	 * Get date separator
	 *
	 * @return null
	 */
	private function getDateSeparator() {
		return $this->dateSeparator;
	}

	/**
	 * Set day
	 *
	 * @param  $val
	 * @return void
	 */
	private function setDay($val) {
		$this->day = $val;
	}

	/**
	 * Get day
	 *
	 * @return null
	 */
	private function getDay() {
		return $this->day;
	}

	/**
	 * Set month
	 *
	 * @param  $val
	 * @return void
	 */
	private function setMonth($val) {
		$this->month = $val;
	}

	/**
	 * Get month
	 *
	 * @return null
	 */
	private function getMonth() {
		return $this->month;
	}

	/**
	 * Set year
	 *
	 * @param  $val
	 * @return void
	 */
	private function setYear($val) {
		$this->year = $val;
	}

	/**
	 * Get year
	 *
	 * @return null
	 */
	private function getYear() {
		return $this->year;
	}

	/**
	 * Set hour
	 *
	 * @param  $val
	 * @return void
	 */
	private function setHour($val) {
		$this->hour = $val;
	}

	/**
	 * Get hour
	 *
	 * @return null
	 */
	private function getHour() {
		return $this->hour;
	}

	/**
	 * Set minute
	 *
	 * @param  $val
	 * @return void
	 */
	private function setMinute($val) {
		$this->minute = $val;
	}

	/**
	 * Get minute
	 *
	 * @return null
	 */
	private function getMinute() {
		return $this->minute;
	}

	/**
	 * Set second
	 *
	 * @param  $val
	 * @return void
	 */
	private function setSecond($val) {
		$this->second = $val;
	}

	/**
	 * Get second
	 *
	 * @return null
	 */
	private function getSecond() {
		return $this->second;
	}

	/**
	 * Set meridiem
	 *
	 * @param  $val
	 * @return void
	 */
	private function setMeridiem($val) {
		$this->meridiem = $val;
	}

	/**
	 * Get meridiem
	 *
	 * @return null
	 */
	private function getMeridiem() {
		return $this->meridiem;
	}

	/**
	 * Set Locale configuration object
	 *
	 * @param  Alaress_Date_Locale_Interface $val
	 * @return void
	 */
	public function setLocale($val) {
		$this->locale = $val;
	}

	/**
	 * Get Locale configuration object
	 *
	 * @return Alaress_Date_Locale_Interface
	 */
	protected function getLocale() {
		return $this->locale;
	}

	protected function getDateTimeObject() {
		return $this->datetime;
	}

	protected function setDateTimeObject($val) {
		$this->datetime = $val;
	}

	/**
	 * Validates whether this date is actually valid.
	 *
	 * @return bool
	 */
	public function isValid() {
		$validator = new Alaress_Validator_Date();
		return $validator->isValid(
			array(
				 'year'   => $this->getYear(),
				 'month'  => $this->getMonth(),
				 'day'    => $this->getDay(),
				 'hour'   => $this->getHour(),
				 'minute' => $this->getMinute(),
				 'second' => $this->getSecond(),
			)
		);
	}


	/**
	 * Transform the datetime into a localised version
	 *
	 * @return string
	 */
	public function getLocal() {
		return $this->getUsingFormat($this->getLocale()->localFormat());
	}

	/**
	 * Transform the datetime into a localised short version
	 *
	 * @return string
	 */
	public function getLocalShort() {
		return $this->getUsingFormat($this->getLocale()->localShortFormat());
	}

	/**
	 * Transform the datetime into an Australianised version
	 *
	 * @return string
	 */
	public function getAustralian() {
		return $this->getLocal();
	}

	/**
	 * Use long format
	 *
	 * @return string
	 */
	public function getLong() {
		return $this->getUsingFormat($this->getLocale()->longFormat());
	}

	/**
	 * Use long format
	 *
	 * @return string
	 */
	public function getLongDateOnly() {
		return $this->getUsingFormat($this->getLocale()->longDateOnlyFormat());
	}

	/**
	 * Use short format
	 *
	 * @return string
	 */
	public function getShort() {
		return $this->getUsingFormat($this->getLocale()->shortFormat());
	}

	/**
	 * Get the date only
	 *
	 * @return string
	 */
	public function getDateOnly() {
		return $this->getUsingFormat($this->getLocale()->dateOnlyFormat());
	}

	/**
	 * Get the date only
	 *
	 * @return string
	 */
	public function getTimeOnly() {
		return $this->getUsingFormat($this->getLocale()->timeOnlyFormat());
	}

	/**
	 * Transform the datetime into the format specified in the config table
	 * Do some error checking
	 *
	 * @return string
	 */
	public function getDatabase() {
		return $this->getUsingFormat($this->getLocale()->databaseFormat());
	}

	/**
	 * Transform the datetime into a database date
	 *
	 * @return string
	 */
	public function getDatabaseDate() {
		return $this->getUsingFormat($this->getLocale()->databaseDateFormat());
	}

	/**
	 * Transform the datetime into a database time
	 *
	 * @return string
	 */
	public function getDatabaseTime() {
		return $this->getUsingFormat($this->getLocale()->databaseTimeFormat());
	}

	/**
	 * Transform the datetime into a unix timestamp
	 *
	 * @return string
	 */
	public function getUnixTimeStamp() {
		return $this->getUsingFormat($this->getLocale()->timestampFormat());
	}

	/**
	 * Extract the numeric representation of the day of the week
	 *
	 * @return Integer
	 */
	public function getWeekDayNumber () {
		return $this->getUsingFormat('N');
	}

	/**
	 * Compare function for comparing with another date object.
	 *
	 * @param Alaress_Date_Format_Interface $other
	 *
	 * @return int
	 */
	public function compare($other) {
		if (!($other instanceof Alaress_Date_Format_Interface)) {
			return 0;
		}
		$this_timestamp  = $this->getUsingFormat('U');
		$other_timestamp = $other->getUsingFormat('U');
		if (!($this_timestamp === $other_timestamp)) {
			return $this_timestamp < $other_timestamp ? -1 : 1;
		}
		return 0;
	}
}