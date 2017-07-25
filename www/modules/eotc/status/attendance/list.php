<?php
/**
 * Attendance List
 *
 * A read-only version of list.php which shows you who is attending
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use EOTC\Authenticator;
use EOTC\EventMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\RoleMapper;

$event = false;

try {
	$eventMapper = EventMapper::getInstance();
	$event = $eventMapper->findById($this->arg[0]);
}
catch (MapperException $e) {
	error(160);
}

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

/* Only EOTC Administrators and Subscribed Users can view this page
 */
$authenticator = new Authenticator($this);

$isEOTCAdministrator = $authenticator->isEOTCAdministrator($sessionUser, $event);
$isSubscribedUser = $authenticator->isSubscribedUser($sessionUser, $event);

if ($isEOTCAdministrator || $isSubscribedUser) {
	$this->setFile(TEMPLATE_content, 'modules/eotc/status/attendance.html');
	$this->setBlock(TEMPLATE_content, 'student_attendee', 'student_attendee_block');

	$this->setVar(array(
		'EVENT_ID' => $event->getId(),
		'EVENT_NAME' => $event->getTitle(),
	));

    $roleMapper = RoleMapper::getInstance();

    if ($event->getMetadata()->getStudentAttendees()->count()) {
		foreach ($event->getMetadata()->getStudentAttendees() as $currentStudent) {
			$role = $roleMapper->findById($currentStudent->getRole()->getId());

			$this->setVar([
				'STUDENT_ATTENDEE_EXTERNAL_ID' => $currentStudent->getExternalId(),
				'STUDENT_ATTENDEE_ID' => $currentStudent->getId(),
				'STUDENT_ATTENDEE_FIRSTNAME' => $currentStudent->getFirstname(),
				'STUDENT_ATTENDEE_LASTNAME' => $currentStudent->getLastname(),
				'STUDENT_ATTENDEE_ROLE_NAME' => $role->getName(),
			]);

			$this->parse('student_attendee_block', 'student_attendee', true);
		}
	}
	else {
		$this->setVar([
			'student_attendee_block' => ''
		]);
	}
}
else {
	error(19);
}
