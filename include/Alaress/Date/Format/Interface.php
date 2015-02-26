<?php
/**
 * Alaress_Date_Interface
 *
 * Methods used in your app for Alaress_Date_Format
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
interface Alaress_Date_Format_Interface extends IComparable {
	/**
	 * Creates the date format object from a given datetime and format.
	 *
	 * @param $datetime
	 * @param $format
	 *
	 * @return mixed
	 */
	public function setFromFormat($datetime, $format);

	public function getUnixTimestamp();
	public function getDatabase();
	public function getLong();
	public function getShort();
	public function getDateOnly();
    public function getAustralian();

	public function getLocal();
	public function getLocalShort();
	public function getLongDateOnly();
	public function getTimeOnly();
	public function getDatabaseDate();
	public function getDatabaseTime();
	public function getWeekDayNumber();
	public function getUsingFormat($format);

	public function getCalendarDate();
	public function getCalendarDateTime();

	/**
	 * Gets datetime object out of this object
	 *
	 * @return DateTime
	 */
	public function getDateTimeObject();

	/**
	 * Retrieves this time, formatted as a relative time.
	 *
	 * @param Alaress_Date_Format|DateTime|DateTimeInterface $now
	 *          The time to compare to, or null to default to the current time.
	 * @param bool                                           $stripModifiers
	 *          If true, remove the prefix and suffix from the output, defaults to false.
	 *
	 * @return string
	 */
	public function getRelativeTime($now = null, $stripModifiers = false);
}