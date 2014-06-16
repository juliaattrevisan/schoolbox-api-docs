<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$authenticator = new \EOTC\Authenticator();

if ($authenticator->isEOTCCreator($_SESSION['user'])) {
	$eventMapper = new \EOTC\EventMapper();

	try {
		$event = $eventMapper->findById($this->arg[0]);
	}
	catch (\Exception $e) {
		error(160);
	}

	$groupHandler = new Group();

	$this->setFile(TEMPLATE_content, 'modules/eotc/manage/resources/list.html');
	$this->setBlock(TEMPLATE_content, 'has_resources', 'has_resources_block');

	$this->setVar(array(
		'EVENT_ID' => $event->getId(),
		'EVENT_NAME' => $event->getNote(),
	));
//
//	$externalHandler = new \External_Synergetic_Class();
//	$allFiletypes = $externalHandler->findFiletypes();
//
//	if (is_array($allFiletypes) && count($allFiletypes)) {
//		foreach($allFiletypes as $currentFiletype) {
//			$this->setVar(array(
//				'FILETYPE_ID' => $currentFiletype['Code'],
//				'FILETYPE_NAME' => $currentFiletype['Description']
//			));
//
//			$this->parse('filetype_block', 'filetype', true);
//		}
//	}
//
//	if ($event->getMetadata()->getStudentAttendees()->count()) {
//		$roleMapper = new \Schoolbox\RoleMapper();
//
//		foreach ($event->getMetadata()->getStudentAttendees() as $currentStudent) {
//			$role = $roleMapper->findById($currentStudent->getRole()->getId());
//
//			$this->setVar(array(
//				'STUDENT_ATTENDEE_ID' => $currentStudent->getId(),
//				'STUDENT_ATTENDEE_FIRSTNAME' => $currentStudent->getFirstname(),
//				'STUDENT_ATTENDEE_LASTNAME' => $currentStudent->getLastname(),
//				'STUDENT_ATTENDEE_ROLE_NAME' => $role->getName(),
//			));
//
//			$this->parse('student_attendee_block', 'student_attendee', true);
//		}
//	}
//	else {
//		$this->setVar(array(
//			'student_attendee_block' => ''
//		));
//	}

	/* Cannot add resources until an event is created
 	 */
	$this->parse('has_resources_block', 'has_resources');
}
else {
	error(19);
}