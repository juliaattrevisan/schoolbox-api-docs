<?php
/**
 * Facade class. This facilitates a layer of abstraction so you can switch in
 * and out of date processing classes via the $strategy class variable.
 *
 * Uses the Alaress_Date_Interface to enforce method definition across all
 * strategy implementations.
 */
class Alaress_Date_Format implements Alaress_Date_Interface {

	/**
	 * Set up datetime data for processing. Choose your strategy here!
	 *
	 * @param  $dateTime
	 */
	public function __construct($dateTime = null, $dateFormat = null) {
		if ($dateTime !== null) {
			$this->strategy = new Alaress_Date_V52($dateTime, $dateFormat);
		}
	}

	/**
	 * Get a unix timestamp
	 *
	 * @return string
	 */
	public function getUnixTimestamp() {
		return $this->strategy->getUnixTimestamp();
	}

	/**
	 * Get a database format
	 *
	 * @return string
	 */
	public function getDatabase() {
		return $this->strategy->getDatabase();
	}

	/**
	 * Get a date only
	 *
	 * @return string
	 */
	public function getDateOnly() {
		return $this->strategy->getDateOnly();
	}

	/**
	 * Get an Australian format
	 *
	 * @return string
	 */
	public function getAustralian() {
		return $this->strategy->getAustralian();
	}

	/**
	 * Get an American format
	 *
	 * @return string
	 */
	public function getAmerican() {
		return $this->strategy->getAmerican();
	}
}