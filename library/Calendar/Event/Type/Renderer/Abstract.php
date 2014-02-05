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
		$this->setup();
	}

	/**
	 * Overridable setup method.
	 */
	protected function setup() {}

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
	 * @return SitePage
	 */
	protected function getPage() {
		return $this->page;
	}

	/**
	 * If the Publish Date hasn't been reached yet, only show the events to Superusers
	 * in a different way so they can still edit/click them. Otherwise, they don't appear
	 * at all for everyone else
	 *
	 * @param $currentEvent
	 *
	 * @return bool
	 */
	protected function isVisible($currentEvent) {
		$isFutureEvent = ($currentEvent->getPublishDate()->getUnixTimestamp() >= time());
		$isVisibleToUser = ($this->getGroupHandler()->isSuperuser() || $currentEvent->isOwner() || (is_numeric($currentEvent->getCategoryId()) && ($this->getGroupHandler()->getPermission($currentEvent->getCategoryId())) > READ_ACCESS));

		if ($isFutureEvent && !($isVisibleToUser)) {
			return false;
		}

		return true;
	}
}
