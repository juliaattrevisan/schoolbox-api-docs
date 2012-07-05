<?php
/**
 * Calendar_Event_Collection_Renderer_Abstract
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
abstract class Calendar_Event_Collection_Renderer_Abstract {

	/* The connector to data sources
	 */
	protected $api = null;

	/* The day to collect data for
	 */
	protected $currentDay = -1;

	/* The page object to render data on
	 */
	protected $page = null;

	/* Schoolbox libraries to determine permissions and accesslevels
	 */
	protected $calendarHandler = null;
	protected $groupHandler = null;

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

		$this->setGroupHandler(new Group());
		$this->setCalendarHandler(new Calendar());
	}

	abstract public function render();

	/* Getters/Setters
	 */

	/**
	 * Set api
	 *
	 * @param $api
	 */
	protected function setApi($api) {
		$this->api = $api;
	}

	/**
	 * Get api
	 *
	 * @return null
	 */
	protected function getApi() {
		return $this->api;
	}

	/**
	 * Set calendar handler
	 *
	 * @param $calendarHandler
	 */
	protected function setCalendarHandler($calendarHandler) {
		$this->calendarHandler = $calendarHandler;
	}

	/**
	 * Get calendar handler
	 *
	 * @return null
	 */
	protected function getCalendarHandler() {
		return $this->calendarHandler;
	}

	/**
	 * Set group handler
	 *
	 * @param $groupHandler
	 */
	protected function setGroupHandler($groupHandler) {
		$this->groupHandler = $groupHandler;
	}

	/**
	 * Get group handler
	 *
	 * @return null
	 */
	protected function getGroupHandler() {
		return $this->groupHandler;
	}

	/**
	 * Set page
	 *
	 * @param $page
	 */
	protected function setPage($page) {
		$this->page = $page;
	}

	/**
	 * Get page
	 *
	 * @return null
	 */
	protected function getPage() {
		return $this->page;
	}

	/**
	 * Set current day
	 *
	 * @param $currentDay
	 */
	protected function setCurrentDay($currentDay) {
		$this->currentDay = $currentDay;
	}

	/**
	 * Get current day
	 *
	 * @return int
	 */
	protected function getCurrentDay() {
		return $this->currentDay;
	}
}
