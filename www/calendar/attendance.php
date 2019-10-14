<?php
/** @var SitePage $this */

use Schoolbox\Calendar\EventMapper;
use Schoolbox\Error\Mapper as ErrorMapper;

$attendanceController = new Event_Attendance_Controller($this);

/**
 * @param $eventId
 * @return bool
 * @throws Exception
 */
$method = $this->arg[0];

if ($method == 'list' && is_numeric($this->arg[1])) {
    $eventId = $this->arg[1];
    if ($attendanceController->isPermitted($_SESSION['user'], $eventId)) {
        $data = $attendanceController->executeAttendeeList($eventId);
        $attendanceController->renderAttendeeList($data);
    } else {
        error(ErrorMapper::ERROR_PERM_EVENT_MODIFY);
    }
} elseif ($method == 'processModifyAttendanceList' && !empty($_POST)) {
    $data = $_POST;

    if ($attendanceController->isPermitted($_SESSION['user'], $data['event']['id'])) {
        $attendanceController->processModifyAttendanceList($data);
    } else {
        error(ErrorMapper::ERROR_PERM_EVENT_MODIFY);
    }
} elseif ($method == 'processRemoveAttendance' && is_numeric($this->arg[1]) && is_numeric($this->arg[2])) {
    $eventId = $this->arg[1];
    $userId = $this->arg[2];

    if ($attendanceController->isPermitted($_SESSION['user'], $eventId)) {
        EventMapper::getInstance()->removeAttendance($eventId, $userId);
    } else {
        error(ErrorMapper::ERROR_PERM_EVENT_MODIFY);
    }

    header('Location: ' . "/calendar/attendance/list/" . $eventId);
    exit;
} /* Show all upcoming events with the number of attendees
 */
else if ($method == 'upcomingEvents') {
    if ($attendanceController->isPermitted($_SESSION['user'], $this->arg[1])) {
        $data = $attendanceController->executeUpcomingEventAttendance();

        $attendanceController->renderUpcomingEventAttendance($data);
    } else {
        error(ErrorMapper::ERROR_PERM_EVENT_MODIFY);
    }
} /* A csv export of all people pending for a particular event
 */
else if ($method == 'exportPending') {
    if ($attendanceController->isPermitted($_SESSION['user'], $this->arg[1])) {
        $data = $attendanceController->executePending($this->arg[1]);

        $attendanceController->renderExportCSV($data);
    } else {
        error(ErrorMapper::ERROR_PERM_EVENT_MODIFY);
    }
} /* A csv export of all people attending a particular event
 */
else if ($method == 'exportAttending') {
    if ($attendanceController->isPermitted($_SESSION['user'], $this->arg[1])) {
        $data = $attendanceController->executeAttending($this->arg[1]);

        $attendanceController->renderExportCSV($data);
    } else {
        error(ErrorMapper::ERROR_PERM_EVENT_MODIFY);
    }
} /* A csv export of all people rejected from a particular event
 */
else if ($method == 'exportRejected') {
    if ($attendanceController->isPermitted($_SESSION['user'], $this->arg[1])) {
        $data = $attendanceController->executeRejected($this->arg[1]);

        $attendanceController->renderExportCSV($data);
    } else {
        error(ErrorMapper::ERROR_PERM_EVENT_MODIFY);
    }
} else {
    $this->pageNotFound();
}
