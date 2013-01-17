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
abstract class Alaress_Date_Strategy_Abstract {
	private $year = '0000';
	private $month = '00';
	private $day = '00';
	private $hour = '00';
	private $minute = '00';
	private $second = '00';
	private $meridiem = '';
	private $dateSeparator = '-';
	private $inputDateFormat = 'd/m/y';
	private $locale = null;

	public function __construct($dateTime, $inputDateFormat) {

		/* Support for unix timestamp variant by converting it to a db format
		 */
		if ($inputDateFormat == 'U') {
            $this->setByTimestamp($dateTime);
		}
		else if ($inputDateFormat !== null) {
			$this->setInputDateFormat($inputDateFormat);

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
	 * @param  $dateFormat
	 * @return
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
	 * @param  $val
	 * @return void
	 */
	public function setLocale($val) {
		$this->locale = $val;
	}

	/**
	 * Get Locale configuration object
	 *
	 * @return
	 */
	protected function getLocale() {
		return $this->locale;
	}


	/**
	 * Validates whether this date is actually valid.
	 *
	 * For a date to be valid, the year, month, day, hour, minute and second fields must all be valid, and must be
	 * integer values - no passing dates like "12th", "Jun", etc.
	 *
	 * @return bool
	 */
	public function isValid() {
		return $this->isValidYear()
			&& $this->isValidMonth()
			&& $this->isValidDay()
			&& $this->isValidHour()
			&& $this->isValidMinute()
			&& $this->isValidSecond();
	}


	/**
	 * Helper function used by isValid*() to determine if a value is a valid integer or integer-format string.
	 *
	 * @param int $val The value to check.
	 * @param int $min If set, validates that $val is not less than this value.
	 * @param int $max If set, validates that $val is not greater than this value.
	 *
	 * @return bool
	 */
	private function isInteger($val, $min = null, $max = null) {
		$val      = trim($val);
		$min      = intval($min);
		$max      = intval($max);
		$is_valid = preg_match('/^\d+$/', $val) ? true : false;
		if (isset($min)) {
			$is_valid = $is_valid && ($val >= $min);
		}
		if (isset($max)) {
			$is_valid = $is_valid && ($val <= $max);
		}
		return $is_valid;
	}


	/**
	 * Is this date's year valid?
	 *
	 * It just needs to be a valid integer value - there's no bounds checking yet.
	 *
	 * @return bool
	 */
	public function isValidYear() {
		return $this->isInteger($this->getYear());
	}


	/**
	 * Is this date's year valid?
	 *
	 * It just needs to be a valid integer value - there's no bounds checking yet.
	 *
	 * @return bool
	 */
	public function isValidMonth() {
		return $this->isInteger($this->getMonth(), 1, 12);
	}


	/**
	 * Is this date's day valid?
	 *
	 * It needs to be a valid day for the date's month and year.
	 *
	 * @return bool
	 */
	public function isValidDay() {
		$day = $this->getDay();
		return ($this->isInteger($day) && checkdate($this->getMonth(), $day, $this->getYear()));
	}


	/**
	 * Is this date's hour valid?
	 *
	 * Currently checks whether hour is between 0 and 23 - there's no daylight saving checking yet.
	 *
	 * @return bool
	 */
	public function isValidHour() {
		return $this->isInteger($this->getHour(), 0, 23);
	}


	/**
	 * Is this date's minute valid?
	 *
	 * Currently checks whether minute is between 0 and 59.
	 *
	 * @return bool
	 */
	public function isValidMinute() {
		return $this->isInteger($this->getMinute(), 0, 59);
	}


	/**
	 * Is this date's second valid?
	 *
	 * Currently checks whether second is between 0 and 59.
	 *
	 * @return bool
	 */
	public function isValidSecond() {
		return $this->isInteger($this->getSecond(), 0, 59);
	}
}