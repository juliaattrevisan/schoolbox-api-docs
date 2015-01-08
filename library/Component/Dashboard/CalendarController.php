<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Component_Dashboard_CalendarController extends Component_Dashboard_ControllerAbstract
{

    protected $calendar;

    protected $group;

    /**
    * @var Alaress_Date_Format
    */
    protected $start;

    /**
    * @var Alaress_Date_Format
    */
    protected $end;

    /**
    * @var Calendar_Event_Collection|Calendar_Event_Type_Abstract[]
    */
    protected $eventList;

    public function __construct(Component_Instance $instance, \Schoolbox\User $user, SitePage $sitepage)
    {
        parent::__construct($instance, $user, $sitepage);

        $this->setCalendar(new Calendar());

        $this->setGroup(new Group());
    }

    /**
    * Get all the events you need to pump into the page
    *
    * @return bool|void
    */
    public function execute()
    {
        $now = new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');
        $this->start = new Alaress_Date_Format($now->getDatabaseDate() . ' 00:00:00', 'y-m-d h:i:s');
        $this->end   = new Alaress_Date_Format($now->getDatabaseDate() . ' 00:00:00', 'y-m-d h:i:s');
        $days  = $this->getConfig('days');

        /* Get 30 days of events by default
         */
        if ($days <= 0) {
            $days = 30;
        }

        $this->end->addDays($days);

        /* A list of submissions
         */
        $eventFactory = new Calendar_Api_Factory($this->user);

        $composite = $eventFactory->produce();

        $this->eventList = $composite->getEvents($this->start->getUnixTimestamp(), $this->end->getUnixTimestamp());

        return true;
    }

    public function renderTemplate($templateHandle)
    {
        $this->setVar("note_listing", "");

        if (!$this->loadTemplate($templateHandle, 'calendar')) {
            return false;
        }

        $this->setBlock($templateHandle, 'day', 'day_block');
        $this->setBlock('day', "note_block", "note_listing");
        $this->setBlock('day', "no_events", "no_events_block");

        $this->setBlock("note_block", "has_category", "has_category_block");
        $this->setBlock("note_block", "attendance_icon", "attendance_icon_block");
        $this->setBlock("note_block", "attendance_status", "attendance_status_block");
        $this->setBlock("note_block", "attendance_request_button", "attendance_request_button_block");
        $this->setBlock("note_block", "attendance_list_button", "attendance_list_button_block");
        $this->setBlock("note_block", "modify_button", "modify_button_block");


        /* Initialise, with date default set so no {VARIABLES} show when there's no events
         */
        $this->setVar(
            array(
                'attendance_icon_block' => '',
                'attendance_status_block' => '',
                'attendance_request_button_block' => '',
                'attendance_list_button_block' => '',
                'modify_button_block' => '',
                'has_category_block' => '',
                'DATE' => date('l jS F', $_SERVER['REQUEST_TIME']),
                'CALENDAR_DATE' => date('d-m-Y', $_SERVER['REQUEST_TIME']),
            )
        );

        $prevDay = false;

        $j = 0;
        if (count($this->eventList) === 0) {
            $this->parse('no_events_block', 'no_events');
        }
        foreach ($this->eventList as $currentEvent) {
            $this->setVar('has_category_block', '');
            $this->setVar('no_events_block', '');

            //
            // Current day: will never be before the start time
            $currentDay = $currentEvent->getStartTime();
            if ($this->start->compare($currentDay) > 0) {
                $currentDay = $this->start;
            }
            if ($prevDay != false && $currentDay->getDateOnly() != $prevDay->getDateOnly()) {
                $this->parse('day_block', 'day', true);
                $this->setVar('note_listing', '');
            }

            $this->setVar('LIST_DATE', $currentDay->getDateOnly());

            $eventOutput = array(
                'DATE' => $currentDay->getUsingFormat('l jS F'),
                'CALENDAR_DATE' => $currentDay->getUsingFormat('d-m-Y'),
                'TIME' => $currentEvent->getEventSpan(),
                'FULL_TIME' => $currentEvent->getFullDate(),
                'LOCATION' => Scrub::text($currentEvent->getLocation()),
                'NOTE' => Scrub::text($currentEvent->getTitle()),
                'DETAIL' => Scrub::html($currentEvent->getDetail()),
                'COLOUR' => $currentEvent->getColour(),
                'HAS_CATEGORY_ID' => $currentEvent->getCategoryId(),
                'HAS_CATEGORY_NAME' => Scrub::text($currentEvent->getCategoryName()),
                'TYPE' => $currentEvent->getEventType(),
                'AUTHOR' => Scrub::text($currentEvent->getAuthorName()),
                'AUTHOR_ID' => $currentEvent->getAuthorId(),
                'ON_OFF' => ++$j % 2 ? 'dark' : '',
                'EVENT_VISIBLE' => '',
                'EVENT_ID' => $currentEvent->getEventId(),
                'EVENT_LEVEL' => '',
                'EVENT_TYPE' => 'type' . $currentEvent->getType(),
                'modify_button_block' => '',
                "has_category_block" => '',
                "attendance_icon_block" => '',
                "attendance_status_block" => '',
                "attendance_request_button_block" => '',
                "attendance_list_button_block" => '',
            );
            if ($currentEvent instanceof Calendar_Event_Type_Submission) {
                $eventOutput['EVENT_ID']   = '';
                $eventOutput['EVENT_TYPE'] = 'submission';
            } elseif ($currentEvent instanceof Calendar_Event_Type_ResourceBooking) {
                $eventOutput['EVENT_ID'] = '';
                $eventOutput['TYPE'] = 'Booking';

                // Finagle better notes or details
                if (strlen($eventOutput['NOTE']) === 0) {
                    $eventOutput['NOTE'] = Scrub::text(
                        'Booking: '.$currentEvent->getBooking()->getResource()->getName()
                    );
                } else {
                    $eventOutput['DETAIL'] = sprintf(
                        '(Booking for: %s)<br>%s',
                        Scrub::text($currentEvent->getBooking()->getResource()->getName()),
                        $eventOutput['DETAIL']
                    );
                }

                // Add the status to the note/title
                $eventOutput['NOTE'] = sprintf(
                    '(%s) %s',
                    Scrub::text($currentEvent->getBooking()->getStatus()->getName()),
                    $eventOutput['NOTE']
                );
            }
            $this->setVar($eventOutput);

            if ($currentEvent instanceof Calendar_Event_Type_Submission) {
                $isAssessment = $currentEvent->getIsAssessment();
                if ($this->getGroup()->isStudent($_SESSION['id'])) {
                    $this->setVar('MODIFY_ACTION', 'View Details');
                    $this->setVar('MODIFY_TYPE', 'modify');
                    $this->setVar(
                        'MODIFY_HREF',
                        $isAssessment ?
                        ('/assess/intro/'.$currentEvent->getSubmissionBoxId()):
                        ('/submission/submissionHistory/'.$currentEvent->getSubmissionBoxId() . '/' . $_SESSION['id'])
                    );
                    $this->parse('modify_button_block', 'modify_button');
                } elseif ($this->getGroup()->isStaff($_SESSION['id'])) {
                    $this->setVar('MODIFY_ACTION', 'View Submissions');
                    $this->setVar('MODIFY_TYPE', 'modify');
                    $this->setVar(
                        'MODIFY_HREF',
                        $isAssessment ?
                        ('/assess/results/'.$currentEvent->getSubmissionBoxId()):
                        ('/submission/viewSubmission/'.$currentEvent->getSubmissionBoxId())
                    );
                    $this->parse('modify_button_block', 'modify_button');
                }
                $this->parse('has_category_block', 'has_category');
            } elseif ($currentEvent instanceof Calendar_Event_Type_ResourceBooking) {
                // Just go to the booking for that day
                $this->setVar('MODIFY_ACTION', 'View Booking');
                $this->setVar('MODIFY_TYPE', 'view');
                $this->setVar(
                    'MODIFY_HREF',
                    sprintf(
                        '/resource-booking/item/%s?date=%s',
                        $currentEvent->getBooking()->getResource()->getId(),
                        $currentEvent->getBooking()->getEvent()->getStartDateTime()->getUsingFormat('d-m-Y')
                    )
                );
                $this->parse('modify_button_block', 'modify_button');
            } else {
                if ($currentEvent->getIsEditable()) {
                    $this->setVar('MODIFY_ACTION', 'Modify');
                    $this->setVar('MODIFY_TYPE', 'modify');
                    $this->setVar(
                        'MODIFY_HREF',
                        '/calendar/modify.php?eventID=' . $currentEvent->getEventId()
                    );
                    $this->parse('modify_button_block', 'modify_button');
                }

                /* Event attendance
                 */
                if ($currentEvent->canAttend()) {
                    $this->parse('attendance_icon_block', 'attendance_icon');

                    if ($this->getGroup()->isStudent($_SESSION['id'])) {
                        if ($attendance = $this->getCalendar()->getAttendanceByEventAndUser(
                            $currentEvent->getEventId(),
                            $_SESSION['id']
                        )) {
                            /* Attendance Status
                             */
                            if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_PENDING) {
                                $this->setVar('ATTENDANCE_STATUS', 'Pending');
                            } elseif ($attendance['status'] == Calendar::ATTENDANCE_STATUS_APPROVED) {
                                $this->setVar('ATTENDANCE_STATUS', 'Approved');
                            } elseif ($attendance['status'] == Calendar::ATTENDANCE_STATUS_REJECTED) {
                                $this->setVar('ATTENDANCE_STATUS', 'Rejected');
                            } else {
                                $this->setVar('ATTENDANCE_STATUS', '');
                            }

                            $this->parse('attendance_status_block', 'attendance_status');

                            $this->setVar('ATTENDANCE_REQUEST_ACTION', 'Unattend');
                        } else {
                            $this->setVar('ATTENDANCE_REQUEST_ACTION', 'Attend');
                        }

                        $this->parse('attendance_request_button_block', 'attendance_request_button');
                    } elseif ($this->getGroup()->isStaff($_SESSION['id'])) {
                        $this->parse('attendance_list_button_block', 'attendance_list_button');
                    }
                }

                /* Show a homepage if appropriate
                 */
                if ($currentEvent->getCategoryId() > 0) {
                    $this->setVar(
                        array(
                            'HAS_CATEGORY_ID' => $currentEvent->getCategoryId(),
                            'HAS_CATEGORY_NAME' => $currentEvent->getCategoryName(),
                        )
                    );

                    $this->parse('has_category_block', 'has_category');
                }

            }


            $this->parse("note_listing", "note_block", true);

            $prevDay = $currentDay;
        }


        $this->parse('day_block', 'day', true);

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
