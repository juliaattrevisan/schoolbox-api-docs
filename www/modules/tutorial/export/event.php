<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/event.php';

$page = new SitePage;
$groupHandler = new Group(TUTORIAL_SERVICE);

$line = "EventID,Date,Name,Start,Finish,Location,Code,Type,Tutor 1,Tutor 2,Cancelled\n";

// user
$user = false;
if ($groupHandler->getPermission(0) >= FULL_ACCESS && is_numeric($_GET['userID'])) {
	$userHandler = new User;
	$user = $userHandler->getByID($_GET['userID']);
}

// output events
$eventHandler = new Event;
$eventList = $eventHandler->getAll('all', 'date', 'asc', $user['uid']);

if (is_array($eventList)) {
	foreach ($eventList as $event) {
		
		$output = array($event['eventID'],
						outputDate('Y-m-d', $event['date']),
						scrubText($event['name']),
						$event['start'],
						$event['finish'],
						scrubText($event['location']),
						!empty($event['code']) ? scrubText($event['code']) : 'N/A',
						$event['typeName'],
						scrubText($event['tutorName1']),
						scrubText($event['tutorName2']),
						$event['cancelled'] == 'true' ? 'Cancelled' : '',
						);
		
		$line .= outputCSV($output)."\n";		
		
	}

}

header('Content-type: text/csv');
header('Content-Disposition: inline; filename="tutorial-list-'.date('dMY').'.csv"');
print $line;


?>