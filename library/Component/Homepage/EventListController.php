<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_EventListController extends Component_Homepage_ControllerAbstract {

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if (!parent::loadTemplate($templateHandle, 'eventList')) {
            return false;
        }
        $this->parseConfig();

        //event output
        $this->setBlock($templateHandle, "group_events", "group_events_block");

        // From updated calendar
        $groupHandler = new Group();

        $this->setBlock("group_events", "attendance_icon", "attendance_icon_block");
        $this->setBlock("group_events", "attendance_status", "attendance_status_block");
        $this->setBlock("group_events", "attendance_request_button", "attendance_request_button_block");
        $this->setBlock("group_events", "attendance_list_button", "attendance_list_button_block");

        $this->setVar(
            array(
                'CALENDAR_PREFIX' => 'group'
            )
        );

        $calendarHandler = new Calendar;
        $eventList = $calendarHandler->getAllInCategory(
            $this->getContext('id'),
            array(
                'size' => $this->getConfig('size'),
                'days' => $this->getConfig('days'),
            )
        );

        $this->setVar('CALENDAR_TITLE', 'Group Calendar');
        $this->setVar('group_events_block', '');

        if (is_array($eventList)) {
            $userHandler = new User();
            $j = 0;
            foreach ($eventList as $event) {

                $event['colour'] = $calendarHandler->displayColour($event['type']);

                $calendarDate = new Alaress_Date_Format(strtotime($event['startDate']), 'U');

                $output = array(
                    'CAL_TIME' => rtrim(
                        ($event['startTime'] != "12:00AM" ? $event['startTime'] : "") . " - " .
                            ($event['endTime'] != "12:00AM" ? $event['endTime'] : ""),
                        "- "
                    ),
                    'CAL_DATE' => $calendarDate->getDateOnly(),
                    'CAL_ID' => $event['id'],
                    'CAL_NOTE' => Scrub::text($event['note']),
                    'CAL_LOCATION' => Scrub::text($event['location']),
                    'CAL_DETAIL' => Scrub::text($event['detail']),
                    'CAL_COLOUR' => $event['colour'],
                    'CAL_MODIFY' => $calendarHandler->getPerm(
                        $event['id']
                    ) > 1 ? "<a class=\"btn modify float-left\" href=\"/calendar/modify.php?eventID=" . $event['id'] . "&returnTo=homepage\" title=\"Modify\"><span></span>Modify</a>" : '',
                    'CAL_TYPE' => $event['eventType'],
                    'CAL_AUTHOR' => $userHandler->formatName($event),
                    'CAL_AUTHOR_ID' => $event['owner'],
                    'ON_OFF' => ++$j % 2 ? '' : 'dark',
                );
                $this->setVar($output);

                // Clear attendance blocks
                $this->setVar(
                    array(
                        'attendance_icon_block' => '',
                        'attendance_status_block' => '',
                        'attendance_request_button_block' => '',
                        'attendance_list_button_block' => '',
                    )
                );

                /* Event attendance
             */
                if ($event['attendance']) {
                    $this->parse('attendance_icon_block', 'attendance_icon');

                    /* Anyone with write access can approve attendance
                  */
                    if ($groupHandler->getPermission($this->getContext('id')) >= WRITE_ACCESS) {
                        $this->parse('attendance_list_button_block', 'attendance_list_button');
                    }

                    if ($groupHandler->isStudent($_SESSION['id'])) {
                        if ($attendance = $calendarHandler->getAttendanceByEventAndUser(
                            $event['id'], $_SESSION['id']
                        )
                        ) {

                            /* Attendance Status
                            */
                            if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_PENDING) {
                                $this->setVar('ATTENDANCE_STATUS', 'Pending');
                            } else {
                                if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_APPROVED) {
                                    $this->setVar('ATTENDANCE_STATUS', 'Approved');
                                } else {
                                    if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_REJECTED) {
                                        $this->setVar('ATTENDANCE_STATUS', 'Rejected');
                                    }
                                }
                            }

                            $this->parse('attendance_status_block', 'attendance_status');

                            $this->setVar('ATTENDANCE_REQUEST_ACTION', 'Unattend');
                        } else {
                            $this->setVar('ATTENDANCE_REQUEST_ACTION', 'Attend');
                        }

                        $this->parse('attendance_request_button_block', 'attendance_request_button');
                    } else {
                        if ($groupHandler->isStaff($_SESSION['id'])) {
                            $this->parse('attendance_list_button_block', 'attendance_list_button');
                        }
                    }
                }

                if ($event['startTimeString'] == "12:00AM" || $event['startTimeString'] == "") {
                    $this->setVar("CAL_TIME", "");
                } else {
                    if ($event['endTimeString'] == "12:00AM") {
                        $this->setVar("CAL_TIME", $event['startTimeString']);
                    } else {
                        $this->setVar("CAL_TIME", $event['startTimeString'] . "&ndash;" . $event['endTimeString']);
                    }
                }

                $this->parse("group_events_block", "group_events", true);

            }
        } else {
            $this->setVar("group_events_block", '');
        }


        //setup block for submission button
        $this->setBlock($templateHandle, "submit_event", "submit_event_block");
        if ($this->permission < WRITE_ACCESS) {
            $this->setVar("submit_event_block", "");
        } else {
            $this->parse("submit_event_block", "submit_event");
        }


        return true;
    }

}
