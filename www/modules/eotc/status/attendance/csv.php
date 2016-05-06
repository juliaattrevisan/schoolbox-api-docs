<?php
/**
 * Attendance List CSV
 *
 * A read-only version of list.php in csv format
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$event = false;

try {
	$mapper = new \EOTC\EventMapper();
	$event = $mapper->findById($this->arg[0]);
}
catch (\Schoolbox\Mapper\Exception $e) {
	error(160);
}

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

/* Only EOTC Administrators and Subscribed Users can view this page
 */
$authenticator = new \EOTC\Authenticator();

$isEOTCAdministrator = $authenticator->isEOTCAdministrator($sessionUser, $event);
$isSubscribedUser = $authenticator->isSubscribedUser($sessionUser, $event);

if ($isEOTCAdministrator || $isSubscribedUser) {
	$csv = '';

	$this->setVar(array(
		'EVENT_ID' => $event->getId(),
		'EVENT_NAME' => $event->getTitle(),
	));

	/* If there are attendees, pop it out
     */
	if ($event->getMetadata()->getStudentAttendees()->count()) {
		$roleMapper = new \Schoolbox\RoleMapper();

		$row = array(
			'Synergy ID',
			'Firstname',
			'Surname',
			'Group'
		);

		$csv .= TextFormatter::outputCSV($row) . "\n";

		foreach ($event->getMetadata()->getStudentAttendees() as $currentStudent) {
			$role = $roleMapper->findById($currentStudent->getRole()->getId());

			$row = array(
				$currentStudent->getExternalId(),
				$currentStudent->getFirstname(),
				$currentStudent->getLastname(),
				$role->getName(),
			);

			$csv .= TextFormatter::outputCSV($row) . "\n";
		}
	}

	/* Thump out a csv
     */
	$slug = new \Url("{$event->getId()}-{$event->getTitle()}-attendees");
	$filename = $slug->getSlug();

	header('Content-type: text/ascii');
	header("Content-disposition: attachment; filename=\"{$filename}.csv\"");
	echo $csv;

	exit();
}
else {
	error(19);
}

