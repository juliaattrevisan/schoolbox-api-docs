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
	public function __construct($dateTime) {
		$this->strategy = new Alaress_Date_V52($dateTime);
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
	 * Get an American format
	 *
	 * @return string
	 */
	public function getAmerican() {
		return $this->strategy->getAmerican();
	}
}