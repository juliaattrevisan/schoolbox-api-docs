<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 *
 * This component is deprecated, it was previously used to display a school's campus calendar on a homepage
 *
 */
class Component_Homepage_CalendarListController extends Component_Homepage_ControllerAbstract
{
    /**
     * @var DeprecatedDatabase
     */
    private $db;

    /**
     * @var Calendar
     */
    private $calendarHandler;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
        $this->db              = DeprecatedDatabase::getInstance();
        $this->calendarHandler = new Calendar();

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'calendarList')) {
            return false;
        }
        $this->parseConfig();

        /* Output a group calendar */
        $this->setBlock($templateHandle, "group_events", "group_events_block");

        /* From updated calendar
        */
        $groupHandler = new Group();

        $this->setBlock("group_events", "attendance_icon", "attendance_icon_block");
        $this->setBlock("group_events", "attendance_status", "attendance_status_block");
        $this->setBlock("group_events", "attendance_request_button", "attendance_request_button_block");
        $this->setBlock("group_events", "attendance_list_button", "attendance_list_button_block");

        $this->setVar(
            array(
                'CALENDAR_PREFIX' => 'campus'
            )
        );

        //event limit changed from posts to days
        if ($this->getConfig('size') != 0 && $this->getConfig('size') != '') {
            $where = " AND DATE(start_datetime) < ADDDATE(CURDATE(), INTERVAL {$this->getConfig('size')} DAY) ";
        } else {
            $where = "";
        }
        $this->setVar('CALENDAR_TITLE', 'Campus Calendar');

        $query = "SELECT event.id, UNIX_TIMESTAMP(start_datetime) as outputDate, owner,
			TIME_FORMAT(start_datetime, '" . Cfg::get('calendar_time_format') . "') as startTimeString,
			TIME_FORMAT(end_datetime, '" . Cfg::get('calendar_time_format') . "') as endTimeString,
			priority, note, location, detail,
			user.id as uid, title, firstname, lastname,
			eventType, eventColour, attendance
		FROM event
		    LEFT JOIN user ON owner = user.id
		    LEFT JOIN luEventType ON type = eventTypeID
			JOIN event_campus ON event.id = event_campus.event_id
			JOIN event_group ON event.id = event_group.event_id
		WHERE access = 3
			AND event_campus.campus_id IN(" . implode(",", $_SESSION['campus']) . ")
			AND event_group.group_id = {$_SESSION['gid']}
			AND ( DATE(start_datetime) >= CURRENT_DATE() $where)
		ORDER BY start_datetime";

        $eventList = $this->db->getAll($query);

        $this->setVar('group_events_block', '');

        if (is_array($eventList)) {
            $userHandler = new User();
            $j           = 0;

            foreach ($eventList as $event) {
                if ($event['startTime'] == "0:00" || strtolower($event['startTime']) == "12:00am" || strtolower(
                        $event['startTime']
                    ) == "12:00 am" || $event['startTime'] == ''
                ) {
                    $this->setVar("TIME", "");
                } elseif ($event['endTime'] == "0:00" || strtolower($event['endTime']) == "12:00am" || strtolower(
                        $event['endTime']
                    ) == "12:00 am" || $event['endTime'] == ''
                ) {
                    $this->setVar("TIME", $event['startTime']);
                } else {
                    $this->setVar("TIME", $event['startTime'] . " - " . $event['endTime']);
                }

                $event['colour'] = $this->calendarHandler->displayColour($event['type']);

                $calendarDate = new Alaress_Date_Format($event['outputDate'], 'U');

                $output = array(
                    'CAL_TIME'      => rtrim(
                        ($event['startTime'] != "12:00AM" ? $event['startTime'] : "") . " - " . ($event['endTime'] != "12:00AM" ? $event['endTime'] : ""),
                        "- "
                    ),
                    'CAL_DATE'      => $calendarDate->getDateOnly(),
                    'CAL_ID'        => $event['id'],
                    'CAL_TITLE'     => Scrub::text($event['title']),
                    'CAL_LOCATION'  => Scrub::text($event['location']),
                    'CAL_DETAIL'    => Scrub::html($event['detail']),
                    'CAL_COLOUR'    => Scrub::text($event['colour']),
                    'CAL_MODIFY'    => $this->calendarHandler->getPerm(
                            $event['id']
                        ) > 1 ? "<a class=\"btn modify float-left\" href=\"/calendar/modify.php?eventID={$event['id']}&homepageId={$this->getContext()->getId()}&returnTo=homepage\" title=\"Modify\"><span></span>Modify</a>" : '',
                    'CAL_TYPE'      => Scrub::text($event['eventType']),
                    'CAL_AUTHOR'    => $userHandler->formatName($event),
                    'CAL_AUTHOR_ID' => $event['owner'],
                    'ON_OFF'        => ++$j % 2 ? '' : 'dark',
                );

                $this->setVar($output);

                // Clear attendance blocks
                $this->setVar(
                    array(
                        'attendance_icon_block'           => '',
                        'attendance_status_block'         => '',
                        'attendance_request_button_block' => '',
                        'attendance_list_button_block'    => '',
                    )
                );

                // Event attendance
                if ($event['attendance']) {
                    $this->parse('attendance_icon_block', 'attendance_icon');

                    // Anyone with write access can approve attendance
                    if ($this->permission >= WRITE_ACCESS) {
                        $this->parse('attendance_list_button_block', 'attendance_list_button');
                    }

                    if ($groupHandler->isStudent($_SESSION['id'])) {
                        if ($attendance = $this->calendarHandler->getAttendanceByEventAndUser(
                            $event['id'], $_SESSION['id']
                        )
                        ) {
                            /* Attendance Status
                                                        */
                            if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_PENDING) {
                                $this->setVar('ATTENDANCE_STATUS', 'Pending');
                            } elseif ($attendance['status'] == Calendar::ATTENDANCE_STATUS_APPROVED) {
                                $this->setVar('ATTENDANCE_STATUS', 'Approved');
                            } elseif ($attendance['status'] == Calendar::ATTENDANCE_STATUS_REJECTED) {
                                $this->setVar('ATTENDANCE_STATUS', 'Rejected');
                            }

                            $this->parse('attendance_status_block', 'attendance_status');

                            $this->setVar('ATTENDANCE_REQUEST_ACTION', 'Unattend');
                        } else {
                            $this->setVar('ATTENDANCE_REQUEST_ACTION', 'Attend');
                        }

                        $this->parse('attendance_request_button_block', 'attendance_request_button');
                    } elseif ($groupHandler->isStaff($_SESSION['id'])) {
                        $this->parse('attendance_list_button_block', 'attendance_list_button');
                    }
                }
                if ($event['startTimeString'] == "12:00AM" || $event['startTimeString'] == "") {
                    $this->setVar("CAL_TIME", "");
                } elseif ($event['endTimeString'] == "12:00AM") {
                    $this->setVar("CAL_TIME", $event['startTimeString']);
                } else {
                    $this->setVar("CAL_TIME", $event['startTimeString'] . "&ndash;" . $event['endTimeString']);
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

        /* Detroit
         */
        $this->applyCollapsedState();

        return true;
    }
}
