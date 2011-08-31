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
	protected $database;
	protected $long;
	protected $short;
	protected $noTime;
	protected $local;
	protected $timestamp;

	/**
	 * Get a database format
	 *
	 * @return
	 */
	public function databaseFormat() {
		return $this->database;
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
	public function noTimeFormat() {
		return $this->noTime;
	}

	/**
	 * Get the preferred local format
	 *
	 * @return
	 */
	public function localFormat() {
		return $this->local;
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