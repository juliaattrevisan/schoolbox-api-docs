<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_EventListController extends Component_Homepage_ControllerAbstract
{
    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'eventList')) {
            return false;
        }
        $this->parseConfig();

        //event output
        $this->setBlock($templateHandle, "group_events", "group_events_block");
        $this->setBlock($templateHandle, "import_events", "import_events_block");

        // From updated calendar
        $groupHandler = new Group();
        $calendarApi  = new Calendar_Api_EventType_Event_Schoolbox_Group($_SESSION['user'], array());
        $calendarApi->setGroupId($this->getContext('id'));

        // Filter to a given number of days.
        $eventLimit = intval($this->getConfig('size'));
        $dayLimit   = intval($this->getConfig('days'));

        if ($dayLimit <= 0) {
            $dayLimit = 365;
        }
        $eventList = $calendarApi->getEvents(
            strtotime('12am today', $_SERVER['REQUEST_TIME']),
            strtotime("12am +$dayLimit day", $_SERVER['REQUEST_TIME'])
        );

        $this->setBlock("group_events", "attendance_icon", "attendance_icon_block");
        $this->setBlock("group_events", "attendance_status", "attendance_status_block");
        $this->setBlock("group_events", "attendance_request_button", "attendance_request_button_block");
        $this->setBlock("group_events", "attendance_list_button", "attendance_list_button_block");

        $this->setVar(
            array(
                'CALENDAR_PREFIX' => 'group',
                'USER_TOKEN'      => $_SESSION['key'],
            )
        );

        $calendarHandler = new Calendar();

        $this->setVar('CALENDAR_TITLE', 'Group Calendar');
        $this->setVar('group_events_block', '');

        if (count($eventList)) {
            $j = 0;
            foreach ($eventList as $eventObject) {
                $j++;
                if ($eventLimit > 0 && $j > $eventLimit) {
                    break;
                }

                /** @var Calendar_Event_Type_Day $eventObject */
                $eventId = $eventObject->getEventId();
                $output = array(
                    'CAL_DATE' => $eventObject->getEventSpan(),
                    'CAL_ID' => $eventId,
                    'CAL_TITLE' => Scrub::text($eventObject->getTitle()),
                    'CAL_LOCATION' => Scrub::text($eventObject->getLocation()),
                    'CAL_DETAIL' => Scrub::html($eventObject->getDetail()),
                    'CAL_COLOUR' => Scrub::text($eventObject->getColour()),
                    'CAL_MODIFY' => $calendarHandler->getPerm($eventId) > READ_ACCESS
                            ? "<a class=\"btn modify float-left\" href=\"/calendar/modify.php?eventID=" . $eventId . "&homepageId={$this->getContext('id')}&returnTo=homepage\" title=\"Modify\"><span></span>Modify</a>"
                            : '',
                    'CAL_TYPE' => Scrub::text($eventObject->getEventType()),
                    'CAL_AUTHOR' => $eventObject->getAuthorName(),
                    'CAL_AUTHOR_ID' => $eventObject->getAuthorId(),
                    'ON_OFF' => $j % 2 ? '' : 'dark',
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
                if ($eventObject->getAttendance()) {
                    $this->parse('attendance_icon_block', 'attendance_icon');

                    /* Anyone with write access can approve attendance
                     */
                    if ($this->permission >= WRITE_ACCESS) {
                        $this->parse('attendance_list_button_block', 'attendance_list_button');
                    }

                    if ($groupHandler->isStudent($_SESSION['id'])) {
                        if ($attendance = $calendarHandler->getAttendanceByEventAndUser($eventId, $_SESSION['id'])) {

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

                $this->setVar("CAL_TIME", "");

                $this->parse("group_events_block", "group_events", true);

            }
        } else {
            $this->setVar("group_events_block", '');
        }


        //setup block for submission button
        $this->setBlock($templateHandle, "submit_event", "submit_event_block");
        if ($this->permission < WRITE_ACCESS) {
            $this->setVar(array(
                'submit_event_block' => '',
                'import_events_block' => ''
            ));
        } else {
            $this->parse("submit_event_block", "submit_event");
            $this->parse('import_events_block', 'import_events');
        }

        return true;
    }
}
