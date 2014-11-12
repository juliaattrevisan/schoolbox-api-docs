<?php

/**
 * Lister
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Event_Type_Renderer_Lister extends Calendar_Event_Type_Renderer_Abstract
{

    /**
     * Classes to be used as filter selectors
     *
     * @param Calendar_Event_Type_Abstract $currentEvent
     * @return array|string
     */
    private function determineSelectors($currentEvent)
    {
        $selectors = array(
            'type' . $currentEvent->getType(),
            'source' . $currentEvent->getConnectorId(),
        );

        /* Merge levels
         */
        $allLevels = explode(' ', $currentEvent->getDerivedEventLevel());
        $selectors = array_merge($selectors, $allLevels);

        /* Merge campuses
         */
        $allCampuses = explode(',', $currentEvent->getCampusList());

        foreach ($allCampuses as $currentCampus) {
            $selectors[] = 'campus' . trim($currentCampus);
        }

        /* Properly trim out spaces and stuff
         */
        foreach ($selectors as &$currentSelector) {
            $currentSelector = trim($currentSelector);
        }

        $selectors = array_filter(array_unique($selectors));

        return implode(' ', $selectors);
    }

    /**
     * Render this event
     *
     * @param Calendar_Event_Type_Abstract $currentEvent
     * @return Sabre\VObject\Component\Vevent
     */
    public function visit($currentEvent)
    {
        $page = $this->getPage();
        if ($currentEvent->getPublishDate()->getUnixTimestamp() > $_SERVER['REQUEST_TIME']) {
            $cssClass = 'translucent';
        } else {
            $cssClass = '';
        }

        $page->setVar(
            array(
                'EVENT_ID' => $currentEvent->getEventId(),
                'EVENT_START' => $currentEvent->getStartTime()->getTimeOnly(),
                'EVENT_END' => $currentEvent->getEndTime()->getTimeOnly(),
                'EVENT_TITLE' => Scrub::text($currentEvent->getTitle()),
                'EVENT_SELECTORS' => $this->determineSelectors($currentEvent),
                'EVENT_CLASS' => $cssClass,
                'EVENT_COLOUR' => $currentEvent->getColour(),
                'EVENT_DETAIL' => Scrub::html($currentEvent->getDetail()),
                'EVENT_TYPE' => Scrub::text($currentEvent->getEventType()),
                'EVENT_AUTHOR' => Scrub::text($currentEvent->getAuthorName()),
                'EVENT_AUTHOR_ID' => $currentEvent->getAuthorId(),
                'EVENT_FULL_TIME' => $currentEvent->getFullDate(),
                'EVENT_LOCATION' => Scrub::text($currentEvent->getLocation()),
                'EVENT_CATEGORY_ID' => $currentEvent->getCategoryId(),
                'EVENT_CATEGORY_NAME' => Scrub::text($currentEvent->getCategoryName()),
                'event_has_category_block' => '',
                'event_buttons_block' => '',
                'event_attendance_status_block' => '',
                'event_attendance_request_block' => '',
                'event_timed_block' => '',
                'event_allday_block' => '',
                'event_submission_block' => '',
            )
        );
        if ($currentEvent->getCategoryId() > 0) {
            $page->parse('event_has_category_block', 'event_has_category');
        }

        /* Special datetime formatting for Submissions
         */
        if ($currentEvent instanceof Calendar_Event_Type_Submission) {
            if ($currentEvent->getAllday()) {
                $page->setVar('EVENT_TIMESTAMP', $currentEvent->getEndTime()->getUsingFormat('Y-m-d'));
                $page->parse('event_allday_block', 'event_allday');
            }
            else {
                $page->setVar('EVENT_TIMESTAMP', $currentEvent->getEndTime()->getUsingFormat('Y-m-d H:i:s'));
                $page->parse('event_submission_block', 'event_submission');
            }
        } elseif ($currentEvent instanceof Calendar_Event_Type_ResourceBooking) {
            $page->setVar('EVENT_TYPE', 'Booking');
            $page->setVar('EVENT_TIMESTAMP', $currentEvent->getStartTime()->getUsingFormat('Y-m-d H:i:s'));
            $page->setVar('EVENT_ID', '');

            // Finagle better notes or details
            $title = $currentEvent->getTitle();
            if (strlen($title) === 0) {
                $title = 'Booking: '.$currentEvent->getBooking()->getResource()->getName();
            } else {
                $page->setVar(
                    'EVENT_DETAIL',
                    sprintf(
                        '(Booking for: %s)<br>%s',
                        Scrub::text($currentEvent->getBooking()->getResource()->getName()),
                        Scrub::html($currentEvent->getDetail())
                    )
                );
            }
            $title = sprintf(
                '(%s) %s',
                $currentEvent->getBooking()->getStatus()->getName(),
                $title
            );
            $page->setVar('EVENT_TITLE', Scrub::text($title));
            $page->parse('event_timed_block', 'event_timed');
        } elseif ($currentEvent->getAllday()) {
            $page->setVar('EVENT_TIMESTAMP', $currentEvent->getStartTime()->getUsingFormat('Y-m-d'));
            $page->parse('event_allday_block', 'event_allday');
        } else {
            $page->setVar('EVENT_TIMESTAMP', $currentEvent->getStartTime()->getUsingFormat('Y-m-d H:i:s'));
            $page->parse('event_timed_block', 'event_timed');
        }

        // Crude approximation of fullcalendar's butonyes

        // Modify butonye
        if ($currentEvent instanceof Calendar_Event_Type_Day && $currentEvent->getIsEditable()) {
            // Only Schoolbox events are modifiable through this interface.
            // Anyway, events should be able to supply their own edit URI.
            $page->setVar(
                array(
                    'EVENT_BUTTON_TEXT' => 'Modify',
                    'EVENT_BUTTON_TYPE' => 'modify',
                    'EVENT_BUTTON_HREF' => '/calendar/modify.php?eventID=' . $currentEvent->getEventId(),
                )
            );
            $page->parse('event_buttons_block', 'event_buttons', true);
        }

        // Go to homepage butonye
        if ($currentEvent->getCategoryId() > 0) {
            // Only Schoolbox events are modifiable through this interface.
            // Anyway, events should be able to supply their own edit URI.
            $page->setVar(
                array(
                    'EVENT_BUTTON_TEXT' => 'Go to homepage',
                    'EVENT_BUTTON_TYPE' => '',
                    'EVENT_BUTTON_HREF' => '/homepage/' . $currentEvent->getCategoryId(),
                )
            );
            $page->parse('event_buttons_block', 'event_buttons', true);
        }

        // Attendance butonye - needs access to calendar and group handlers
//        if ($currentEvent->canAttend()) {
//            if ($attendance = $page->getCalendar()->getAttendanceByEventAndUser(
//                $currentEvent->getEventId(),
//                $_SESSION['id']
//            )) {
//                /* Attendance Status
//                 */
//                if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_PENDING) {
//                    $page->setVar('ATTENDANCE_STATUS', 'Pending');
//                } elseif ($attendance['status'] == Calendar::ATTENDANCE_STATUS_APPROVED) {
//                    $page->setVar('ATTENDANCE_STATUS', 'Approved');
//                } elseif ($attendance['status'] == Calendar::ATTENDANCE_STATUS_REJECTED) {
//                    $page->setVar('ATTENDANCE_STATUS', 'Rejected');
//                } else {
//                    $page->setVar('ATTENDANCE_STATUS', '');
//                }
//            } elseif ($page->getGroup()->isStaff($_SESSION['id'])) {
//                $page->parse('event_attendance_status_block', 'event_attendance_status');
//            }
//            $page->parse('event_attendance_status_block', 'event_attendance_status');
//        }

        $page->parse('event_block', 'event', true);

        /* Clear children after parsing out the parent
         */
        $page->setVar(
            array(
                'event_allday_block' => '',
                'event_timed_block' => ''
            )
        );
    }
}
