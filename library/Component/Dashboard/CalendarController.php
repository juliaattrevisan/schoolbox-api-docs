<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 *
 */

use Calendar\Event\Collection\Lister;

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
     * @var Lister
     */
    protected $eventRenderer;

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
        $now         = Alaress_Date_Format::now();
        $this->start = $now->startOfDay();
        $this->end   = clone $this->start;
        $days        = $this->getConfig('days');

        /* Get 30 days of events by default
         */
        if ($days <= 0) {
            $days = 30;
        }

        $this->end->addDays($days);

        /* A list of submissions
         */
        $eventFactory = new Calendar_Api_Factory($this->user);
        $api = $eventFactory->produce();
        $this->eventRenderer = new Lister($api, $this->start->getUnixTimestamp(), $this->end->getUnixTimestamp(), $this->user, $this->sitepage);

        return true;
    }

    public function renderTemplate($templateHandle)
    {

        if (!$this->loadTemplate($templateHandle, 'calendar')) {
            return false;
        }

        $this->setBlock($templateHandle, 'day', 'day_block');
        $this->setBlock('day', 'note_block', 'note_listing');
        $this->setBlock('day', 'no_events', 'no_events_block');
        $this->setVar('no_events_block','');

        $this->setVar(
            array(
                'day_block'      => '',
                'LIST_DATE'      => $this->start->getLongDateOnly(),
                'DATE'           => $this->start->getLongDateOnly(),
                'CALENDAR_DATE'  => $this->start->getURLDate(),
            )
        );

        $prevDay = false;

        if (count($this->eventRenderer->getAllEvents()) === 0) {
            $this->setVar('note_listing', '');
            $this->parse('no_events_block', 'no_events');

        }
        foreach ($this->eventRenderer->getAllEvents() as $currentEvent) {

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

            $this->setVar('LIST_DATE', $currentDay->getLongDateOnly());

            $this->setVar('note_block', $this->eventRenderer->renderEvent($currentEvent, 'html'));

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

    public function old() {
        $eventOutput = array(
            'DATE'                            => $currentDay->getLongDateOnly(),
            'CALENDAR_DATE'                   => $currentDay->getURLDate(),
            'TIME'                            => $currentEvent->getEventSpan(),
            'FULL_TIME'                       => $currentEvent->getFullDate(),
            'LOCATION'                        => Scrub::text($currentEvent->getLocation()),
            'NOTE'                            => Scrub::text($currentEvent->getTitle()),
            'DETAIL'                          => Scrub::html($currentEvent->getDetail()),
            'COLOUR'                          => $currentEvent->getColour(),
            'HAS_CATEGORY_ID'                 => $currentEvent->getCategoryId(),
            'HAS_CATEGORY_NAME'               => Scrub::text($currentEvent->getCategoryName()),
            'TYPE'                            => $currentEvent->getEventType(),
            'AUTHOR'                          => Scrub::text($currentEvent->getAuthorName()),
            'AUTHOR_ID'                       => $currentEvent->getAuthorId(),
            'ON_OFF'                          => ++$j % 2 ? 'dark' : '',
            'EVENT_VISIBLE'                   => '',
            'EVENT_ID'                        => $currentEvent->getEventId(),
            'EVENT_LEVEL'                     => '',
            'EVENT_TYPE'                      => 'type' . $currentEvent->getType(),
            'modify_button_block'             => '',
            "has_category_block"              => '',
            "attendance_icon_block"           => '',
            "attendance_status_block"         => '',
            "attendance_request_button_block" => '',
            "attendance_list_button_block"    => '',
        );
        if ($currentEvent instanceof Calendar_Event_Type_Submission) {
            $eventOutput['EVENT_ID']   = '';
            $eventOutput['EVENT_TYPE'] = 'submission';
        } elseif ($currentEvent instanceof Calendar_Event_Type_ResourceBooking) {
            $eventOutput['EVENT_ID'] = '';
            $eventOutput['TYPE']     = 'Booking';

            // Finagle better notes or details
            if (strlen($eventOutput['NOTE']) === 0) {
                $eventOutput['NOTE'] = Scrub::text(
                    'Booking: ' . $currentEvent->getBooking()->getResource()->getName()
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
                        ('/assess/intro/' . $currentEvent->getSubmissionBoxId()) :
                        ('/submission/submissionHistory/' . $currentEvent->getSubmissionBoxId() . '/' . $_SESSION['id'])
                );
                $this->parse('modify_button_block', 'modify_button');
            } elseif ($this->getGroup()->isStaff($_SESSION['id'])) {
                $this->setVar('MODIFY_ACTION', 'View Submissions');
                $this->setVar('MODIFY_TYPE', 'modify');
                $this->setVar(
                    'MODIFY_HREF',
                    $isAssessment ?
                        ('/assess/results/' . $currentEvent->getSubmissionBoxId()) :
                        ('/submission/viewSubmission/' . $currentEvent->getSubmissionBoxId())
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
                    $currentEvent->getBooking()->getEvent()->getStartDateTime()->getURLDate()
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
                        'HAS_CATEGORY_ID'   => $currentEvent->getCategoryId(),
                        'HAS_CATEGORY_NAME' => $currentEvent->getCategoryName(),
                    )
                );

                $this->parse('has_category_block', 'has_category');
            }

        }

        /* Hide "posted by" if there is no author
         */
        if (is_numeric($currentEvent->getAuthorId()) && ($currentEvent->getAuthorId() > 0)) {
            $this->parse('has_posted_by_block', 'has_posted_by');
        }
    }
}
