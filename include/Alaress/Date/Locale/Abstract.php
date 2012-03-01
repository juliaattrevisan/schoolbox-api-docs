<?php
/**
 * Alaress_Date_Locale_Abstract
 *
 * Localisation for date formats used in Alaress_Date_Format and its subclasses
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Alaress_Date_Locale_Abstract implements Alaress_Date_Locale_Interface {
	protected $local;
	protected $localShort;
	protected $long;
	protected $longDateOnly;
	protected $short;
	protected $dateOnly;
	protected $timeOnly;
	protected $database;
	protected $databaseTime;
	protected $timestamp;

	/**
	 * Get the preferred local format
	 *
	 * @return
	 */
	public function localFormat() {
		return $this->local;
	}

	/**
	 * Get the preferred local short format
	 *
	 * @return
	 */
	public function localShortFormat() {
		return $this->localShort;
	}

	/**
	 * Get a long format
	 *
	 * @return
	 */
	public function longFormat() {
		return $this->long;
	}

	/**
	 * Get a long date only format
	 *
	 * @return
	 */
	public function longDateOnlyFormat() {
		return $this->longDateOnly;
	}

	/**
	 * Get a shortened format
	 *
	 * @return
	 */
	public function shortFormat() {
		return $this->short;
	}

	/**
	 * Get the date-only format
	 *
	 * @return
	 */
	public function dateOnlyFormat() {
		return $this->dateOnly;
	}

	/**
	 * Get the time-only format
	 *
	 * @return
	 */
	public function timeOnlyFormat() {
		return $this->timeOnly;
	}

	/**
	 * Get a database format
	 *
	 * @return
	 */
	public function databaseFormat() {
		return $this->database;
	}

	/**
	 * Get a database date format
	 *
	 * @return mixed
	 */
	public function databaseDateFormat() {
		return $this->databaseDate;
	}

	/**
	 * Get a database time format
	 *
	 * @return
	 */
	public function databaseTimeFormat() {
		return $this->databaseTime;
	}

	/**
	 * Get the timestamp format
	 *
	 * @return
	 */
	public function timestampFormat() {
		return $this->timestamp;
	}
}