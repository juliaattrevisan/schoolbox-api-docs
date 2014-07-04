<?php
/**
 * ${NAME}
 *
 * A read-only version of list.php
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/* Only EOTC Administrators can view this page
 */
$authenticator = new \EOTC\Authenticator();

if (!$authenticator->isEOTCAdministrator($_SESSION['user'])) {
	error(19);
}

$eventMapper = new \EOTC\EventMapper();
$csv = '';

try {
	$event = $eventMapper->findById($this->arg[0]);
}
catch (\Exception $e) {
	exit();
}

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