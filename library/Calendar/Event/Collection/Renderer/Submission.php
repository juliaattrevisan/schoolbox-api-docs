<?php
/**
 * Calendar_Event_Collection_Renderer_Submission
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Event_Collection_Renderer_Submission extends Calendar_Event_Collection_Renderer_Abstract {

	/**
	 * Render collection into calendar view
	 */
	public function render() {
		$submissionList = $this->getCalendarHandler()->getSubmissionEvents($this->getCurrentDay());

		$iterator = $submissionList->createIterator();

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
				'time_block' => ''
			));

			/* Get status to set action
			 */
			if ($this->getGroupHandler()->isStudent($_SESSION['id'])) {
				$action = 'View Details';
				$this->getPage()->setVar('MODIFY', '<a class="btn go-to float-left" href="/submission/submissionHistory/' . $currentEvent->getSubmissionBoxId() . '"><span></span>' . $action . '</a>');
			}
			else if ($this->getGroupHandler()->isStaff($_SESSION['id'])) {
				$this->getPage()->setVar('MODIFY', '<a class="btn go-to float-left" href="/submission/viewSubmission/' . $currentEvent->getSubmissionBoxId() . '"><span></span>View Submissions</a>');
			}
			else {
				$this->getPage()->setVar('MODIFY', '');
			}

			$this->getPage()->setVar(array(
				'EVENT_ID' => '',
				'TIME' => $currentEvent->getDerivedTime(),
				'NOTE' => $currentEvent->getNote(),
				'COLOUR' => $currentEvent->getColour(),
				'LOCATION' => '',
				'DETAIL' => '',

				/* Wtf - EVENT_TYPE => 'type' . $event['type']; and TYPE => $event['eventType']
				 */
				'EVENT_TYPE' => 'submission',
				'TYPE' => $currentEvent->getEventType(),

				'AUTHOR' => $currentEvent->getAuthorName(),
				'AUTHOR_ID' => $currentEvent->getAuthorId(),
				'EVENT_VISIBLE' => '',
				'EVENT_LEVEL' => '',
			));

			/* If a time was specified, show it; otherwise hide it
			 */
			if (strlen($currentEvent->getDerivedTime())) {
				$this->getPage()->parse('time_block', 'time');
			}

			$this->getPage()->parse("note_block", "note", true);
		}
	}
}
