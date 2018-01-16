<?php
/**
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_EventType_Event_Schoolbox_Default extends Calendar_Api_EventType_Abstract {

	/**
	 * A schoobox event handler instance
	 *
	 * @var Calendar
	 */
	private $calendarHandler;

	/**
	 * Constructor
	 *
	 * @param $user
	 * @param $config
	 */
	public function __construct($user, $config) {
        parent::__construct($user);
		$this->setCalendarHandler(new Calendar());
	}

	/**
	 * Get day events from this data source
	 *
	 * @param int $startTimestamp
	 * @param int $endTimestamp
	 *
	 * @return \Calendar\Event\Collection
	 */
	public function getEvents($startTimestamp, $endTimestamp) {
		$collection = $this->getEventsFromCalendarHandler($startTimestamp, $endTimestamp);

		/* Give the calendar_connector id so this event knows where it came from
		 * This allows you to create an event detail screen in Mobile
		 */
		foreach ($collection as $current) {
			$current->setConnectorId($this->getConnectorId());
		}

		return $collection;
	}

	/**
	 * Gets events out of the calendar handler.
	 *
	 * Can be overridden in subclasses - a filter can be set on
	 * Calendar->getDayEvents().
	 *
	 * @param int $startTimestamp
	 * @param int $endTimestamp
	 *
	 * @return \Calendar\Event\Collection
	 */
	protected function getEventsFromCalendarHandler($startTimestamp, $endTimestamp) {
		return $this->getCalendarHandler()->getDayEvents(
			$this->getUser(),
			$startTimestamp,
			$endTimestamp
		);
	}

	/**
	 * Find a particular event from this source
	 * This implementation of getEvent is actually used
	 * (by /www/calendar/ajax/create.php, to display a new event)
	 *
	 * @param $eventId
	 * @return \Calendar\Event\Collection
	 */
	public function getEvent($eventId) {
		return $this->getCalendarHandler()->getDayEvents(
			$this->getUser(),
			null,
			null,
			array('id' => $eventId)
		);
	}

	/* Getters/Setters
	 */

	/**
	 * Set calendar handler
	 *
	 * @param Calendar $calendarHandler
	 */
	public function setCalendarHandler($calendarHandler) {
		$this->calendarHandler = $calendarHandler;
	}

	/**
	 * Get calendar handler
	 *
	 * @return Calendar
	 */
	public function getCalendarHandler() {
		return $this->calendarHandler;
	}
}
