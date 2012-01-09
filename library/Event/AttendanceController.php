<?php

class Event_AttendanceController extends Controller
{
    private $calendarHandler;

    public function __construct ($page = null) {
        parent::__construct($page);

        $this->setCalendarHandler( new Calendar );
    }

    public function executeList ($eventId) {

    }

    public function renderList ($data) {
        $this->getPage()->setFile(TEMPLATE_content, 'calendar/attendance.html');
    }

    public function setCalendarHandler($calendarHandler)
    {
        $this->calendarHandler = $calendarHandler;
    }

    public function getCalendarHandler()
    {
        return $this->calendarHandler;
    }
}