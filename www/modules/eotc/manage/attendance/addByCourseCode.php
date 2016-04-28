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
		$event = $eventMapper->findById($_POST['eventId']);
	}
	catch (\Exception $e) {
		error(160);
	}

	/* If a course code was provided, find all the students in it
	 */
	if (strlen($_POST['courseCode'])) {
		$externalHandler = new \External_Synergetic_Class();
		$allStudents = $externalHandler->getStudents($_POST['courseCode']);

		if (is_array($allStudents) && count($allStudents)) {
			$userMapper = new \Schoolbox\UserMapper();
			$ids = array();

			foreach($allStudents as $currentStudent) {
				try {
					$user = $userMapper->findByExternalId($currentStudent['synergy_id']);
					$ids[] = $user->getId();
				}
				catch (\Exception $e) {
				}
			}

			$metadataMapper = new \EOTC\EventMetadataMapper();
			$metadataMapper->addStudentAttendeesRelationsSpecific($event->getMetadata(), $ids);
		}
	}

	$this->localRedir('/modules/eotc/manage/attendance/list/' . $event->getId());
}
else {
	error(19);
}