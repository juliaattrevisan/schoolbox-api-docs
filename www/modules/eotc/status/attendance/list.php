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
    $this->setBlock(TEMPLATE_content, 'has_student_attendees', 'has_student_attendees_block');
	$this->setBlock('has_student_attendees', 'student_attendee', 'student_attendee_block');
    $this->setBlock(TEMPLATE_content, 'no_student_attendees', 'no_student_attendees_block');

	$this->setVar(array(
		'EVENT_ID' => $event->getId(),
		'EVENT_NAME' => $event->getTitle(),
	));

    $roleMapper = RoleMapper::getInstance();

    /* If there are students, list them, otherwise show empty state
     */
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

		$this->setVar([
		    'no_student_attendees_block' => ''
        ]);

		$this->parse('has_student_attendees_block', 'has_student_attendees');
	}
	else {
		$this->setVar([
            'has_student_attendees_block' => '',
			'student_attendee_block' => ''
		]);

		$this->parse('no_student_attendees_block', 'no_student_attendees');
	}
}
else {
	error(19);
}
