<?php
/**
 * Calendar_Api_EventType_Event_Schoolbox_Personal
 *
 * Like Calendar_Api_EventType_Event_Schoolbox_Default, but only returns events
 * that are personal.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Calendar_Api_EventType_Event_Schoolbox_Personal extends Calendar_Api_EventType_Event_Schoolbox_Default {

	/**
	 * Overridden to get personal events only.
	 *
	 * User filtering is done in query - this may change, as admins may want to
	 * fetch personal events from other calendars.
	 *
	 * @param int $startTimestamp
	 * @param int $endTimestamp
	 *
	 * @return Calendar_Event_Collection
	 */
	protected function getEventsFromCalendarHandler($startTimestamp, $endTimestamp) {
		return $this->getCalendarHandler()->getDayEvents(
			$startTimestamp,
			$endTimestamp,
			array('personal' => true)
		);
	}
}
