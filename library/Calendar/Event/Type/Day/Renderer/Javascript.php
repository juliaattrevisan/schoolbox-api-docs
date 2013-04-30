<?php
/**
 * Calendar_Event_Type_Day_Renderer_Javascript
 *
 * Renders out a JSON mob of event objects
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Event_Type_Day_Renderer_Javascript extends Calendar_Event_Type_Renderer_Abstract {

    /**
     * The typeId of Task event types
     *
     * @var int
     */
    private $taskTypeId = -1;

    /**
     * Stinky Call Super style -_-
     *
     * @param $page
     */
    public function __construct($page) {

        /* Forgive me, for I have sinned
         */
        parent::__construct($page);

        /* Determine which event type is the Task one
         */
        $this->detectTaskTypeId();
    }

    /**
     * Detect the task type id
     */
    private function detectTaskTypeId() {
        $allTypes = $this->getCalendarHandler()->getTypeList();

        if (is_array($allTypes) && count($allTypes)) {
            foreach($allTypes as $currentType) {

                /* Event Task id
                 */
                if ($currentType['eventType'] == 'Task') {
                    $this->setTaskTypeId($currentType['eventTypeID']);
                }
            }
        }
    }

	/**
	 * Visitor
	 *
	 * @param $currentEvent
	 */
	public function visit($currentEvent) {
		if ($this->isVisible($currentEvent)) {

			/* Future events are visible to superusers, event owners, group members with write access
			 */
			if ($currentEvent->getPublishDate()->getUnixTimestamp() >= time()) {
				$cssClass = 'translucent';
			}
			else {
				$cssClass = '';
			}

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

			if ($currentEvent->canAttend()) {
				$hasIcon = true;

				/* Anyone with write access can approve attendance
				 */
				if (is_numeric($currentEvent->getCategoryId())) {
					if ($this->getGroupHandler()->getPermission($currentEvent->getCategoryId()) > READ_ACCESS) {
						$hasListButton = true;
					}
				}

				if ($this->getGroupHandler()->isStudent($_SESSION['id'])) {
					if ($attendance = $this->getCalendarHandler()->getAttendanceByEventAndUser($currentEvent->getEventId(), $_SESSION['id'])) {

						/* Attendance Status
	  					 */
						if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_PENDING) {
							$hasStatus = 'Pending';
						}
						else if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_APPROVED) {
							$hasStatus = 'Approved';
						}
						else if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_REJECTED) {
							$hasStatus = 'Rejected';
						}

						$hasAttendanceRequestButton = 'Unattend';
					}
					else {
						$hasAttendanceRequestButton = 'Attend';
					}
				}
				else if ($this->getGroupHandler()->isStaff($_SESSION['id'])) {
					$hasListButton = true;
				}
			}

			/* Congeal all that stuff
			 */
			$start = $currentEvent->getStartTime()->getDatabaseDate() . ' ' . $currentEvent->getStartTime()->getDatabaseTime();
			$end = $currentEvent->getEndTime()->getDatabaseDate() . ' ' . $currentEvent->getEndTime()->getDatabaseTime();

            /* Make tasks allDay events by default
             */
            if ($this->isTask($currentEvent)) {
                $allDay = true;
            }
            else {
                $allDay = false;
            }

			//
			// Add campus list to list of classes
			$classes = array(
				$currentEvent->getDerivedEventLevel(),
				'type' . $currentEvent->getType()
			);
			$campus_list = explode(',', $currentEvent->getCampusList());
			foreach ($campus_list as $campus_id) {
				if ($campus_id) {
					$classes[] = 'campus'.trim($campus_id);
				}
			}

			$event = array(
				'title' => $currentEvent->getNote(),
				'start' => "$start",
				'end' => "$end",

				/* Whether you can edit this event or not
				 */
				'editable' => $editable,

				/* Whether it's an all-day event or not
				 * If allDay is true, then you can't resize it
				 *
				 * Schoolbox usage would be that all events are not
				 * allDay events
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
						'detail' => $currentEvent->getDetail(),
						'location' => $currentEvent->getLocation(),
						'eventTypeId' => $currentEvent->getType(),
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
						'visible' => $cssClass,
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
		$isFutureEvent = ($currentEvent->getPublishDate()->getUnixTimestamp() >= time());
		$isVisibleToUser = ($this->getGroupHandler()->isSuperuser() || $currentEvent->isOwner() || (is_numeric($currentEvent->getCategoryId()) && ($this->getGroupHandler()->getPermission($currentEvent->getCategoryId())) > READ_ACCESS));

		if ($isFutureEvent && !($isVisibleToUser)) {
			return false;
		}

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
