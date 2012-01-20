<?php

$attendanceController = new Event_Attendance_Controller($this);

$method = $this->arg[0];

if ( $method == 'list' && is_numeric($this->arg[1]) ) {
    $eventId = $this->arg[1];

    $data = $attendanceController->executeAttendeeList($eventId);
    $attendanceController->renderAttendeeList($data);
}

elseif ( $method == 'processModifyAttendanceList' && !empty($_POST) ) {
    $data = $_POST;

    $attendanceController->processModifyAttendanceList($data);
}

elseif ( $method == 'processRemoveAttendance' && is_numeric($this->arg[1]) ) {
    $attendanceId = $this->arg[1];

    $attendanceController->processRemoveAttendance($attendanceId);
}