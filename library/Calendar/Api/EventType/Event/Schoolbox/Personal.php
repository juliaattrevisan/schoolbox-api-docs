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
	 * Overridden to filter out non-personal events.
	 * TODO stop being lazy - implement proper filtering at the query level
	 *
	 * @param int $startTimestamp
	 * @param int $endTimestamp
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getEvents($startTimestamp, $endTimestamp) {
		$event_list = parent::getEvents($startTimestamp, $endTimestamp);
		$new_list   = array();
		foreach ($event_list as $event) {
			if ($event->getAccess() == Calendar::ACCESS_PERSONAL) {
				$new_list[] = $event;
			}
		}
		$event_list->setStack($new_list);
		return $event_list;
	}
}
