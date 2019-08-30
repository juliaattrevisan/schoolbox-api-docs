<?php
/** @var SitePage $this */

use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\Event\Access;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access as FolderAccess;

$attendanceController = new Event_Attendance_Controller($this);

/**
 * @param $eventId
 * @return bool
 * @throws Exception
 */
$isPermittedToModify = static function ($eventId)
    {
        $access = new Access(new FolderAccess());
        $eventMapper = new EventMapper;
        try {
            $event = $eventMapper->findById($eventId);
        } catch (MapperException $e) {
            error(ErrorMapper::ERROR_EVENT_NOT_FOUND);
            exit;
        }
        if ($access->checkExisting($_SESSION['user'], $event)){
            return true;
        }
        return false;
    };
$method = $this->arg[0];

if ($method == 'list' && is_numeric($this->arg[1])) {
    $eventId = $this->arg[1];

    $data = $attendanceController->executeAttendeeList($eventId);
    $attendanceController->renderAttendeeList($data);
}

elseif ( $method == 'processModifyAttendanceList' && !empty($_POST) ) {
    $data = $_POST;

    if ($isPermittedToModify($data['event']['id'])) {
        $attendanceController->processModifyAttendanceList($data);
    } else {
        error(ErrorMapper::ERROR_PERM_EVENT_MODIFY);
    }
}

elseif ( $method == 'processRemoveAttendance' && is_numeric($this->arg[1]) && is_numeric($this->arg[2])) {
    $eventId = $this->arg[1];
    $userId = $this->arg[2];

    if ($isPermittedToModify($eventId)) {
        \Schoolbox\Calendar\EventMapper::getInstance()->removeAttendance($eventId, $userId);
    } else {
        error(ErrorMapper::ERROR_PERM_EVENT_MODIFY);
    }

    header('Location: '."/calendar/attendance/list/".$eventId);
    exit;
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
} else {
    $this->pageNotFound();
}

/**
 * @param $eventId
 * @return bool
 * @throws Exception
 */
function isPermittedToModify($eventId)
{
    $access = new Access(new FolderAccess());
    $eventMapper = new EventMapper;
    try {
        $event = $eventMapper->findById($eventId);
    } catch (MapperException $e) {
        error(ErrorMapper::ERROR_EVENT_NOT_FOUND);
        exit;
    }
    if ($access->checkExisting($_SESSION['user'], $event)){
        return true;
    }
    return false;
}
