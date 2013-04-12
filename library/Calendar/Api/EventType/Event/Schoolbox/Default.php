<?php
/**
 * Calendar_Api_EventType_Event_Schoolbox_Default
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_EventType_Event_Schoolbox_Default extends Calendar_Api_EventType_Abstract {

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
	 * @param $startTimestamp
	 * @param $endTimestamp
	 * @internal param $currentDay
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getEvents($startTimestamp, $endTimestamp) {
		$collection = $this->getCalendarHandler()->getDayEvents($startTimestamp, $endTimestamp);

		$iterator = $collection->createIterator();

		/* Give the calendar_connector id so this event knows where it came from
		 * This allows you to create an event detail screen in Mobile
		 */
		for ($iterator->rewind(); $iterator->valid(); $iterator->next()) {
			$iterator->current()->setConnectorId($this->getConnectorId());
		}

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
