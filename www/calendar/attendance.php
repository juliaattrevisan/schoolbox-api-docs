<?php

$attendanceController = new Event_Attendance_Controller($this);

$method = $this->arg[0];

if ($method == 'list' && is_numeric($this->arg[1])) {
    $eventId = $this->arg[1];

    $data = $attendanceController->executeAttendeeList($eventId);
    $attendanceController->renderAttendeeList($data);
}

elseif ( $method == 'processModifyAttendanceList' && !empty($_POST) ) {
    $data = $_POST;

    $attendanceController->processModifyAttendanceList($data);
}

elseif ( $method == 'processRemoveAttendance' && is_numeric($this->arg[1]) && is_numeric($this->arg[2])) {
    $eventId = $this->arg[1];
    $userId = $this->arg[2];

    \Schoolbox\Calendar\EventMapper::getInstance()->removeAttendance($eventId, $userId);

    header('Location: '."/calendar/attendance/list/".$eventId);
}

/* Show all upcoming events with the number of attendees
 */
else if ($method == 'upcomingEvents') {
	$data = $attendanceController->executeUpcomingEventAttendance();

	$attendanceController->renderUpcomingEventAttendance($data);
}
/* A csv export of all people pending for a particular event
 */
else if ($method == 'exportPending') {
	$data = $attendanceController->executePending($this->arg[1]);

	$attendanceController->renderExportCSV($data);
}
/* A csv export of all people attending a particular event
 */
else if ($method == 'exportAttending') {
	$data = $attendanceController->executeAttending($this->arg[1]);

	$attendanceController->renderExportCSV($data);
}
/* A csv export of all people rejected from a particular event
 */
else if ($method == 'exportRejected') {
	$data = $attendanceController->executeRejected($this->arg[1]);

	$attendanceController->renderExportCSV($data);
}