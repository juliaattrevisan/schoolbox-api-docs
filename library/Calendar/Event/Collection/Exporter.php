<?php
/**
 * Calendar_Event_Collection_Exporter
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */

use Sabre\VObject\Component\VCalendar;

class Calendar_Event_Collection_Exporter extends Calendar_Event_Collection_Renderer {

	/**
	 * Set up the V
	 *
	 * @return VCalendar
	 */
	private function setupVcal() {
		$calendar = new VCalendar();
		$calendar->add('method', 'PUBLISH');
		$calendar->add('X-WR-CALNAME', Config::get('title').' Calendar');
		$calendar->add('X-WR-CALDESC', 'Calendar for '.Config::get('school_name'));
		return $calendar;
	}

	/**
	 * Collects all events from everywhere, for the current day
	 *
	 * Do this by using ReflectionClass on the API Interface to
	 * get all the mandatory methods
	 */
	protected function collectAllEvents() {
		$this->setAllEvents($this->getApi()->getEvents($this->getStartTimestamp(), $this->getEndTimestamp()));
		$this->getAllEvents()->sort();
	}

	/**
	 * Render all events
	 */
	public function renderAll() {
		$v = $this->setupVcal();

		/** @var Calendar_Event_Type_Abstract[] $collection */
		$collection = $this->getAllEvents();
		$renderer   = new Calendar_Event_Type_Exporter_VObject(null);
		$renderer->setVCalendar($v);

		foreach ($collection as $currentEvent) {
			$currentEvent->accept($renderer);
//			$component = $currentEvent->accept($renderer);
//			if ($component instanceof calendarComponent) {
//				$v->addComponent($component);
//			}
		}

		//
		// Add timezone AFTER adding all events. Hooray, IcalCreator is smart
		// enough to add them for me!
//		iCalUtilityFunctions::createTimezone($v, Cfg::get('date_timezone'));
//		$str = $v->output();
//		echo $str;
		echo $v->serialize();
	}
}
