<?php
/** @var SitePage $this */

use Schoolbox\Calendar\EventMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Calendar\Event\Access as EventAccess;
use Schoolbox\Permission\Access\Access as FolderAccess;

$attendanceController = new Event_Attendance_Controller($this, new EventAccess(new FolderAccess()) );

/**
 * @param int $eventId
 * @return object $event
 * @throws Exception
 */
$findEvent = function (int $eventId){
    $eventMapper = new EventMapper;
    try {
        return $eventMapper->findById($eventId);
    } catch (MapperException $e) {
        error(ErrorMapper::ERROR_EVENT_NOT_FOUND);
        exit;
    }
};

/**
 * @param $eventId
 * @return bool
 * @throws Exception
 */
$method = $this->arg[0];

if ($method == 'list' && is_numeric($this->arg[1])) {
    $event = $findEvent($this->arg[1]);
    if ($attendanceController->isPermitted($_SESSION['user'], $event)) {
        $data = $attendanceController->executeAttendeeList($this->arg[1]);
        $attendanceController->renderAttendeeList($data);
    } else {
        error(ErrorMapper::ERROR_PERM_EVENT_MODIFY);
    }
} elseif ($method == 'processModifyAttendanceList' && !empty($_POST)) {
    $data = $_POST;
    $event = $findEvent($data['event']['id']);

    if ($attendanceController->isPermitted($_SESSION['user'], $event)) {
        $attendanceController->processModifyAttendanceList($data);
    } else {
        error(ErrorMapper::ERROR_PERM_EVENT_MODIFY);
    }
} elseif ($method == 'processRemoveAttendance' && is_numeric($this->arg[1]) && is_numeric($this->arg[2])) {
    $eventId = $this->arg[1];
    $userId = $this->arg[2];
    $event = $findEvent($eventId);

    if ($attendanceController->isPermitted($_SESSION['user'], $event)) {
        EventMapper::getInstance()->removeAttendance($eventId, $userId);
    } else {
        error(ErrorMapper::ERROR_PERM_EVENT_MODIFY);
    }

    header('Location: ' . "/calendar/attendance/list/" . $eventId);
    exit;
} /* Show all upcoming events with the number of attendees
 */
else if ($method == 'upcomingEvents') {
    $event = $findEvent($this->arg[1]);
    if ($attendanceController->isPermitted($_SESSION['user'], $event)) {
        $data = $attendanceController->executeUpcomingEventAttendance();

        $attendanceController->renderUpcomingEventAttendance($data);
    } else {
        error(ErrorMapper::ERROR_PERM_EVENT_MODIFY);
    }
} /* A csv export of all people pending for a particular event
 */
else if ($method == 'exportPending') {
    $event = $findEvent($this->arg[1]);
    if ($attendanceController->isPermitted($_SESSION['user'], $event)) {
        $data = $attendanceController->executePending($this->arg[1]);

        $attendanceController->renderExportCSV($data);
    } else {
        error(ErrorMapper::ERROR_PERM_EVENT_MODIFY);
    }
} /* A csv export of all people attending a particular event
 */
else if ($method == 'exportAttending') {
    $event = $findEvent($this->arg[1]);
    if ($attendanceController->isPermitted($_SESSION['user'], $event)) {
        $data = $attendanceController->executeAttending($this->arg[1]);

        $attendanceController->renderExportCSV($data);
    } else {
        error(ErrorMapper::ERROR_PERM_EVENT_MODIFY);
    }
} /* A csv export of all people rejected from a particular event
 */
else if ($method == 'exportRejected') {
    $event = $findEvent($this->arg[1]);
    if ($attendanceController->isPermitted($_SESSION['user'], $event)) {
        $data = $attendanceController->executeRejected($this->arg[1]);

        $attendanceController->renderExportCSV($data);
    } else {
        error(ErrorMapper::ERROR_PERM_EVENT_MODIFY);
    }
} else {
    $this->pageNotFound();
}
