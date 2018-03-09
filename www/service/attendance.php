<?php

$serviceController = new Event_Attendance_ServiceController($this);

$method = $this->arg[0];

if ( $method === 'setAttendance' ) {
    $data = $_POST;

    echo $serviceController->setAttendance($data);
}