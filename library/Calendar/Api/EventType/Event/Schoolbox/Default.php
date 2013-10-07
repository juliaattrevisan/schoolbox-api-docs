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
		$this->setUser($user);
		$this->setCalendarHandler(new Calendar());
	}

	/**
	 * Get day events from this data source
	 *
	 * @param int $startTimestamp
	 * @param int $endTimestamp
	 *
	 * @return Calendar_Event_Collection
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
	 * @return Calendar_Event_Collection
	 */
	protected function getEventsFromCalendarHandler($startTimestamp, $endTimestamp) {
		return $this->getCalendarHandler()->getDayEvents($this->getUser(), $startTimestamp, $endTimestamp);
	}

	/**
	 * Find a particular event from this source
	 *
	 * @param $eventId
	 * @return Calendar_Event_Collection
	 */
	public function getEvent($eventId) {
		$collection = new Calendar_Event_Collection();

		$currentEvent = $this->getCalendarHandler()->getByID($eventId);

		/* If an event was found, compose and stack a new event object
		 */
		if (is_array($currentEvent)) {
			$event = new Calendar_Event_Type_Day();

			$event->setAuthorName($currentEvent['firstname'] . ' ' . $currentEvent['lastname']);
			$event->setAuthorId($currentEvent['uid']);
			$event->setEventId($currentEvent['id']);
			$event->setStartTime(new Alaress_Date_Format($currentEvent['eventDate'] . ' ' . $currentEvent['startTime'], 'y-m-d h:i:s'));
			$event->setEndTime(new Alaress_Date_Format($currentEvent['eventDate'] . ' ' . $currentEvent['endTime'], 'y-m-d h:i:s'));
			$event->setType($currentEvent['type']);
			$event->setNote($currentEvent['note']);
			$event->setAccess($currentEvent['access']);
			$event->setEventType($currentEvent['eventType']);
			$event->setColour($currentEvent['eventColour']);
			$event->setCampusList($currentEvent['campusList']);
			$event->setDetail($currentEvent['detail']);
			$event->setLocation($currentEvent['location']);
			$event->setPublishDate($currentEvent['publish_date']);
			$event->setSid($currentEvent['sid']);

			if ($this->getCalendarHandler()->getPerm($currentEvent) > 1) {
				$event->setIsEditable(true);
			}
			else {
				$event->setIsEditable(false);
			}

			if ($currentEvent['access'] == 2) {
				$event->setCategoryId($currentEvent['categoryId']);
				$event->setCategoryname($currentEvent['categoryName']);
			}

			$event->setAttendance($currentEvent['attendance']);
			$event->setCompleted($currentEvent['completed']);

			$collection->add($event);
		}

		return $collection;
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
