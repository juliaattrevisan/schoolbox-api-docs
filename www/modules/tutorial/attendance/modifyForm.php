<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/event.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/attendance.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/enrolment.php';

$page = new SitePage();
$eventHandler = new Event;
$attendanceHandler = new Attendance;
$enrolmentHandler = new Enrolment;
$groupHandler = new Group(TUTORIAL_SERVICE);

// check access
if (($permission = $groupHandler->getPermission(0)) < WRITE_ACCESS)
	error(19);

if (is_array($_POST['reason']) && is_numeric($_POST['eventID'])) {

	/* submit attendance */
	
	// check user is one of the tutors
	$event = $eventHandler->getByID($_POST['eventID']);
	if ($permission < FULL_ACCESS && !in_array($_SESSION['id'], array($event['tutor1ID'], $event['tutor2ID'])))
		error(19);

	// check event isn't too old
	if ($event['date'] < strtotime("-1 month"))
		error(19);
	
	
	$attendanceHandler->deleteByEvent($_POST['eventID']);
	
	foreach ($_POST['reason'] as $studentID => $reason) {
		$attendance = array('eventID'	=> $_POST['eventID'],
							'studentID'	=> $studentID,
							'present'	=> isset($_POST['present'][$studentID]) ? 'true' : 'false',
							'reason'	=> $reason,
							);
		
		$attendanceHandler->save($attendance);
	}
	
	localRedir('/modules/tutorial/attendance/list.php?week=' . $_POST['week']);
}

?>