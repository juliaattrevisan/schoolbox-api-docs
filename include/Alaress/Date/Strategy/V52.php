<?php
/**
 * Alaress_Date_V52
 *
 * Stock-standard way of doing it, for backwards compatibility when not using
 * PHP 5.3 which has that wacky DateTime object.
 *
 * Uses the Alaress_Date_Format_Interface to enforce method definition
 * Localisation for date formats
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Alaress_Date_Strategy_V52 extends Alaress_Date_Strategy_Abstract implements Alaress_Date_Format_Interface {

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
		return $this->getUsingFormat($this->getLocale()->noTimeFormat());
	}

	/**
	 * Get the date only
	 *
	 * @return string
	 */
	public function getTimeOnly() {
		return $this->getUsingFormat($this->getLocale()->timeFormat());
	}

	/**
	 * Transform the datetime into the format specified in the config table
	 * Do some error checking
	 *
	 * @return string
	 */
	public function getDatabase() {
		$result = $this->getUsingFormat($this->getLocale()->databaseFormat());

		$illegal = array(
			'0000-00-00 00:00:00',
			'1970-01-01 10:00:00',
			'-0001-11-30 00:00:00',
		);

		if (in_array($result, $illegal) || strstr($result, '0000')) {
			return '';
		}

		return $result;
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
	 * Transform the datetime into an arbitrary format
	 *
	 * @param  $format
	 * @return string
	 */
	public function getUsingFormat($format) {
		return date($format, strtotime($this->getDateTime()));
	}
}
