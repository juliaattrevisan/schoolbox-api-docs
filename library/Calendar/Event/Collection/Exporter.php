<?php
/**
 * Calendar_Event_Collection_Exporter
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Event_Collection_Exporter extends Calendar_Event_Collection_Renderer {

	/**
	 * Set up the V
	 *
	 * @return \vcalendar
	 */
	private function setupVcal() {
		$v = new vcalendar();
		$v->setConfig('unique_id', Cfg::get('mail_address'));
		$v->setProperty('calscale', 'GREGORIAN');
		$v->setProperty('method', 'PUBLISH');

		$v->setProperty('X-WR-CALNAME', 'Calendar export');
		$v->setProperty('X-WR-CALDESC', 'The portal school calendar');

		return $v;
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

		$collection = $this->getAllEvents();

		foreach ($collection as $currentEvent) {
			$currentEvent->setRendererClass('Calendar_Event_Type_Exporter');

			//
			// Only add valid components, because exporter's visitor can return null.
			$classname = $currentEvent->getRendererClass();
			$component = $currentEvent->accept(new $classname(null));
			if ($component instanceof calendarComponent) {
				$v->addComponent($component);
			}
		}

		//
		// Add timezone AFTER adding all events. Hooray, IcalCreator is smart
		// enough to add them for me!
		iCalUtilityFunctions::createTimezone($v, Cfg::get('date_timezone'));
		$str = $v->createCalendar();
		echo $str;
	}
}
