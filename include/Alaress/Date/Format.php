<?php
/**
 * Alaress_Date_Format
 *
 * Facade class. This facilitates a layer of abstraction so you can switch in
 * and out of date processing classes via the $strategy class variable.
 *
 * Uses the Alaress_Date_Format_Interface to enforce method definition across
 * all strategy implementations.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Alaress_Date_Format implements Alaress_Date_Format_Interface, Alaress_Validator_ValidatableInterface {

	/**
	 * @var Alaress_Date_Format_Interface
	 */
	protected $strategy;

	/**
	 * Ported properties from Alaress_Date_Relative
	 */

	/**
	 * @var string
	 */
	private $prefix = 'on';

	/**
	 * @var string
	 */
	private $suffix = 'ago';

	/**
	 * @var int
	 */
	private $now = 0;

	/**
	 * @var array
	 */
	private $intervals = array(
		array(60, 'second'),
		array(60, 'minute'),
		array(24, 'hour'),
		array(7,  'day'),
		array(4.38,  'week'),
		array(12, 'month'),
	);


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
	 * @param string        $dateFormat
	 * @param DateTimeZone  $timezone   The timezone of the date, or null to use default
	 */
	public function __construct($dateTime = null, $dateFormat = null, DateTimeZone $timezone = null) {
		if (!($timezone instanceof DateTimeZone)) {
			$timezone = new DateTimeZone(date_default_timezone_get());
		}
		$strategy = new Alaress_Date_Strategy_LaravelDate($timezone);
		$strategy->setFromFormat($dateTime, $dateFormat);
		$strategy->setLocale(new Alaress_Date_Locale_Australian());

		$this->setStrategy($strategy);
	}

	public function setFromFormat($datetime, $format) {
		$this->getStrategy()->setFromFormat($datetime, $format);
	}

	/**
	 * Add/subtract minutes and reinitialise
	 *
	 * @param $val
	 * @return \Alaress_Date_Format
	 */
	public function addMinutes($val) {
		$time = $this->getStrategy()->getUnixTimestamp() + (60*$val);

		$this->getStrategy()->setFromFormat($time, 'U');
		
		return $this;
	}

	/**
	 * Add/subtract hours and reinitialise
	 *
	 * @param $val
	 * @return \Alaress_Date_Format
	 */
	public function addHours($val) {
		$time = $this->getStrategy()->getUnixTimestamp() + (60*60*$val);

		$this->getStrategy()->setFromFormat($time, 'U');
		
		return $this;
	}

	/**
	 * Add/subtract days and reinitialise
	 *
	 * @param  $val
	 * @return \Alaress_Date_Format
	 */
	public function addDays($val) {
		$time = $this->getStrategy()->getUnixTimestamp() + (60*60*24*$val);

		$this->getStrategy()->setFromFormat($time, 'U');
		
		return $this;
	}

	/**
	 * Get an local format
	 *
	 * @return string
	 */
	public function getLocal() {
		return $this->getStrategy()->getLocal();
	}

	/**
	 * Get a date-only local format
	 *
	 * @return string
	 */
	public function getLocalShort() {
		return $this->getStrategy()->getLocalShort();
	}

	/**
	 * Get an Australian format
	 *
	 * @return string
	 */
	public function getAustralian() {
		return $this->getStrategy()->getAustralian();
	}

	/**
	 * Delegation method to get a long format
	 *
	 * @return mixed
	 */
	public function getLong() {
		return $this->getStrategy()->getLong();
	}

	/**
	 * Delegation method to get a long format
	 *
	 * @return mixed
	 */
	public function getLongDateOnly() {
		return $this->getStrategy()->getLongDateOnly();
	}

	/**
	 * Delegation method to get a short format
	 *
	 * @return mixed
	 */
	public function getShort() {
		return $this->getStrategy()->getShort();
	}

	/**
	 * Get a date only
	 *
	 * @return string
	 */
	public function getDateOnly() {
		return $this->getStrategy()->getDateOnly();
	}

	/**
	 * Get a time only
	 *
	 * @return string
	 */
	public function getTimeOnly() {
		return $this->getStrategy()->getTimeOnly();
	}

	/**
	 * Delegation method to get a database format
	 *
	 * @return mixed
	 */
	public function getDatabase() {
		return $this->getStrategy()->getDatabase();
	}

	/**
	 * Delegation method to get a database dateformat
	 *
	 * @return
	 */
	public function getDatabaseDate() {
		return $this->getStrategy()->getDatabaseDate();
	}

	/**
	 * Delegation method to get a database timeformat
	 *
	 * @return
	 */
	public function getDatabaseTime() {
		return $this->getStrategy()->getDatabaseTime();
	}

    /**
     * Get a unix timestamp
     *
     * @return string
     */
    public function getUnixTimestamp() {
        return $this->getStrategy()->getUnixTimestamp();
    }

    /**
     * Get numerical representation of the day of the week
     *
     * @return string
     */
    public function getWeekDayNumber () {
        return $this->getStrategy()->getWeekDayNumber();
    }

	/**
	 * Delegation method to get the datetime in an arbitrary format
	 *
	 * @param  $format
	 * @return string
	 */
	public function getUsingFormat($format) {
		return $this->getStrategy()->getUsingFormat($format);
	}

	/**
	 * Gets datetime object out of this wrapper
	 *
	 * @return DateTime
	 */
	public function getDateTimeObject() {
		return $this->getStrategy()->getDateTimeObject();
	}

	/**
	 * Get calendar date format
	 *
	 * @return mixed
	 */
	public function getCalendarDate() {
		return $this->getStrategy()->getCalendarDate();
	}

	/**
	 * Get calendar datetime format
	 *
	 * @return mixed
	 */
	public function getCalendarDateTime() {
		return $this->getStrategy()->getCalendarDateTime();
	}

	/**
	 * Set strategy object
	 *
	 * @param  $val
	 * @return void
	 */
	private function setStrategy($val) {
		$this->strategy = $val;
	}

	/**
	 * Get strategy object
	 *
	 * @return Alaress_Date_Format_Interface
	 */
	private function getStrategy() {
		return $this->strategy;
	}

    /**
     * Returns whether this date is valid, according to the strategy that this date was set up with.
     *
     * @return bool
     */
    public function isValid() {
        $strategy = $this->getStrategy();
		
        if ($strategy instanceof Alaress_Validator_ValidatableInterface) {
            return $strategy->isValid();
        }
		
        return true;
    }

	/**
	 * Compare function for comparing with another date object.
	 *
	 * @param Alaress_Date_Format $other
	 *
	 * @return int
	 */
	public function compare($other) {
		if (!($other instanceof Alaress_Date_Format)) {
			return 0;
		}
		
		return $this->getStrategy()->compare($other->getStrategy());
	}

	/**
	 * Compares dates of two Alaress_Date_Format objects, ignoring the time
	 * component completely.
	 *
	 * @param Alaress_Date_Format $other
	 *
	 * @return int
	 */
	public function compareDateOnly($other) {
		if (!($other instanceof Alaress_Date_Format)) {
			return 0;
		}
		return strcmp($this->getDatabaseDate(), $other->getDatabaseDate());
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
	public function __toString() {
		return $this->getUsingFormat('c');
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
	 * Ported methods from Alaress_Date_Relative
	 */


	/**
	 * Ported from stdfuncs
	 *
	 * @param  $date
	 * @param int $now
	 * @param string $prefix
	 * @param string $suffix
	 * @return string
	 */
	public function getRelativeTime() {
		$currentTime = $this->getNow();

		/* If Now is zero, assume you want to get the period between the event
		 * happening and the current moment in time
		 */
		if (!$currentTime) {
			$currentTime = time();
		}

		/* Provide return string
		 */
		$diff = $currentTime - $this->getUnixTimestamp();

		if($diff > 0) {
			return $this->getRelativePast($diff);
		}
		elseif($diff < 0) {
			return $this->getRelativeFuture($diff);
		}
		else {
			return 'just then';
		}

	}


	/**
	 * Output a relative string from the past
	 * @param $diff
	 * @return string
	 */
	private function getRelativePast($diff) {
		for($i = 0; $i < count($this->intervals); ++$i) {
			if ($diff < $this->intervals[$i][0]) {
				return sprintf('%s %s%s %s', $diff, $this->intervals[$i][1], $this->plural($diff), $this->getSuffix());
			}
			$diff = round($diff / $this->intervals[$i][0]);

			// exceptional cases
			if($i == 2) {
				if ($diff < 2) {
					return 'Yesterday';
				}
			}
			elseif($i == 3) {
				if ($diff < 2) {
					return 'Last week';
				}
			}
		}

		// fall back to full format
		return $this->getPrefix() . ' ' . $this->getDateOnly();
	}

	/**
	 * Output a relative time in the future
	 * @param $diff
	 * @return string
	 */
	private function getRelativeFuture($diff) {
		// ensure positivity
		if($diff < 0) {
			$diff = 0 - $diff;
		}

		for($i = 0; $i < count($this->intervals); ++$i) {
			if ($diff < $this->intervals[$i][0]) {
				return sprintf('in %s %s%s', $diff, $this->intervals[$i][1], $this->plural($diff));
			}
			$diff = round($diff / $this->intervals[$i][0]);

			// exceptional cases
			if($i == 2) {
				if ($diff < 2) {
					return 'Tomorrow';
				}
				elseif($diff < 7) {
					$dow = $this->getUsingFormat('l');
					return 'on ' . $dow;
				}
				elseif($diff < 14) {
					$dow = $this->getUsingFormat('l');
					return 'next ' . $dow;
				}
			}
		}

		// fall back to full format
		return $this->getPrefix() . ' ' . $this->getDateOnly();
	}

	/**
	 * Pluralise something. Used in getRelativeTime()
	 *
	 * @param  $num
	 * @return string
	 */
	private function plural($num) {
		if ($num != 1) {
			return 's';
		}

		return '';
	}

	/**
	 * Set prefix
	 * Support chainable settings
	 *
	 * @param  $val
	 * @return $this
	 */
	public function setPrefix($val) {
		$this->prefix = $val;

		return $this;
	}

	/**
	 * Get prefix
	 *
	 * @return string
	 */
	private function getPrefix() {
		return $this->prefix;
	}

	/**
	 * Set suffix
	 * Support chainable settings
	 *
	 * @param  $val
	 * @return $this
	 */
	public function setSuffix($val) {
		$this->suffix = $val;

		return $this;
	}

	/**
	 * Get suffix
	 *
	 * @return string
	 */
	private function getSuffix() {
		return $this->suffix;
	}

	/**
	 * Set Now
	 * Support chainable settings
	 *
	 * @param  $val
	 * @return $this
	 */
	public function setNow($val) {
		$this->now = $val;

		return $this;
	}

	/**
	 * Get Now
	 *
	 * @return int
	 */
	public function getNow() {
		return $this->now;
	}
}