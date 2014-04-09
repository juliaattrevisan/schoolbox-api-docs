<?php

/**
 * @deprecated - did this ever work??
 * Class Event_Attendance_ServiceController
 */
class Event_Attendance_ServiceController extends Controller
{
    private $calendarHandler;

    public function __construct ($page) {
        parent::__construct($page);

        $this->getPage()->setAjax(true);

        $this->setCalendarHandler( new Calendar );
    }

    public function processToggleAttendance ($data) {
        $event = $this->getCalendarHandler()->getByID($data['event']['id']);

        if ( !is_array($event) ) {
            return json_encode( array(
                'error' => 34 // @todo Create error message for event not found
            ) );
        }

        $attendance = $this->getCalendarHandler()->getAttendanceByEventAndUser($event['id'], $_SESSION['id']);

        if ( !is_array($attendance) ) {
            $attendance = array(
                'event_id' => $event['id'],
                'user_id' => $_SESSION['id'],
                'status' => Calendar::ATTENDANCE_STATUS_PENDING,
                'intention' => Calendar::ATTENDANCE_INTENTION_PRESENT
            );

            $this->getCalendarHandler()->createAttendance($attendance);
        }
        else {
            $this->getCalendarHandler()->removeAttendance($attendance['id']);
        }

        return json_encode( array() );
    }

    /**
     * @param Calendar $calendarHandler
     */
    public function setCalendarHandler($calendarHandler)
    {
        $this->calendarHandler = $calendarHandler;
    }

    /**
     * @return Calendar
     */
    public function getCalendarHandler()
    {
        return $this->calendarHandler;
    }
}
