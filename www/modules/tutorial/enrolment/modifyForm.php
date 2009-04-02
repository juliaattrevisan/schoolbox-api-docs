<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/event.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/enrolment.php';

$page = new SitePage;
$eventHandler = new Event;
$enrolmentHandler = new Enrolment;

// check access
$groupHandler = new Group(TUTORIAL_SERVICE);
if (($permission = $groupHandler->getPermission(0)) < WRITE_ACCESS)
	error(19);

if (is_numeric($_POST['userID']) && is_numeric($_POST['eventID'])) {

	/* add student */
	
	// check user is one of the tutors
	$event = $eventHandler->getByID($_POST['eventID']);
	if ($permission < FULL_ACCESS && !in_array($_SESSION['id'], array($event['tutor1ID'], $event['tutor2ID'])))
		error(19);
		
	// check event isn't too old
	if ($event['date'] < strtotime("-1 month"))
		error(19);


	require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';
	$userHandler = new User;
	$user = $userHandler->getByID($_POST['userID']);
	if (isset($user['uid'])) {
		$enrolment = array('code'		=> $event['code'],
						   'eventID'	=> $_POST['eventID'],
						   'studentID'	=> $user['uid'],
						   );
		$enrolmentHandler->create($enrolment);
	}
	
	$page->backToTheFuture(1);
}
elseif (is_numeric($_GET['delete']) && is_numeric($_GET['eventID'])) {

	/* delete student */
	
	// check user is one of the tutors
	$event = $eventHandler->getByID($_GET['eventID']);
	if ($permission < FULL_ACCESS && !in_array($_SESSION['id'], array($event['tutor1ID'], $event['tutor2ID'])))
		error(19);


	$enrolmentHandler->deleteByStudent($_GET['delete'], $event['code'], $_GET['eventID']);

	$page->backToTheFuture(1);

} 

?>