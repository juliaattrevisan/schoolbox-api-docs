<?php

/**
 * @deprecated - did this ever work??
 * Class Event_Attendance_ServiceController
 */
class Event_Attendance_ServiceController extends Controller
{

    private $calendarHandler;


    public function __construct($page)
    {
        parent::__construct($page);

        $this->getPage()->setAjax(true);

        $this->setCalendarHandler(new Calendar);
    }


    public function setAttendance($data)
    {

        $eventMapper = \Schoolbox\Calendar\EventMapper::getInstance();

        if ($data['status'] === 'add') {
            try {
                $eventMapper->addAttendance($data['event']['id'], $_SESSION['id']);
                return json_encode("Attendance for event " . $data['event']['id'] . " added.");
            } catch (PDOException $e) {
                return json_encode("No event with id: " . $data['event']['id'] . " exists.");
            }
        } elseif ($data['status'] === 'remove') {
            try {
                $eventMapper->removeAttendance($data['event']['id'], $_SESSION['id']);
                return json_encode("Attendance for event " . $data['event']['id'] . " removed.");
            } catch (Exception $e) {

            }
        }

        return json_encode("No valid status was given.");
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
