<?php
/**
 * Calendar_Event_Collection_Renderer_Facade
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Event_Collection_Renderer_Facade {
	private $api = null;
	private $currentDay = -1;
	private $page = null;

	/* Mega-stack Calendar_Event_Collection instance which stores all events
	 */
	private $allEvents = null;

	/**
	 * Constructor
	 *
	 * @param $api
	 * @param $currentDay
	 * @param $page
	 */
	public function __construct($api, $currentDay, $page) {
		$this->setApi($api);
		$this->setPage($page);

		$this->setAllEvents(new Calendar_Event_Collection());

		$this->collectAllEvents($currentDay);
	}

	/**
	 * Collects all events from everywhere, for the current day
	 *
	 * Do this by using ReflectionClass on the API Interface to
	 * get all the mandatory methods
	 *
	 * @param $currentDay
	 */
	private function collectAllEvents($currentDay) {
		$reflection = new ReflectionClass('Calendar_Api_Interface');

		foreach ($reflection->getMethods() as $currentMethod) {
			$methodName = $currentMethod->getName();

			$eventList = $this->getApi()->$methodName($currentDay);

//			if ($methodName == 'getDayEvents') {
//				$x = date('d', $currentDay);
//
//				$duff = new Calendar_Event_Type_Day();
//				$duff->setNote("MALENA Test for sorting");
//				$duff->setPublishDate(date('Y-m-d H:i:s'));
//				$duff->setStartTime(new Alaress_Date_Format("2012-07-$x 09:15:15", 'y-m-d h:i:s'));
//				$duff->setEndTime(new Alaress_Date_Format("2012-07-$x 14:17:17", 'y-m-d h:i:s'));
//
//				$eventList->add($duff);
//
//				$duff = new Calendar_Event_Type_Day();
//				$duff->setNote("SARAH Test for sorting");
//				$duff->setPublishDate(date('Y-m-d H:i:s'));
//				$duff->setStartTime(new Alaress_Date_Format("2012-07-$x 05:15:15", 'y-m-d h:i:s'));
//				$duff->setEndTime(new Alaress_Date_Format("2012-07-$x 07:17:17", 'y-m-d h:i:s'));
//
//				$eventList->add($duff);
//
//				$duff = new Calendar_Event_Type_Day();
//				$duff->setNote("JUSTINE Test for sorting");
//				$duff->setPublishDate(date('Y-m-d H:i:s'));
//				$duff->setStartTime(new Alaress_Date_Format("2012-07-$x 15:15:15", 'y-m-d h:i:s'));
//				$duff->setEndTime(new Alaress_Date_Format("2012-07-$x 15:17:17", 'y-m-d h:i:s'));
//
//				$eventList->add($duff);
//			}

			$this->getAllEvents()->merge($eventList);
		}

		/* Sort all the events in order of time
		 */
		$this->getAllEvents()->sort();
	}

	/**
	 * Render all events
	 */
	public function renderAll() {
		$iterator = $this->getAllEvents()->createIterator();

		$registry = array();

		for ($iterator->rewind(); $iterator->valid(); $iterator->next()) {
			$currentEvent = $iterator->current();

			$rendererClass = $currentEvent->getRendererClass();

			if (!$registry[$rendererClass]) {
				$registry[$rendererClass] = new $rendererClass($this->getPage());
			}

			$currentEvent->accept($registry[$rendererClass]);
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set api
	 *
	 * @param $api
	 */
	private function setApi($api) {
		$this->api = $api;
	}

	/**
	 * Get api
	 *
	 * @return null
	 */
	private function getApi() {
		return $this->api;
	}

	/**
	 * Set current day
	 *
	 * @param $currentDay
	 */
	private function setCurrentDay($currentDay) {
		$this->currentDay = $currentDay;
	}

	/**
	 * Get current day
	 *
	 * @return int
	 */
	private function getCurrentDay() {
		return $this->currentDay;
	}

	/**
	 * Set page
	 *
	 * @param $page
	 */
	private function setPage($page) {
		$this->page = $page;
	}

	/**
	 * Get page
	 *
	 * @return null
	 */
	private function getPage() {
		return $this->page;
	}

	/**
	 * Set all events
	 *
	 * @param $allEvents
	 */
	private function setAllEvents($allEvents) {
		$this->allEvents = $allEvents;
	}

	/**
	 * Get all events
	 *
	 * @return null
	 */
	private function getAllEvents() {
		return $this->allEvents;
	}
}
