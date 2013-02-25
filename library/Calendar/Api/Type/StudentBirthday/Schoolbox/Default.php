<?php
/**
 * Calendar_Api_Type_StudentBirthday_Schoolbox_Default
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_Type_StudentBirthday_Schoolbox_Default implements Calendar_Api_Type_Interface {

	/**
	 * A schoobox event handler instance
	 *
	 * @var null
	 */
	private $calendarHandler = null;

	/**
	 * Constructor
	 *
	 * @param $config
	 */
	public function __construct($config) {
		$this->setCalendarHandler(new Calendar());
	}

	/**
	 * Get day events from this data source
	 *
	 * @param $currentDay
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getEvents($currentDay) {
		$collection = new Calendar_Event_Collection();

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
