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
	protected $calendarDate;
	protected $calendarDateTime;

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
	 * Get the calendar date format
	 *
	 * @return mixed
	 */
	public function calendarDate() {
		return $this->calendarDate;
	}

	/**
	 * Get the calendar datetime format
	 *
	 * @return mixed
	 */
	public function calendarDateTime() {
		return $this->calendarDateTime;
	}
}