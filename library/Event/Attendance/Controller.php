<?php

use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\Event\Access as EventAccess;

/**
 * @deprecated - did this ever work??
 * Class Event_Attendance_Controller
 */
class Event_Attendance_Controller extends Controller
{
    private $calendarHandler;

    private $userHandler;

    private $leaveRequestMapper;

    private $leaveController;

    /**
     * @var EventAccess
     */
    private $eventAccess;

    public function __construct(SitePage $page, EventAccess $eventAccess)
    {
        parent::__construct($page);

        $this->setCalendarHandler(new Calendar);

        $this->setUserHandler(new User);

        $this->eventAccess = $eventAccess;

        /* Check if Border Leave is enabled and existent first, before doing this
        */
        if ((Config::get('boarder_leave_enabled') == 1) || (Config::get('boarder_leave_enabled') == 'true')) {
            if (class_exists('Leave_RequestMapper') && class_exists('Leave_Controller')) {
                $this->setLeaveRequestMapper(Leave_RequestMapper::getInstance());
                $this->setLeaveController(new Leave_Controller);
            }
        }
    }

    /**
     * Get a list of all upcoming events with a summary of attendance statuses
     *
     * @return
     */
    public function executeUpcomingEventAttendance()
    {
        $data = $this->getCalendarHandler()->getUpcomingEventAttendance();

        return $data;
    }

    /**
     * Render a list of all upcoming events with a summary of attendance statuses
     *
     * @param $data
     */
    public function renderUpcomingEventAttendance($data)
    {
        $this->getPage()->setFile(TEMPLATE_content, 'calendar/upcomingeventattendance.html');
        $this->getPage()->setBlock(TEMPLATE_content, 'event', 'event_block');

        if (is_array($data) && count($data)) {
            foreach ($data as $currentEvent) {
                $eventDate = new Alaress_Date_Format($currentEvent['start_datetime'], Alaress_Date_Format::DATABASE_FORMAT);

                // check security perms
                if ($this->getCalendarHandler()->getPerm($currentEvent['id']) < 2) {
                    error(34);
                } else {
                    $this->getPage()->setVar([
                        'EVENT_ID'             => $currentEvent['id'],
                        'EVENT_TITLE'          => $currentEvent['title'],
                        'EVENT_DATE'           => $eventDate->getShort(),
                        'EVENT_LOCATION'       => $currentEvent['location'],
                        'EVENT_USER_FIRSTNAME' => $currentEvent['firstname'],
                        'EVENT_USER_SURNAME'   => $currentEvent['lastname'],
                        'EVENT_PENDING'        => $currentEvent['numPending'],
                        'EVENT_APPROVED'       => $currentEvent['numApproved'],
                    ]);
                }

                $this->getPage()->parse('event_block', 'event', true);
            }
        } else {
            $this->getPage()->setVar('event_block', 'event');
        }
    }

    public function executeAttendeeList($eventId)
    {
        $data = [];

        if ($event = $this->getCalendarHandler()->getByID($eventId)) {
            $data['event'] = $event;
        }

        if ($attendeeList = $this->getCalendarHandler()->getAttendeeList($eventId)) {
            $data['attendeeList'] = $attendeeList;
        }

        return $data;
    }

    /**
     * Get a list of attending people
     *
     * @param $eventId
     * @return array
     */
    public function executeAttending($eventId)
    {
        $data = [];

        if ($event = $this->getCalendarHandler()->getByID($eventId)) {
            $data['event'] = $event;
        }

        if ($attendeeList = $this->getCalendarHandler()->getAttending($eventId)) {
            $data['attendeeList'] = $attendeeList;
        }

        return $data;
    }

    /**
     * Get a list of pending people
     *
     * @param $eventId
     * @return array
     */
    public function executePending($eventId)
    {
        $data = [];

        if ($event = $this->getCalendarHandler()->getByID($eventId)) {
            $data['event'] = $event;
        }

        if ($attendeeList = $this->getCalendarHandler()->getPending($eventId)) {
            $data['attendeeList'] = $attendeeList;
        }

        return $data;
    }

    /**
     * Get a list of pending people
     *
     * @param $eventId
     * @return array
     */
    public function executeRejected($eventId)
    {
        $data = [];

        if ($event = $this->getCalendarHandler()->getByID($eventId)) {
            $data['event'] = $event;
        }

        if ($attendeeList = $this->getCalendarHandler()->getRejected($eventId)) {
            $data['attendeeList'] = $attendeeList;
        }

        return $data;
    }

    /**
     * Exports an event/list of people into a csv format
     *
     * @param $data
     */
    public function renderExportCSV($data)
    {
        $name = new Url($data['event']['title']);

        $headings = [
            'External ID',
            'Firstname',
            'Lastname',
            'Email',
            'Group',
            'Year Level',
        ];

        $allRows   = [];
        $allRows[] = TextFormatter::outputCSV($headings);

        if (is_array($data['attendeeList']) && count($data['attendeeList'])) {
            foreach ($data['attendeeList'] as $currentPerson) {

                $row = [
                    $currentPerson['synergy_id'],
                    $currentPerson['firstname'],
                    $currentPerson['lastname'],
                    $currentPerson['email'],
                    $currentPerson['groupName'],
                    $currentPerson['year'],
                ];

                $allRows[] = TextFormatter::outputCSV($row);
            }
        }

        header('Content-type: application/csv');
        header("Content-Disposition: attachment; filename={$name->getSlug()}.csv");
        header("Pragma: no-cache");
        header("Expires: 0");

        echo implode("\n", $allRows);
        exit(1);
    }

    public function renderAttendeeList($data)
    {
        $event        = $data['event'];
        $attendeeList = $data['attendeeList'];

        $this->getPage()->setFile(TEMPLATE_content, 'calendar/attendance.html');

        $this->getPage()->setBlock(TEMPLATE_content, 'no_attendees', 'no_attendees_block');

        $this->getPage()->setBlock(TEMPLATE_content, 'pending_attendee_list', 'pending_attendee_list_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'approved_attendee_list', 'approved_attendee_list_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'rejected_attendee_list', 'rejected_attendee_list_block');

        $this->getPage()->setBlock('pending_attendee_list', 'pending_attendee', 'pending_attendee_block');
        $this->getPage()->setBlock('approved_attendee_list', 'approved_attendee', 'approved_attendee_block');
        $this->getPage()->setBlock('rejected_attendee_list', 'rejected_attendee', 'rejected_attendee_block');

        if (is_array($event)) {

            $this->getPage()->setVar([
                'EVENT_ID'     => $event['id'],
                'EVENT_NAME'   => $event['title'],
                'EVENT_DATE'   => $event['date'],
                'EVENT_DETAIL' => $event['detail'],
            ]);

            if (is_array($attendeeList)) {
                $this->getPage()->setVar('no_attendees_block', '');

                $pendingAttendeeCount  = 0;
                $approvedAttendeeCount = 0;
                $rejectedAttendeeCount = 0;

                foreach ($attendeeList as $attendee) {
                    $this->getPage()->setVar([
                        'USER_NAME'         => $this->getUserHandler()->formatName($attendee),
                        'USER_ROLE'         => $attendee['groupName'],
                        'ATTENDANCE_ID'     => $attendee['id'],
                        'ATTENDING_USER_ID' => $attendee['user_id'],
                    ]);

                    if ($attendee['status'] == Event::ATTENDANCE_STATUS_PENDING) {
                        $this->getPage()->parse('pending_attendee_block', 'pending_attendee', true);

                        $pendingAttendeeCount++;
                    } elseif ($attendee['status'] == Event::ATTENDANCE_STATUS_APPROVED) {
                        $this->getPage()->parse('approved_attendee_block', 'approved_attendee', true);

                        $approvedAttendeeCount++;
                    } elseif ($attendee['status'] == Event::ATTENDANCE_STATUS_REJECTED) {
                        $this->getPage()->parse('rejected_attendee_block', 'rejected_attendee', true);

                        $rejectedAttendeeCount++;
                    }
                }

                if ($pendingAttendeeCount) {
                    $this->getPage()->parse('pending_attendee_list_block', 'pending_attendee_list');
                } else {
                    $this->getPage()->setVar('pending_attendee_list_block', '');
                }

                if ($approvedAttendeeCount) {
                    $this->getPage()->parse('approved_attendee_list_block', 'approved_attendee_list');
                } else {
                    $this->getPage()->setVar('approved_attendee_list_block', '');
                }

                if ($rejectedAttendeeCount) {
                    $this->getPage()->parse('rejected_attendee_list_block', 'rejected_attendee_list');
                } else {
                    $this->getPage()->setVar('rejected_attendee_list_block', '');
                }
            } else {
                $this->getPage()->parse('no_attendees_block', 'no_attendees');

                $this->getPage()->setVar('pending_attendee_list_block', '');
                $this->getPage()->setVar('approved_attendee_list_block', '');
                $this->getPage()->setVar('rejected_attendee_list_block', '');
            }

        } else {
            $this->getPage()->error(34); // @todo Create error for event not found
        }

    }

    public function processModifyAttendanceList($data)
    {
        $event = $this->getCalendarHandler()->getByID($data['event']['id']);

        $attendanceList = $data['attendanceList'];

        if (!is_array($event)) {
            $this->getPage()->error(34); // @todo Create error for event not found
        }

        if (is_array($attendanceList) && !empty($attendanceList)) {
            foreach ($attendanceList as $attendance) {
                $attendance = $this->getCalendarHandler()->getAttendance($attendance['id']);

                if (isset($data['approve'])) {
                    $attendance['status'] = Event::ATTENDANCE_STATUS_APPROVED;

                    /* Check if Border Leave is enabled first, before doing this
                     */
                    if ((Config::get('boarder_leave_enabled') == 1) || (Config::get('boarder_leave_enabled') == 'true')) {
                        $this->createLeaveRequest($event, $attendance);
                    }
                } elseif (isset($data['reject'])) {
                    $attendance['status'] = Event::ATTENDANCE_STATUS_REJECTED;
                }

                $this->getCalendarHandler()->updateAttendance($attendance);
            }

            $this->getPage()->localRedir("/calendar/attendance/list/{$event['id']}");
        }
    }

    private function createLeaveRequest($event, $attendance)
    {
        $start  = new Alaress_Date_Format($event['start_datetime'], Alaress_Date_Format::DATABASE_FORMAT);
        $finish = new Alaress_Date_Format($event['end_datetime'], Alaress_Date_Format::DATABASE_FORMAT);

        $created = new Alaress_Date_Format(time(), 'U');

        $request = new Leave_Request;

        $request->setUser($attendance['user_id']);
        $request->setAuthor($_SESSION['user']);
        $request->setStart($start->getDatabase());
        $request->setFinish($finish->getDatabase());
        $request->setHostType(Leave\HostType::TYPE_OTHER);
        $request->setHostName($this->getUserHandler()->formatName($event));
        $request->setHostPhone('');
        $request->setUserPhone('');
        $request->setDescription($event['title']);
        $request->setStatus(Leave_Request::STATUS_PENDING);
        $request->setCreated($created->getDatabase());

        $this->getLeaveRequestMapper()->save($request);

        try {
            $request = $this->getLeaveRequestMapper()->findById($request->getId());
            $this->getLeaveController()->sendParentNotice($request);
        } catch (Schoolbox\Mapper\Exception $e) {
        }
    }

    public function setCalendarHandler($calendarHandler)
    {
        $this->calendarHandler = $calendarHandler;
    }

    public function getCalendarHandler()
    {
        return $this->calendarHandler;
    }

    public function setUserHandler($userHandler)
    {
        $this->userHandler = $userHandler;
    }

    public function getUserHandler()
    {
        return $this->userHandler;
    }

    /**
     * @param Leave_RequestMapper $leaveRequestMapper
     */
    public function setLeaveRequestMapper($leaveRequestMapper)
    {
        $this->leaveRequestMapper = $leaveRequestMapper;
    }

    /**
     * @return Leave_RequestMapper
     */
    public function getLeaveRequestMapper()
    {
        return $this->leaveRequestMapper;
    }

    public function setLeaveController($leaveController)
    {
        $this->leaveController = $leaveController;
    }

    public function getLeaveController()
    {
        return $this->leaveController;
    }

    /**
     * Is user permitted to modify the event
     *
     * @param \Schoolbox\User $user
     * @param Event           $event
     * @return bool
     */
    public function isPermitted(Schoolbox\User $user, Event $event): bool
    {
        if ($this->eventAccess->checkExisting($user, $event)) {
            return true;
        }
        return false;
    }
}
