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

    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage, Calendar $calendar, Group $group) {
        parent::__construct($instance, $user, $sitepage);

        $this->setCalendar($calendar);

        $this->setGroup($group);
    }

    public function execute() {
        $currDay = time();

        $this->setVar("DATE", date("l jS F"));

		$factory = new Calendar_Api_Factory();

		/* A list of submissions
		 */
		$submissionConfig = new Calendar_Api_Config_Default();
		$submissionConfig->setEventType('Submission');
		$submissionConfig->setConnector('Schoolbox');

		$api = $factory->resetConfigs()->addConfig($submissionConfig)->produce();

		$this->submissionList = $api->getEvents($currDay);
		$this->submissionList->sort();

		/* A list of events
		 */
		$eventListConfig = new Calendar_Api_Config_Default();
		$eventListConfig->setEventType('Event');
		$eventListConfig->setConnector('Schoolbox');

		$api = $factory->resetConfigs()->addConfig($eventListConfig)->produce();

        $this->eventList = $api->getEvents($currDay);
        $this->eventList->sort();

        return true;
    }

    public function renderTemplate($templateHandle) {
        $this->setVar("note_listing", "");

        if (!$this->loadTemplate($templateHandle, 'calendar')) {
            return false;
        }

        $this->setBlock($templateHandle, "note_block", "note_listing");

        $this->setBlock("note_block", "has_category", "has_category_block");
        $this->setBlock("note_block", "attendance_icon", "attendance_icon_block");
        $this->setBlock("note_block", "attendance_status", "attendance_status_block");
        $this->setBlock("note_block", "attendance_request_button", "attendance_request_button_block");
        $this->setBlock("note_block", "attendance_list_button", "attendance_list_button_block");

        /* Initialise
         */
        $this->setVar(
            array(
                'attendance_icon_block' => '',
                'attendance_status_block' => '',
                'attendance_request_button_block' => '',
                'attendance_list_button_block' => '',
                'has_category_block' => '',
            )
        );

        $j = 0;

        if (is_array($this->submissionList)) {
            foreach ($this->submissionList as $currentEvent) {

                /* Get status to set action
                 */
                if ($this->getGroupHandler()->isStudent($_SESSION['id'])) {
                    $action = 'View Details';
                    $this->setVar(
                        'MODIFY',
                        '<a class="btn go-to float-left" href="/submission/submissionHistory/' . $currentEvent->getSubmissionBoxId(
                        ) . '"><span></span>' . $action . '</a>'
                    );
                } else {
                    if ($this->getGroupHandler()->isStaff($_SESSION['id'])) {
                        $this->setVar(
                            'MODIFY',
                            '<a class="btn go-to float-left" href="/submission/viewSubmission/' . $currentEvent->getSubmissionBoxId(
                            ) . '"><span></span>View Submissions</a>'
                        );
                    } else {
                        $this->setVar('MODIFY', '');
                    }
                }

                $this->setVar(
                    array(
                        'EVENT_ID' => '',
                        'TIME' => $currentEvent->getTime(),
                        'FULL_TIME' => $currentEvent->getFullDate(),
                        'NOTE' => $currentEvent->getNote(),
                        'COLOUR' => $currentEvent->getColour(),
                        'LOCATION' => '',
                        'HAS_CATEGORY_ID' => $currentEvent->getCategoryId(),
                        'HAS_CATEGORY_NAME' => Scrub::text($currentEvent->getCategoryName()),
                        'DETAIL' => Scrub::text($currentEvent->getSubmissionBoxDetail()),
                        /* Wtf - EVENT_TYPE => 'type' . $event['type']; and TYPE => $event['eventType']
                         */
                        'EVENT_TYPE' => 'submission',
                        'TYPE' => $currentEvent->getEventType(),
                        'AUTHOR' => $currentEvent->getAuthorName(),
                        'AUTHOR_ID' => $currentEvent->getAuthorId(),
                        'EVENT_VISIBLE' => '',
                        'EVENT_LEVEL' => '',
                    )
                );
                $this->parse('has_category_block', 'has_category');
                $this->parse("note_listing", "note_block", true);
            }
        }

//        $iterator = $this->eventList->createIterator();
//
//        for ($iterator->rewind(); $iterator->valid(); $iterator->next()) {
//            $currentEvent = $iterator->current();
//
//            $this->setVar(
//                array(
//                    'EVENT_ID' => $currentEvent->getEventId(),
//                    'TIME' => $currentEvent->getTime(),
//                    'FULL_TIME' => $currentEvent->getFullDate(),
//                    'NOTE' => $currentEvent->getNote(),
//                    'LOCATION' => $currentEvent->getLocation(),
//                    'DETAIL' => $currentEvent->getDetail(),
//                    'COLOUR' => $currentEvent->getColour(),
//                    'EVENT_TYPE' => 'type' . $currentEvent->getType(),
//                    'TYPE' => $currentEvent->getEventType(),
//                    'AUTHOR' => $currentEvent->getAuthorName(),
//                    'AUTHOR_ID' => $currentEvent->getAuthorId(),
//                    'ON_OFF' => ++$j % 2 ? 'dark' : '',
//                )
//            );
//
//            /* Modify link. Should be templated...
//                */
//            if ($currentEvent->getIsEditable()) {
//                $this->setVar(
//                    'MODIFY',
//                    '<a class="btn modify float-left" href="/calendar/modify.php?eventID=' . $currentEvent->getEventId(
//                    ) . '&returnTo=month"><span></span>Modify</a>'
//                );
//            } else {
//                $this->setVar('MODIFY', '');
//            }
//
//            /* Event attendance
//             */
//            if ($currentEvent->canAttend()) {
//                $this->parse('attendance_icon_block', 'attendance_icon');
//
//                if ($this->getGroup()->isStudent($_SESSION['id'])) {
//                    if ($attendance = $this->getCalendar()->getAttendanceByEventAndUser(
//                        $currentEvent->getEventId(), $_SESSION['id']
//                    )
//                    ) {
//
//                        /* Attendance Status
//                         */
//                        if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_PENDING) {
//                            $this->setVar('ATTENDANCE_STATUS', 'Pending');
//                        } else {
//                            if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_APPROVED) {
//                                $this->setVar('ATTENDANCE_STATUS', 'Approved');
//                            } else {
//                                if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_REJECTED) {
//                                    $this->setVar('ATTENDANCE_STATUS', 'Rejected');
//                                }
//                            }
//                        }
//
//                        $this->parse('attendance_status_block', 'attendance_status');
//
//                        $this->setVar('ATTENDANCE_REQUEST_ACTION', 'Unattend');
//                    } else {
//                        $this->setVar('ATTENDANCE_REQUEST_ACTION', 'Attend');
//                    }
//
//                    $this->parse('attendance_request_button_block', 'attendance_request_button');
//                } else {
//                    if ($this->getGroup()->isStaff($_SESSION['id'])) {
//                        $this->parse('attendance_list_button_block', 'attendance_list_button');
//                    }
//                }
//            }
//
//            /* Show a homepage if appropriate
//             */
//            if ($currentEvent->getCategoryId() > 0) {
//                $this->setVar(
//                    array(
//                        'HAS_CATEGORY_ID' => $currentEvent->getCategoryId(),
//                        'HAS_CATEGORY_NAME' => $currentEvent->getCategoryName(),
//                    )
//                );
//
//                $this->parse('has_category_block', 'has_category');
//            }
//            $this->parse("note_listing", "note_block", true);
//        }

        return true;
    }

    public function setCalendar($calendar) {
        $this->calendar = $calendar;
    }

    public function getCalendar() {
        return $this->calendar;
    }

    public function setGroup($group) {
        $this->group = $group;
    }

    public function getGroup() {
        return $this->group;
    }

}
