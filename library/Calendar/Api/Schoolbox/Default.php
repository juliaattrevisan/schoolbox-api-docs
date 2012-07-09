<?php
/**
 * Calendar_Api_Schoolbox_Default
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_Schoolbox_Default implements Calendar_Api_Interface {
	private $calendarHandler = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setCalendarHandler(new Calendar());
	}

	/**
	 * Get day events from this data source
	 *
	 * @param $currentDay
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getDayEvents($currentDay) {
		$collection = $this->getCalendarHandler()->getDayEvents($currentDay);

		return $collection;
	}

	/**
	 * Get student birthdays from this data source
	 *
	 * @param $currentDay
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getStudentBirthdays($currentDay) {

		/* Note that this uses an MSSQL connection
		 */
		$collection = $this->getCalendarHandler()->getStudentBirthdays($currentDay);

		return $collection;
	}

	/**
	 * Get submission events from this data source
	 *
	 * @param $currentDay
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getSubmissionEvents($currentDay) {
		$collection = $this->getCalendarHandler()->getSubmissionEvents($currentDay);

		return $collection;
	}

	/* Getters/Setters
	 */

	/**
	 * Set calendar handler
	 *
	 * @param $calendarHandler
	 */
	public function setCalendarHandler($calendarHandler) {
		$this->calendarHandler = $calendarHandler;
	}

	/**
	 * Get calendar handler
	 *
	 * @return null
	 */
	public function getCalendarHandler() {
		return $this->calendarHandler;
	}
}
