<?php
/**
 * Calendar_Api_EventType_Event_Schoolbox_Group
 *
 * Like Calendar_Api_EventType_Event_Schoolbox_Default, but only returns events
 * for a given group.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Calendar_Api_EventType_Event_Schoolbox_Group extends Calendar_Api_EventType_Event_Schoolbox_Default {

	/**
	 * The group to get evetns for
	 *
	 * @var int
	 */
	protected $group_id;

	/**
	 * Overridden to filter out events not for this group.
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
			if ($event->getAccess() == Calendar::ACCESS_GROUP) {
				if ($event->getCategoryId() == $this->getGroupId()) {
					$new_list[] = $event;
				}
			}
		}
		$event_list->setStack($new_list);
		return $event_list;
	}

	/**
	 * @param  int $group_id
	 *
	 * @return $this
	 */
	public function setGroupId($group_id) {
		$this->group_id = $group_id;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getGroupId() {
		return $this->group_id;
	}
}
