<?php
/**
 * Calendar_Api_EventType_Submission_Schoolbox_Default
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_EventType_Submission_Schoolbox_Default extends Calendar_Api_EventType_Abstract {
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
	 * @param $startTimestamp
	 * @param $endTimestamp
	 * @internal param $currentDay
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getEvents($startTimestamp, $endTimestamp) {
		$collection = $this->getCalendarHandler()->getSubmissionEvents($startTimestamp, $endTimestamp);

		return $collection;
	}

	/**
	 * Find a particular event from this source
	 *
	 * @param $eventId
	 * @return Calendar_Event_Collection
	 */
	public function getEvent($eventId) {
		$collection = new Calendar_Event_Collection();

		/* Fill in your query result set here and populate $collection
		 */

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
