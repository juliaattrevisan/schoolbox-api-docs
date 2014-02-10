<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$eventMapper = new \EOTC\EventMapper();

try {
	$event = $eventMapper->findById($this->arg[0]);
}
catch (\Exception $e) {
	error(160);
}

$this->setFile(TEMPLATE_content, 'modules/eotc/manage/attendance/list.html');
$this->setBlock(TEMPLATE_content, 'student_attendee', 'student_attendee_block');
$this->setBlock(TEMPLATE_content, 'filetype', 'filetype_block');

$this->setVar(array(
	'EVENT_ID' => $event->getId(),
	'EVENT_NAME' => $event->getNote(),
));

$externalHandler = new \External_Synergetic_Class();
$allFiletypes = $externalHandler->findFiletypes();

if (is_array($allFiletypes) && count($allFiletypes)) {
	foreach($allFiletypes as $currentFiletype) {
		$this->setVar(array(
			'FILETYPE_ID' => $currentFiletype['Code'],
			'FILETYPE_NAME' => $currentFiletype['Description']
		));

		$this->parse('filetype_block', 'filetype', true);
	}
}

if ($event->getStudentAttendees()->count()) {
	$roleMapper = new \Schoolbox\RoleMapper();

	foreach ($event->getStudentAttendees() as $currentStudent) {
		$role = $roleMapper->findById($currentStudent->getRole()->getId());

		$this->setVar(array(
			'STUDENT_ATTENDEE_ID' => $currentStudent->getId(),
			'STUDENT_ATTENDEE_FIRSTNAME' => $currentStudent->getFirstname(),
			'STUDENT_ATTENDEE_LASTNAME' => $currentStudent->getLastname(),
			'STUDENT_ATTENDEE_ROLE_NAME' => $role->getName(),
		));

		$this->parse('student_attendee_block', 'student_attendee', true);
	}
}
else {
	$this->setVar(array(
		'student_attendee_block' => ''
	));
}
