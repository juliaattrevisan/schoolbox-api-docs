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
		$this->setVar("note_listing", "");

		$userHandler = $this->user;
		$groupHandler = $this->group;

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

		$iterator = $this->eventList->createIterator();

		for ($iterator->rewind(); $iterator->valid(); $iterator->next()) {
			$currentEvent = $iterator->current();

			if ($currentEvent->getStartTime() == "0:00" || strtolower($currentEvent->getStartTime()) == "12:00am" || strtolower($currentEvent->getStartTime()) == "12:00 am" || $currentEvent->getStartTime() == '') {
				$this->setVar("TIME", "");
			}
			else if ($currentEvent->getEndTime() == "0:00" || strtolower($currentEvent->getEndTime()) == "12:00am" || strtolower($currentEvent->getEndTime()) == "12:00 am" || $currentEvent->getEndTime() == '') {
				$this->setVar("TIME", $currentEvent->getStartTime());
			}
			else {
				$this->setVar("TIME", $currentEvent->getStartTime()." - ".$currentEvent->getEndTime());
			}

			$this->setVar(array(
				'EVENT_ID' => $currentEvent->getEventId(),
				'TIME' => $currentEvent->getDerivedTime(),
				'NOTE' => $currentEvent->getNote(),
				'LOCATION' => $currentEvent->getLocation(),
				'DETAIL' => $currentEvent->getDetail(),
				'COLOUR' => $currentEvent->getColour(),
				'EVENT_TYPE' => 'type' . $currentEvent->getType(),
				'TYPE' => $currentEvent->getEventType(),
				'AUTHOR' => $currentEvent->getAuthor(),
				'AUTHOR_ID' => $currentEvent->getAuthorId(),
			));

			$this->setVar(array(
				'MODIFY' => $this->getCalendar()->getPerm($currentEvent->getEventId()) > 1 ? "<a href=\"/calendar/modify.php?eventID=" . $currentEvent->getEventId() . "&returnTo=month\" title=\"Modify\">Modify</a>" : '',
			));

			/* Event attendance
			 */
			if ($currentEvent->canAttend()) {
				$this->parse('attendance_icon_block', 'attendance_icon');

				if ($this->getGroup()->isStudent($_SESSION['id'])) {
					if ($attendance = $this->getCalendarHandler()->getAttendanceByEventAndUser($currentEvent->getEventId(), $_SESSION['id'])) {

						/* Attendance Status
						 */
						if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_PENDING) {
							$this->setVar('ATTENDANCE_STATUS', 'Pending');
						}
						else if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_APPROVED) {
							$this->setVar('ATTENDANCE_STATUS', 'Approved');
						}
						else if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_REJECTED) {
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
				else if ($this->getGroup()->isStaff($_SESSION['id'])) {
					$this->parse('attendance_list_button_block', 'attendance_list_button');
				}
			}

			$this->parse("note_listing", "note_block", true);
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
