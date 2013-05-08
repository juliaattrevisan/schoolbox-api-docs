<?php
/**
 * Calendar_Event_Type_Submission_Renderer_Javascript
 *
 * Renders out a JSON mob of event objects
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Event_Type_Submission_Renderer_Javascript extends Calendar_Event_Type_Renderer_Abstract {

	/**
	 * Visitor
	 *
	 * @param $currentEvent
	 */
	public function visit($currentEvent) {
		if ($this->isVisible($currentEvent)) {

			/* Future events are visible to superusers, event owners, group members with write access
			 */
//			if ($currentEvent->getPublishDate()->getUnixTimestamp() >= time()) {
//				$cssClass = 'translucent';
//			}
//			else {
//				$cssClass = '';
//			}

			/* Modify link. Should be templated...
			 */
			if ($currentEvent->getIsEditable() && ($currentEvent->getEventId() > -1)) {
				$editable = true;
			}
			else {
				$editable = false;
			}

			/* Show a homepage if appropriate
			 */
			if ($currentEvent->getCategoryId() > 0) {
				$categoryId = $currentEvent->getCategoryId();
				$categoryName = Scrub::text($currentEvent->getCategoryName());
			}
			else {
				$categoryId = '';
				$categoryName = '';
			}

			/* Event attendance
			 */
			$hasIcon = false;
			$hasStatus = false;
			$hasAttendanceRequestButton = false;
			$hasListButton = false;

			/* Congeal all that stuff
			 */
			$start   = $currentEvent->getStartTime()->getDatabaseDate();
			$all_day = $currentEvent->getAllDay();
			if (!$all_day) {
				$start .= ' ' . $currentEvent->getStartTime()->getDatabaseTime();
			}

			$event = array(
				'title' => $currentEvent->getNote(),
				'start' => "$start",
				'end' => "",

				/* Whether you can edit this event or not
				 */
				'editable' => $editable,

				/* Whether it's an all-day event or not
				 * If allDay is true, then you can't resize it
				 *
				 * Schoolbox usage would be that all events are not
				 * allDay events
				 */
				'allDay' => $all_day,

				/* Colour of the event
				 */
				'color' => $currentEvent->getColour(),

				/* Extra data that the Event Object doesn't directly support
				 */
				'data' => array(
					'meta' => array(
						'eventId' => $currentEvent->getEventId(),
						'time' => $currentEvent->getTime(),
						'detail' => $currentEvent->getDetail(),
						'location' => $currentEvent->getLocation(),
						'eventType' => 'type' . $currentEvent->getType(),
						'type' => $currentEvent->getEventType(),
						'author' => Scrub::text($currentEvent->getAuthorName()),
						'authorId' => $currentEvent->getAuthorId(),
						'level' => $currentEvent->getDerivedEventLevel(),
						'completed' => $currentEvent->getCompleted(),
					),
					'links' => array(
						'category' => array(
							'id' => $categoryId,
							'name' => $categoryName,
						)
					),
					'styles' => array(
						'visible' => $this->isVisible($currentEvent),
					),
					'attendance' => array(
						'icon' => $hasIcon,
						'status' => $hasStatus,
						'requestButton' => $hasAttendanceRequestButton,
						'listButton' => $hasListButton,
					)
				)
			);

			echo json_encode($event);
			echo ",";
		}
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
	private function isVisible($currentEvent) {
//		$isFutureEvent = ($currentEvent->getPublishDate()->getUnixTimestamp() >= time());
//		$isVisibleToUser = ($this->getGroupHandler()->isSuperuser() || $currentEvent->isOwner() || (is_numeric($currentEvent->getCategoryId()) && ($this->getGroupHandler()->getPermission($currentEvent->getCategoryId())) > READ_ACCESS));
//
//		if ($isFutureEvent && !($isVisibleToUser)) {
//			return false;
//		}

		return true;
	}
}
