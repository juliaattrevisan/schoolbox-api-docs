<?php
/**
 * Stock-standard way of doing it, for backwards compatibility when not using
 * PHP 5.3 which has that wacky DateTime object.
 *
 * This is the superclass. It could have been merged with Alaress_Date_V52
 * but this keeps things a bit more reusable and easier to read/subclass for
 * other usage
 */
abstract class Alaress_Date_Abstract {
	private $year = '0000';
	private $month = '00';
	private $day = '00';
	private $hour = '00';
	private $minute = '00';
	private $second = '00';
	private $meridiem = '';
	private $dateSeparator = '-';

	public function __construct($dateTime) {

		/* Snap out all the bits, exit if no separator could be found (this
		 * indicates an illegal dateTime format anyway)
		 */
		if (strstr($dateTime, '-')) {
			$this->setDateSeparator('-');
		}
		else if (strstr($dateTime, '/')) {
			$this->setDateSeparator('/');
		}
		else {
			return false;
		}

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

		/* Remember date components
		 * Remember time components
		 */
		$this->setDateComponents($parts[0]);
		$this->setTimeComponents($parts[1], $parts[2]);
	}

	/**
	 * Get a datetime string from the components
	 *
	 * @return string
	 */
	protected function getDateTime() {
		$date = "{$this->getYear()}{$this->getDateSeparator()}{$this->getMonth()}{$this->getDateSeparator()}{$this->getDay()}";
		$time = "{$this->getHour()}:{$this->getMinute()}:{$this->getSecond()}";

		return $date . ' ' . $time;
	}

	/**
	 * Remember the date components
	 *
	 * @param  $dateComponent
	 * @return void
	 */
	private function setDateComponents($dateComponent) {
		$date = explode($this->getDateSeparator(), $dateComponent);

		/* American format vs normal people format
		 */
		if ($this->getDateSeparator() == '-') {
			if (strlen($date[1])) {
				$this->setDay($date[1]);
			}

			if (strlen($date[0])) {
				$this->setMonth($date[0]);
			}
		}
		else if ($this->getDateSeparator() == '/') {
			if (strlen($date[0])) {
				$this->setDay($date[0]);
			}

			if (strlen($date[1])) {
				$this->setMonth($date[1]);
			}
		}

		/* Year
		 */
		if (strlen($date[2])) {
			$this->setYear($date[2]);
		}
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

		if (count($time) == 2) {
			$hour = $time[0];
			$minute = $time[1];

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
			if (strlen($time[0])) {
				$this->setHour($hour);
			}

			if (strlen($time[1])) {
				$this->setMinute($minute);
			}

			if (strlen($meridiemComponent)) {
				$this->setMeridiem($meridiem);
			}
		}
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
}



