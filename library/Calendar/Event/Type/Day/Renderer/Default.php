<?php
/**
 * Calendar_Event_Type_Day_Renderer_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Event_Type_Day_Renderer_Default extends Calendar_Event_Type_Renderer_Abstract {

    /**
     * Render this event
     *
     * @param Calendar_Event_Type_Abstract $currentEvent
     */
    public function visit($currentEvent) {
        if ($this->isVisible($currentEvent)) {

            /* Clear attendance blocks
             */
            $this->getPage()->setVar(array(
				'attendance_icon_block' => '',
				'attendance_status_block' => '',
				'attendance_request_button_block' => '',
				'attendance_list_button_block' => '',
				'has_category_block' => '',
			));

            /* Future events are visible to superusers, event owners, group members with write access
             */
            if ($currentEvent->getPublishDate()->getUnixTimestamp() >= time()) {
				$cssClass = 'translucent';
			}
			else {
				$cssClass = '';
			}

			/* Render normally if we haven't already skipped it due to being unpublished
			 */
            $this->getPage()->setVar(array(
				'EVENT_ID' => $currentEvent->getEventId(),
				'FULL_TIME' => $currentEvent->getFullDate(),
				'TIME' => $currentEvent->getTime(),
				'NOTE' => Scrub::text($currentEvent->getNote()),
				'COLOUR' => $currentEvent->getColour(),
				'LOCATION' => Scrub::text($currentEvent->getLocation()),
				'DETAIL' => Scrub::html($currentEvent->getDetail()),
				/* Wtf - EVENT_TYPE => 'type' . $event['type']; and TYPE => $event['eventType']
				 */
				'EVENT_TYPE' => 'type' . $currentEvent->getType(),
				'TYPE' => $currentEvent->getEventType(),
				'AUTHOR' => Scrub::text($currentEvent->getAuthorName()),
				'AUTHOR_ID' => $currentEvent->getAuthorId(),
				'EVENT_VISIBLE' => $cssClass,
				'EVENT_LEVEL' => $currentEvent->getDerivedEventLevel(),
			));

            /* Modify link. Should be templated...
             */
            if ($currentEvent->getIsEditable()) {
                $this->getPage()->setVar(
                    'MODIFY',
                    '<a class="btn modify float-left" href="/calendar/modify.php?eventID=' . $currentEvent->getEventId(
                    ) . '&returnTo=month"><span></span>Modify</a>'
                );
            }
			else {
                $this->getPage()->setVar('MODIFY', '');
            }

            /* Event attendance
             */
            if ($currentEvent->canAttend()) {
                $this->getPage()->parse('attendance_icon_block', 'attendance_icon');

                /* Anyone with write access can approve attendance
                 */
                if (is_numeric($currentEvent->getCategoryId())) {
                    if ($this->getGroupHandler()->getPermission($currentEvent->getCategoryId()) > READ_ACCESS) {
                        $this->getPage()->parse('attendance_list_button_block', 'attendance_list_button');
                    }
                }

                if ($this->getGroupHandler()->isStudent($_SESSION['id'])) {
                    if ($attendance = $this->getCalendarHandler()->getAttendanceByEventAndUser($currentEvent->getEventId(), $_SESSION['id'])) {

                        /* Attendance Status
                         */
                        if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_PENDING) {
                            $this->getPage()->setVar('ATTENDANCE_STATUS', 'Pending');
                        }
						else if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_APPROVED) {
							$this->getPage()->setVar('ATTENDANCE_STATUS', 'Approved');
						}
						else if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_REJECTED) {
							$this->getPage()->setVar('ATTENDANCE_STATUS', 'Rejected');
						}

                        $this->getPage()->parse('attendance_status_block', 'attendance_status');

                        $this->getPage()->setVar('ATTENDANCE_REQUEST_ACTION', 'Unattend');
                    }
					else {
                        $this->getPage()->setVar('ATTENDANCE_REQUEST_ACTION', 'Attend');
                    }

                    $this->getPage()->parse('attendance_request_button_block', 'attendance_request_button');
                }
				else if ($this->getGroupHandler()->isStaff($_SESSION['id'])) {
					$this->getPage()->parse('attendance_list_button_block', 'attendance_list_button');
                }
            }

            /* Show a homepage if appropriate
             */
            if ($currentEvent->getCategoryId() > 0) {
                $this->getPage()->setVar(array(
					'HAS_CATEGORY_ID' => $currentEvent->getCategoryId(),
					'HAS_CATEGORY_NAME' => Scrub::text($currentEvent->getCategoryName()),
				));

                $this->getPage()->parse('has_category_block', 'has_category');
            }

            $this->getPage()->parse('note_block', 'note', true);
        }
    }

    /**
     * If the Publish Date hasn't been reached yet, only show the events to Superusers
     * in a different way so they can still edit/click them. Otherwise, they don't appear
     * at all for everyone else
     *
     * @param $currentEvent
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
}
