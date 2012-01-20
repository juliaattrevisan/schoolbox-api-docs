<?php

$serviceController = new Event_Attendance_ServiceController($this);

$method = $this->arg[0];

if ( $method == 'processToggleAttendance' ) {
    $data = $_POST;

    echo $serviceController->processToggleAttendance($data);
}