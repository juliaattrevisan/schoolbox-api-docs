<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/event.php';

$page = new SitePage;
$groupHandler = new Group(TUTORIAL_SERVICE);

$line = "EventID,Date,Name,Start,Finish,Location,Code,Type,Tutor 1,Tutor 2,Cancelled\n";

// user
$user = false;
if ($groupHandler->getPermission(0) >= FULL_ACCESS && is_numeric($_GET['userID'])) {
	$userHandler = new DeprecatedUser;
	$user = $userHandler->getByID($_GET['userID']);
}

// output events
$eventHandler = new Event;
$eventList = $eventHandler->getAll('all', 'date', 'asc', $user['uid']);

if (is_array($eventList)) {
	foreach ($eventList as $event) {
		$date = new Alaress_Date_Format($event['date'], 'U');

		$output = array(
			$event['eventID'],
			$date->getLocal(),
			Scrub::text($event['name']),
			$event['start'],
			$event['finish'],
			Scrub::text($event['location']),
			!empty($event['code']) ? Scrub::text($event['code']) : 'N/A',
			$event['typeName'],
			Scrub::text($event['tutorName1']),
			Scrub::text($event['tutorName2']),
			$event['cancelled'] == 'true' ? 'Cancelled' : '',
		);

		$line .= TextFormatter::outputCSV($output)."\n";
	}
}

$date = new Alaress_Date_Format(time(), 'U');

header('Content-type: text/csv');
header('Content-Disposition: inline; filename="tutorial-list-'.$date->getLocalShort().'.csv"');
print $line;
