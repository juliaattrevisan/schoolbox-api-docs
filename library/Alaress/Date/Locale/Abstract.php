<?php
/**
 * Alaress_Date_Locale_Abstract
 *
 * Localisation for date formats used in Alaress_Date_Format and its subclasses
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
	protected $weekendDays;

	/**
	 * Get the preferred local format
	 *
	 * @return string
	 */
	public function localFormat() {
		return $this->local;
	}

	/**
	 * Get the preferred local short format
	 *
	 * @return string
	 */
	public function localShortFormat() {
		return $this->localShort;
	}

	/**
	 * Get a long format
	 *
	 * @return string
	 */
	public function longFormat() {
		return $this->long;
	}

	/**
	 * Get a long date only format
	 *
	 * @return string
	 */
	public function longDateOnlyFormat() {
		return $this->longDateOnly;
	}

	/**
	 * Get a shortened format
	 *
	 * @return string
	 */
	public function shortFormat() {
		return $this->short;
	}

    /**
     * Get a long date only format
     *
     * @return string
     */
    public function shortDateOnlyFormat() {
        return $this->shortDateOnly;
    }

	/**
	 * Get the date-only format
	 *
	 * @return string
	 */
	public function dateOnlyFormat() {
		return $this->dateOnly;
	}

	/**
	 * Get the time-only format
	 *
	 * @return string
	 */
	public function timeOnlyFormat() {
		return $this->timeOnly;
	}

	/**
	 * Get the calendar date format
	 *
	 * @return string
	 */
	public function calendarDate() {
		return $this->calendarDate;
	}

	/**
	 * Get the calendar datetime format
	 *
	 * @return string
	 */
	public function calendarDateTime() {
		return $this->calendarDateTime;
	}

	/**
     * Get the days which are the weekends for this locale
     */
	public function weekendDays()
    {
        return $this->weekendDays;
    }

}
