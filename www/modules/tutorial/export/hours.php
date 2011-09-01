<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/event.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/attendance.php';

$page = new SitePage;
$userHandler = new User;
$eventHandler = new Event;
$attendaceHandler = new Attendance;

// check access
$groupHandler = new Group(TUTORIAL_SERVICE);
if ($groupHandler->getPermission(0) < FULL_ACCESS)
	error(19);

$dateObj = new Alaress_Date_Format($_GET['from']);
$from = $dateObj->getUnixTimestamp();

$toObj = new Alaress_Date_Format($_GET['to']);
$to = $toObj->getUnixTimestamp();

$line = "PortalID,SynergyID,Title,Firstname,Surname,Email,Marked,Tutorial,Code,Type,Tutorial Date,Start Time,Finish Time,Students Enrolled,Student Attended,Hours,Processed\n";


$tutorList = $eventHandler->getTutorList();
if (is_array($tutorList)) {
	foreach ($tutorList as $tutor) {

		$user = $userHandler->getBySynergy($tutor['id']);
		if (isset($user['uid'])) {
			$eventList = $eventHandler->getByTutor($user['uid'], $from, $to);

			if (is_array($eventList)) {
				foreach ($eventList as $event) {

					if ($event['tutor1ID'] == $user['uid'])
						$processed = $event['tutor1Processed'];
					else
						$processed = $event['tutor2Processed'];

					$emailObj = new Email();
					$email = $emailObj->formatEmailFromArray($user);

					$markedDate = new Alaress_Date_Format($event['marked'], 'U');
					$eventDate = new Alaress_Date_Format($event['date'], 'U');

					$output = array($user['uid'],
								  	$user['synergy_id'],
									$user['title'],
									$user['firstname'],
									$user['lastname'],
									$email,
									$markedDate->getLocalShort(),
									scrubText($event['name']),
									scrubText($event['code']),
									$event['typeName'],
									$eventDate->getLocalShort(),
									$event['start'],
									$event['finish'],
									$event['enrolled'],
									$attendaceHandler->getCountByEvent($event['eventID']),
									number_format($event['hours'], 2),
									'PROCESSED_SELECTED' => $processed == 'true' ? 'Processed' : '',
									);

					$line .= TextFormatter::outputCSV($output)."\n";
				}
			}
		}
	}
}

$fromDate = new Alaress_Date_Format($from);
$toDate = new Alaress_Date_Format($to);

header('Content-type: text/csv');
header('Content-Disposition: inline; filename="hours-performed-'. $fromDate->getLocalShort() .'-'. $toDate->getLocalShort() .'.csv"');
print $line;

