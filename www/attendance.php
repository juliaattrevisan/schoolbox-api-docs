<?php

$attendanceController = new Event_AttendanceController($this);

$method = $this->arg[0];

if ( $method == 'list' && is_numeric($this->arg[1]) ) {
    $eventId = $this->arg[1];

    $data = $attendanceController->executeList($eventId);
    $attendanceController->renderList($data);
}