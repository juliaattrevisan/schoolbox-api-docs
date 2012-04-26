<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_CalendarController extends Component_Dashboard_ControllerAbstract {

    protected $calendar;

    protected $group;

    public function __construct (Component_Instance $instance, User $user, SitePage $sitepage, Calendar $calendar, Group $group) {
        parent::__construct($instance, $user, $sitepage);

        $this->setCalendar($calendar);

        $this->setGroup($group);
    }

    public function execute () {
        $currDay = time();

        $this->setVar("DATE", date("l jS F"));

        $this->submissionList = $this->getCalendar()->getSubmissionEvents($currDay);

        $this->eventList = $this->getCalendar()->getDayEvents($currDay);

        return true;
    }

    public function renderTemplate ($templateHandle) {
        if ( !$this->loadTemplate($templateHandle, 'calendar') ) {
            return false;
        }

        $this->setBlock($templateHandle, "note_block", "note_listing");

        $this->setBlock("note_block", "attendance_icon", "attendance_icon_block");
        $this->setBlock("note_block", "attendance_status", "attendance_status_block");
        $this->setBlock("note_block", "attendance_request_button", "attendance_request_button_block");
        $this->setBlock("note_block", "attendance_list_button", "attendance_list_button_block");

		/* Initialise
		 */
		$this->setVar(array(
			'attendance_icon_block' => '',
			'attendance_status_block' => '',
			'attendance_request_button_block' => '',
			'attendance_list_button_block' => '',
		));


        if ( is_array($this->submissionList) ) {
            foreach ( $this->submissionList as $submission ) {

                $output = array(
                    'TIME' => $submission['dueTime'],
                    'NOTE' => 'WORK DUE ' . $submission['category'] . ( !empty( $submission['synergyCode'] )
                            ? ' (' . $submission['synergyCode'] . ')' : '' ) . ' - ' . $submission['name'],
                    'COLOUR' => $this->getCalendar()->displayColour(4), 'MODIFY' => '', 'LOCATION' => '', 'DETAIL' => '',
                    'EVENT_TYPE' => 'submission', 'TYPE' => 'Due Work',
                    'AUTHOR' => $this->user->formatName($submission), 'AUTHOR_ID' => $submission['owner'],
                    'EVENT_LEVEL' => '',
                );

                $this->setVar($output);
                $this->parse("note_listing", "note_block", true);
                $subout = 1;
            }
        }

        if ( !is_array($this->eventList) ) {
            if ( !$subout ) {
                $this->setVar("note_listing", "");
            }
        }
        else {
            $userHandler = $this->user;
            $groupHandler = $this->group;

            foreach ( $this->eventList as $event ) {
                if ( $event['startTime'] == "0:00" || strtolower($event['startTime']) == "12:00am" || strtolower($event['startTime']) == "12:00 am" || $event['startTime'] == '' ) {
                    $this->setVar("TIME", "");
                }
                else {
                    if ( $event['endTime'] == "0:00" || strtolower($event['endTime']) == "12:00am" || strtolower($event['endTime']) == "12:00 am" || $event['endTime'] == '' ) {
                        $this->setVar("TIME", $event['startTime']);
                    }
                    else {
                        $this->setVar("TIME", $event['startTime'] . " - " . $event['endTime']);
                    }
                }

                $output = array(
                    'EVENT_ID' => $event['id'],
                    'TIME' => rtrim(( $event['startTime'] != "12:00AM" ? $event['startTime']
                                                                    : "" ) . " - " . ( $event['endTime'] != "12:00AM"
                                  ? $event['endTime'] : "" ), "- "), 'NOTE' => Scrub::text($event['note']),
                    'LOCATION' => Scrub::text($event['location']), 'DETAIL' => Scrub::text($event['detail']),
                    'COLOUR' => '#' . $event['eventColour'],
                    'MODIFY' => $this->getCalendar()->getPerm($event['id']) > 1
                            ? "<a href=\"/calendar/modify.php?eventID=" . $event['id'] . "&returnTo=month\" title=\"Modify\">Modify</a>"
                            : '', 'EVENT_TYPE' => 'type' . $event['type'], 'TYPE' => $event['eventType'],
                    'AUTHOR' => $userHandler->formatName($event), 'AUTHOR_ID' => $event['owner'],
                );

                $this->setVar($output);

                /* Attendance */
                if ($event['attendance']) {
                    $this->parse('attendance_icon_block', 'attendance_icon');

                    if ($this->getGroup()->isStudent($_SESSION['id'])) {
                        if ($attendance = $this->getCalendar()->getAttendanceByEventAndUser($event['id'], $_SESSION['id'])) {
                            /* Attendance Status */
                            if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_PENDING) {
                                $this->setVar('ATTENDANCE_STATUS', 'Pending');
                            }
                            elseif ($attendance['status'] == Calendar::ATTENDANCE_STATUS_APPROVED) {
                                $this->setVar('ATTENDANCE_STATUS', 'Approved');
                            }
                            elseif ($attendance['status'] == Calendar::ATTENDANCE_STATUS_REJECTED) {
                                $this->setVar('ATTENDANCE_STATUS', 'Rejected');
                            }

                            $this->parse('attendance_status_block', 'attendance_status');

                            $this->setVar('ATTENDANCE_REQUEST_ACTION', 'Unattend');
                        }
                        else {
                            $this->setVar('ATTENDANCE_REQUEST_ACTION', 'Attend');
                        }

                        $this->parse('attendance_request_button_block', 'attendance_request_button');
                    }
                    elseif ($groupHandler->isStaff($_SESSION['id'])) {
                        $this->parse('attendance_list_button_block', 'attendance_list_button');
                    }
                }

                $this->parse("note_listing", "note_block", true);
            }
        }

        return true;
    }

    public function setCalendar($calendar)
    {
        $this->calendar = $calendar;
    }

    public function getCalendar()
    {
        return $this->calendar;
    }

    public function setGroup($group)
    {
        $this->group = $group;
    }

    public function getGroup()
    {
        return $this->group;
    }

}
