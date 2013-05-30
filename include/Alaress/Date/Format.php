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
	 * Set up datetime data for processing. Choose your strategy here!
	 * Choose your locale as well; this would probably come from the Config
	 * table rather than have a concrete instantiation
	 *
	 * @param  $dateTime
	 * @param $dateFormat
	 */
	public function __construct($dateTime = null, $dateFormat) {
		$strategy = new Alaress_Date_Strategy_V52();
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
}