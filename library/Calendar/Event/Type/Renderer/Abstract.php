<?php
/**
 * Calendar_Event_Type_Renderer_Abstract
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
abstract class Calendar_Event_Type_Renderer_Abstract {

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
	 * @param $page
	 */
	public function __construct($page) {
		$this->setPage($page);

		$this->setGroupHandler(new Group(CALENDAR_SERVICE));
		$this->setCalendarHandler(new Calendar());
	}

	abstract public function visit($visitor);

	/* Getters/Setters
	 */

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
}
