<?php
/**
 * Calendar_Event_Type_EOTC_Renderer_Javascript
 *
 * Renders out a JSON mob of event objects
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Event_Type_EOTC_Renderer_Javascript extends Calendar_Event_Type_Renderer_Abstract {

	/**
	 * Visitor
	 *
	 * @param $currentEvent
	 */
	public function visit($currentEvent) {
		if ($this->isVisible($currentEvent)) {

			/* Events not in Approved state appear translucent
			 */
			$customSettings = $currentEvent->getCustom();

			if ($customSettings['approved'])  {
				$cssClass = '';
			}
			else {
				$cssClass = 'translucent';
			}

			/* Modify link. Should be templated...
			 */
			if ($currentEvent->getIsEditable() && ($currentEvent->getEventId() > -1)) {
				$editable = true;
			}
			else {
				$editable = false;
			}

			/* Congeal all that stuff
			 */

			/* All-day settings
			 */
			if ($currentEvent->getAllDay()) {
 				// Compensate for FullCalendar/vCal having exclusive end times
				$allDay = true;
				$start  = $currentEvent->getStartTime()->getDatabaseDate();
				$end    = $currentEvent->getEndTime()->addDays(1)->getDatabaseDate();
			}
			else {
				$allDay = false;
				$start  = $currentEvent->getStartTime()->getDatabase();
				$end    = $currentEvent->getEndTime()->getDatabase();
			}

			//
			// Add campus list to list of classes
			$classes = array(
				$currentEvent->getDerivedEventLevel(),
				'type' . $currentEvent->getType(),
			);
			if (($connectorId = $currentEvent->getConnectorId()) > 0) {
				$classes[] = 'source' . $connectorId;
			}

			$campus_list = array_filter(array_map('trim', explode(',', $currentEvent->getCampusList())));

			foreach ($campus_list as &$campus) {
				$campus = 'campus'.trim($campus);
			}

			unset($campus);

			if (!empty($campus_list)) {
				$classes[] = implode(' ', $campus_list);
			}

			$event = array(
				'title' => $currentEvent->getTitle(),
				'start' => "$start",
				'end' => "$end",

				/* Whether you can edit this event or not
				 */
				'editable' => $editable,

				/* Whether it's an all-day event or not
				 * If allDay is true, then you can't resize it
				 */
				'allDay' => $allDay,

				/* Colour of the event
				 */
				'color' => $currentEvent->getColour(),

				/* Campus slug so you can filter events
				 */
				'className' => $classes,

				/* Extra data that the Event Object doesn't directly support
				 */
				'data' => array(
					'meta' => array(
						'eventId' => $currentEvent->getEventId(),
						'time' => $currentEvent->getTime(),
						'detail' => Scrub::html($currentEvent->getDetail()),
						'location' => $currentEvent->getLocation(),
						'eventTypeId' => $currentEvent->getType(),
						'eventType' => 'type' . $currentEvent->getType(),
						'type' => $currentEvent->getEventType(),
						'author' => $currentEvent->getAuthorName(),
						'authorId' => $currentEvent->getAuthorId(),
						'level' => $currentEvent->getDerivedEventLevel(),
						'completed' => $currentEvent->getCompleted(),
					),
					'links' => array(
						'category' => array(
							'id' => '',
							'name' => '',
						)
					),
					'styles' => array(
						'visible' => $cssClass,
					),
					'attendance' => array(
						'icon' => false,
						'status' => false,
						'requestButton' => false,
						'listButton' => false,
					),
					'custom' => array(
						'modifyLink' => '/modules/eotc/manage/modify.php?eventID=' . $currentEvent->getEventId(),
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
	protected function isVisible($currentEvent) {
		return true;
	}

    /**
     * Determine whether an event is a task or not
     *
     * @param $currentEvent
     * @return bool
     */
    private function isTask($currentEvent) {
        if ($currentEvent->getType() == $this->getTaskTypeId()) {
            return true;
        }

        return false;
    }

    /* Getters/Setters
     */

    /**
     * @param int $taskTypeId
     */
    private function setTaskTypeId($taskTypeId) {
        $this->taskTypeId = $taskTypeId;
    }

    /**
     * @return int
     */
    private function getTaskTypeId() {
        return $this->taskTypeId;
    }
}
