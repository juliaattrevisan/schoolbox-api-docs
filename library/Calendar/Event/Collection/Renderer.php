<?php
/**
 * Calendar_Event_Collection_Renderer
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Event_Collection_Renderer {
	private $interfaceClass = 'Calendar_Api_Interface';

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
		$this->setCurrentDay($currentDay);
		$this->setPage($page);

		$this->setAllEvents(new Calendar_Event_Collection());

		$this->collectAllEvents();
	}

	/**
	 * Collects all events from everywhere, for the current day
	 *
	 * Do this by using ReflectionClass on the API Interface to
	 * get all the mandatory methods
	 */
	private function collectAllEvents() {
		$reflection = new ReflectionClass($this->getInterfaceClass());

		foreach ($reflection->getMethods() as $currentMethod) {
			$methodName = $currentMethod->getName();

			$eventList = $this->getApi()->$methodName($this->getCurrentDay());

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
	 * @param Calendar_Event_Collection $allEvents
	 */
	private function setAllEvents($allEvents) {
		$this->allEvents = $allEvents;
	}

	/**
	 * Get all events
	 *
	 * @return Calendar_Event_Collection
	 */
	private function getAllEvents() {
		return $this->allEvents;
	}

	/**
	 * The interface which defines accessible methods
	 *
	 * @return string
	 */
	public function getInterfaceClass() {
		return $this->interfaceClass;
	}
}
