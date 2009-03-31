<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
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

$from = ausDateToUnix($_GET['from']);
$to = ausDateToUnix($_GET['to']);

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
					
					$output = array($user['uid'],
								  	$user['synergy_id'],
									$user['title'],
									$user['firstname'],
									$user['lastname'],
									formatEmail($user),
									outputDate('Y-m-d', $event['marked']),
									scrubText($event['name']),
									scrubText($event['code']),
									$event['typeName'],
									outputDate('Y-m-d', $event['date']),
									$event['start'],
									$event['finish'],
									$event['enrolled'],
									$attendaceHandler->getCountByEvent($event['eventID']),
									number_format($event['hours'], 2),
									'PROCESSED_SELECTED' => $processed == 'true' ? 'Processed' : '',
									);
					
					$line .= outputCSV($output)."\n";
				}
				
				
			}
		}
	}
}


header('Content-type: text/csv');
header('Content-Disposition: inline; filename="hours-performed-'.outputDate('dMY', $from).'-'.outputDate('dMY', $to).'.csv"');
print $line;

?>