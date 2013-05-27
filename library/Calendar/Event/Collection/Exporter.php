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

		$v->setProperty('X-WR-CALNAME', 'Calendar export'); // set some X-properties, name, content.. .
		$v->setProperty('X-WR-CALDESC', 'The portal school calendar');
		$v->setProperty('X-WR-TIMEZONE', Cfg::get('date_timezone'));

		$t = new vtimezone();
		$t->setProperty('tzid', Cfg::get('date_timezone'));
//		$t->setProperty('last-modified', 1987, 1, 1);

//		$ts = new vtimezone('standard');
//		$ts->setProperty('dtstart', 1997, 10, 26, 2);
//		$rdate1 = array('year' => 1997, 'month' => 10, 'day' => 26, 'hour' => 02, 'min' => 0, 'sec' => 0);
//		$ts->setProperty('rdate', array($rdate1));
//		$ts->setProperty('tzoffsetfrom', '+1100');
//		$ts->setProperty('tzoffsetto', '+1000');
//		$ts->setProperty('tzname', 'EST');
//		$t->setComponent($ts);
//
//		$td = new vtimezone('daylight');
//		$td->setProperty('dtstart', 1997, 10, 26, 2);
//		$rdate1 = array('year' => 1997, 'month' => 4, 'day' => 6, 'hour' => 02, 'min' => 0, 'sec' => 0);
//		$td->setProperty('rdate', array($rdate1));
//		$td->setProperty('tzoffsetfrom', '+1000');
//		$td->setProperty('tzoffsetto', '+1100');
//		$td->setProperty('tzname', 'EST');
//		$t->setComponent($td);
//
//		$v->setComponent($t);

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

		$str = $v->createCalendar();
		echo $str;
	}
}
