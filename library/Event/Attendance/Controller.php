<?php

class Event_Attendance_Controller extends Controller
{
    private $calendarHandler;

    private $userHandler;

    private $leaveRequestMapper;

    private $leaveController;

    public function __construct ($page = null) {
        parent::__construct($page);

        $this->setCalendarHandler( new Calendar );

        $this->setUserHandler( new User );

        $this->setLeaveRequestMapper( Leave_RequestMapper::getInstance() );

        $this->setLeaveController( new Leave_Controller );
    }

    public function executeAttendeeList ($eventId) {
        $data = array();

        if ( $event = $this->getCalendarHandler()->getByID($eventId) ) {
            $data['event'] = $event;
        }

        if ( $attendeeList = $this->getCalendarHandler()->getAttendeeList($eventId) ) {
            $data['attendeeList'] = $attendeeList;
        }

        return $data;
    }

    public function renderAttendeeList ($data) {
        $event = $data['event'];
        $attendeeList = $data['attendeeList'];

        $this->getPage()->setFile(TEMPLATE_content, 'calendar/attendance.html');

        $this->getPage()->setBlock(TEMPLATE_content, 'no_attendees', 'no_attendees_block');

        $this->getPage()->setBlock(TEMPLATE_content, 'pending_attendee_list', 'pending_attendee_list_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'approved_attendee_list', 'approved_attendee_list_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'rejected_attendee_list', 'rejected_attendee_list_block');

        $this->getPage()->setBlock('pending_attendee_list', 'pending_attendee', 'pending_attendee_block');
        $this->getPage()->setBlock('approved_attendee_list', 'approved_attendee', 'approved_attendee_block');
        $this->getPage()->setBlock('rejected_attendee_list', 'rejected_attendee', 'rejected_attendee_block');

        if ( is_array($event) ) {

            $this->getPage()->setVar( array(
                'EVENT_ID' => $event['id'],
                'EVENT_NAME' => $event['note'],
                'EVENT_DATE' => $event['date']
            ) );

            if ( is_array($attendeeList) ) {
                $this->getPage()->setVar('no_attendees_block', '');

                $pendingAttendeeCount = 0;
                $approvedAttendeeCount = 0;
                $rejectedAttendeeCount = 0;

                foreach ( $attendeeList as $attendee ) {
                    $this->getPage()->setVar( array(
                        'USER_NAME' => $this->getUserHandler()->formatName($attendee),
                        'USER_YEAR' => $attendee['year'],
                        'ATTENDANCE_ID' => $attendee['id']
                    ) );

                    if ( $attendee['status'] == Calendar::ATTENDANCE_STATUS_PENDING ) {
                        $this->getPage()->setVar( array(
                            'CLASS' => $pendingAttendeeCount % 2 ? '' : 'dark',
                        ) );

                        $this->getPage()->parse('pending_attendee_block', 'pending_attendee', true);

                        $pendingAttendeeCount++;
                    }

                    elseif ( $attendee['status'] == Calendar::ATTENDANCE_STATUS_APPROVED ) {
                        $this->getPage()->setVar( array(
                            'CLASS' => $approvedAttendeeCount % 2 ? '' : 'dark',
                        ) );

                        $this->getPage()->parse('approved_attendee_block', 'approved_attendee', true);

                        $approvedAttendeeCount++;
                    }

                    elseif ( $attendee['status'] == Calendar::ATTENDANCE_STATUS_REJECTED ) {
                        $this->getPage()->setVar( array(
                            'CLASS' => $rejectedAttendeeCount % 2 ? '' : 'dark',
                        ) );

                        $this->getPage()->parse('rejected_attendee_block', 'rejected_attendee', true);

                        $rejectedAttendeeCount++;
                    }
                }

                if ( $pendingAttendeeCount ) {
                    $this->getPage()->parse('pending_attendee_list_block', 'pending_attendee_list');
                }
                else {
                    $this->getPage()->setVar('pending_attendee_list_block', '');
                }

                if ( $approvedAttendeeCount ) {
                    $this->getPage()->parse('approved_attendee_list_block', 'approved_attendee_list');
                }
                else {
                    $this->getPage()->setVar('approved_attendee_list_block', '');
                }

                if ( $rejectedAttendeeCount ) {
                    $this->getPage()->parse('rejected_attendee_list_block', 'rejected_attendee_list');
                }
                else {
                    $this->getPage()->setVar('rejected_attendee_list_block', '');
                }
            }
            else {
                $this->getPage()->parse('no_attendees_block', 'no_attendees');

                $this->getPage()->setVar('pending_attendee_list_block', '');
                $this->getPage()->setVar('approved_attendee_list_block', '');
                $this->getPage()->setVar('rejected_attendee_list_block', '');
            }

        }
        else {
            $this->getPage()->error(34); // @todo Create error for event not found
        }

    }

    public function processModifyAttendanceList ($data) {
        $event = $this->getCalendarHandler()->getByID($data['event']['id']);

        $attendanceList = $data['attendanceList'];

        if ( !is_array($event) ) {
            $this->getPage()->error(34); // @todo Create error for event not found
        }

        if ( is_array($attendanceList) && !empty($attendanceList) ) {
            foreach ( $attendanceList as $attendance ) {
                $attendance = $this->getCalendarHandler()->getAttendance($attendance['id']);

                if ( isset($data['approve']) ) {
                    $attendance['status'] = Calendar::ATTENDANCE_STATUS_APPROVED;

                    $this->createLeaveRequest($event, $attendance);
                }
                elseif ( isset($data['reject']) ) {
                    $attendance['status'] = Calendar::ATTENDANCE_STATUS_REJECTED;
                }

                $this->getCalendarHandler()->updateAttendance($attendance);
            }

            $this->getPage()->localRedir("/calendar/attendance/list/{$event['id']}");
        }
    }

    private function createLeaveRequest ($event, $attendance) {
        $start = new Alaress_Date_Format("{$event['date']} {$event['startTime']}", 'y-m-d');
        $finish = new Alaress_Date_Format("{$event['date']} {$event['endTime']}", 'y-m-d');

        $created = new Alaress_Date_Format( time(), 'U');

        $request = new Leave_Request;

        $request->setUserId($attendance['user_id']);
        $request->setStart( $start->getDatabase() );
        $request->setFinish( $finish->getDatabase() );
        $request->setHostType($request->getHostTypeMapIndex('Other'));
        $request->setHostName( $this->getUserHandler()->formatName($event) );
        $request->setHostPhone('');
        $request->setUserPhone('');
        $request->setDescription($event['note']);
        $request->setStatus(Leave_Request::STATUS_PENDING);
        $request->setCreated( $created->getDatabase() );

        $this->getLeaveRequestMapper()->save($request);

        if ( $request = $this->getLeaveRequestMapper()->findById( $request->getId() ) ) {
            $this->getLeaveController()->sendParentNotice($request);
        }
    }

    public function processRemoveAttendance ($attendanceId) {
        $attendance = $this->getCalendarHandler()->getAttendance($attendanceId);

        if ( !is_array($attendance) ) {
            $this->getPage()->error(34); // @todo Create error for attendee not found
        }

        $event = $this->getCalendarHandler()->getByID($attendance['event_id']);

        if ( !is_array($event) ) {
            $this->getPage()->error(34); // @todo Create error for event not found
        }

        $this->getCalendarHandler()->removeAttendance($attendanceId);

        $this->getPage()->localRedir("/calendar/attendance/list/{$event['id']}");
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

    public function setLeaveRequestMapper($leaveRequestMapper)
    {
        $this->leaveRequestMapper = $leaveRequestMapper;
    }

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
}