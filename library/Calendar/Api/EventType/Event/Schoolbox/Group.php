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
	 * Overridden to get events only for this group.
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
			array('group' => $this->getGroupId())
		);
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
