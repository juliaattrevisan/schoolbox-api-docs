<?php
/**
 * Calendar_Event_Collection_Renderer_StudentBirthday

 School-specific implementation (filters and perms should exist outside of here)

//		if (is_array($birthdayList) && in_array(3, $_SESSION['campus'])) {
//		}

 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Event_Collection_Renderer_StudentBirthday extends Calendar_Event_Collection_Renderer_Abstract {

	/**
	 * Render collection into calendar view
	 */
	public function render() {
		$birthdayList = $this->getApi()->getStudentBirthdays($this->getCurrentDay());

		$iterator = $birthdayList->createIterator();

		for ($iterator->rewind(); $iterator->valid(); $iterator->next()) {
			$currentEvent = $iterator->current();

			/* Clear attendance blocks
			 */
			$this->getPage()->setVar(array(
				'attendance_icon_block' => '',
				'attendance_status_block' => '',
				'attendance_request_button_block' => '',
				'attendance_list_button_block' => '',
				'has_category_block' => '',
				'time_block' => '',
			));

			$this->getPage()->setVar(array(
				'EVENT_ID' => '',
				'TIME' => '',
				'NOTE' => $currentEvent->getNote(),
				'COLOUR' => $currentEvent->getColour(),
				'LOCATION' => '',
				'DETAIL' => '',

				/* Wtf - EVENT_TYPE => 'type' . $event['type']; and TYPE => $event['eventType']
				 */
				'EVENT_TYPE' => 'type' . $currentEvent->getType(),
				'TYPE' => $currentEvent->getEventType(),

				'AUTHOR' => '',
				'AUTHOR_ID' => '',
				'EVENT_VISIBLE' => '',
				'EVENT_LEVEL' => '',
			));

			$this->getPage()->parse("note_block", "note", true);
		}
	}
}
