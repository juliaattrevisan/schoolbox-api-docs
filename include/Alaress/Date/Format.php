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
class Alaress_Date_Format implements Alaress_Date_Format_Interface {

	/**
	 * Set up datetime data for processing. Choose your strategy here!
	 * Choose your locale as well; this would probably come from the Config
	 * table rather than have a concrete instantiation
	 *
	 * @param  $dateTime
	 */
	public function __construct($dateTime = null, $dateFormat = null) {
		if ($dateTime !== null) {
			$strategy = new Alaress_Date_Strategy_V52($dateTime, $dateFormat);
			$strategy->setLocale(new Alaress_Date_Locale_Australian());

			$this->setStrategy($strategy);
		}
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
	 * Get a database format
	 *
	 * @return string
	 */
	public function getDatabase() {
		return $this->getStrategy()->getDatabase();
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
	 * Get an Australian format
	 *
	 * @return string
	 */
	public function getAustralian() {
		return $this->getStrategy()->getAustralian();
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
	 * @return
	 */
	private function getStrategy() {
		return $this->strategy;
	}
}