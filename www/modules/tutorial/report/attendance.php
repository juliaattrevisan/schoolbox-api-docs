<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/event.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$page = new SitePage();
$page->setFile(TEMPLATE_content, 'tutorial/report/attendance.html');
$page->setBlock(TEMPLATE_content, 'has_events', 'has_events_block');
$page->setBlock('has_events', 'event_list', 'event_list_block');

$page->setTitle('Tutorial Management');


// check access
$groupHandler = new Group(TUTORIAL_SERVICE);

if ($groupHandler->getPermission(0) < FULL_ACCESS) {
	error(19);
}

if (!isset($_GET['to'])) {
	$_GET['to'] = date("d/m/Y");
}

$fromObj = new Alaress_Date_Format($_GET['from'], 'd/m/y');
$from = $fromObj->getUnixTimestamp();

$toObj = new Alaress_Date_Format($_GET['to'], 'd/m/y');
$to = $toObj->getUnixTimestamp();

$output = array(
	'FROM' => $fromObj->getLocalShort(),
	'TO' => $toObj->getLocalShort(),
);

$page->setVar($output);

$eventHandler = new Event;
$eventList = $eventHandler->getUncompleted($from, $to);

if (is_array($eventList)) {
	foreach ($eventList as $event) {
		$date = new Alaress_Date_Format($event['date'], 'U');

		$output = array(
			'EVENT_ID'	=> $event['eventID'],
			'NAME'		=> Scrub::text($event['name']),
			'CODE'		=> !empty($event['code']) ? Scrub::text($event['code']) : "N/A",
			'DATE'		=> $date->getDateOnly(),
			'TYPE'		=> $event['typeName'],
			'TUTOR1_ID'	=> $event['tutor1ID'],
			'TUTOR2_ID'	=> $event['tutor2ID'],
			'TUTOR1'	=> Scrub::text($event['tutorName1']),
			'TUTOR2'	=> Scrub::text($event['tutorName2']),
			'ON_OFF'	=> ++$j%2 ? 'dark' : '',
		);

		$page->setVar($output);
		$page->parse('event_list_block', 'event_list', true);
	}

	$page->parse('has_events_block', 'has_events');
}
else {
	$page->setVar('has_events_block', '<p>All attendance completed.</p>');
}

$page->outputPage();
?>