<?php
/** @var SitePage $this */

use Event\Attendance\CanModifyAttendance;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Calendar\Event\Access as EventAccess;
use Schoolbox\Permission\Access\Access as FolderAccess;

$attendanceController = new Event_Attendance_Controller($this, new EventAccess(new FolderAccess()));

$canModifyAttendance = new CanModifyAttendance($attendanceController, $eventMapper = new EventMapper(), $_SESSION['user'], new ErrorMapper());

/**
 * @param $eventId
 * @return bool
 * @throws Exception
 */
$method = $this->arg[0];

if ($method == 'list' && is_numeric($this->arg[1])) {
    $canModifyAttendance->isSatisfiedBy($this->arg[1]);
    $data = $attendanceController->executeAttendeeList($this->arg[1]);
    $attendanceController->renderAttendeeList($data);

} elseif ($method == 'processModifyAttendanceList' && !empty($_POST)) {
    $data = $_POST;
    $canModifyAttendance->isSatisfiedBy($data['event']['id']);
    $attendanceController->processModifyAttendanceList($data);

} elseif ($method == 'processRemoveAttendance' && is_numeric($this->arg[1]) && is_numeric($this->arg[2])) {
    $eventId = $this->arg[1];
    $userId = $this->arg[2];
    $canModifyAttendance->isSatisfiedBy($eventId);

    $eventMapper->removeAttendance($eventId, $userId);

    header('Location: ' . "/calendar/attendance/list/" . $eventId);
    exit;
} /* Show all upcoming events with the number of attendees
 */
else if ($method == 'upcomingEvents') {
    $data = $attendanceController->executeUpcomingEventAttendance();

    $attendanceController->renderUpcomingEventAttendance($data);

} /* A csv export of all people pending for a particular event
 */
elseif ($method == 'exportPending') {
    $canModifyAttendance->isSatisfiedBy($this->arg[1]);
    $data = $attendanceController->executePending($this->arg[1]);

    $attendanceController->renderExportCSV($data);

} /* A csv export of all people attending a particular event
 */
elseif ($method == 'exportAttending') {
    $canModifyAttendance->isSatisfiedBy($this->arg[1]);
    $data = $attendanceController->executeAttending($this->arg[1]);

    $attendanceController->renderExportCSV($data);

} /* A csv export of all people rejected from a particular event
 */
elseif ($method == 'exportRejected') {
    $canModifyAttendance->isSatisfiedBy($this->arg[1]);
    $data = $attendanceController->executeRejected($this->arg[1]);

    $attendanceController->renderExportCSV($data);

} else {
    $this->pageNotFound();
}
