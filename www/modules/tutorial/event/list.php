<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/event.php';

$page = new SitePage;
$eventHandler = new Event;

$page->setFile(TEMPLATE_content, 'tutorial/event/list.html');
$page->setBlock(TEMPLATE_content, 'add_link', 'add_link_block');

$page->setBlock(TEMPLATE_content, 'has_events', 'has_events_block');
$page->setBlock('has_events', 'event_list', 'event_list_block');
$page->setBlock('has_events', 'modify_title', 'modify_title_block');
$page->setBlock('event_list', 'modify_link', 'modify_link_block');
$page->setTitle('Tutorials');

$groupHandler = new Group(TUTORIAL_SERVICE);

$page->setVar('add_link_block', '');
if ($groupHandler->getPermission(0) >= WRITE_ACCESS)
	$page->parse('add_link_block', 'add_link');

// view
if (!isset($_GET['show']))
	$_GET['show'] = 'week';

// sorting
$sortOptions = array('date', 'name', 'start', 'finish', 'location', 'code', 'tutorName1', 'typeName');
if (!isset($_GET['order']) || !in_array($_GET['order'], $sortOptions))
	$_GET['order'] =  $sortOptions[0];

foreach ($sortOptions as $option) {
	$output = array('CLASS_'.strtoupper($option) => '',
					'DIR_'.strtoupper($option) => 'asc',
					);
	$page->setVar($output);
}

$output = array('CLASS_'.strtoupper($_GET['order']) => $_GET['dir'] == 'desc' ? 'desc' : 'asc',
				'DIR_'.strtoupper($_GET['order']) => $_GET['dir'] == 'asc' ? 'desc' : 'asc',
				);
$page->setVar($output);

// user
$user = false;
if ($groupHandler->getPermission(0) >= FULL_ACCESS && is_numeric($_GET['userID'])) {
	$userHandler = new User;
	$user = $userHandler->getByID($_GET['userID']);
}


// output events
$eventList = $eventHandler->getAll($_GET['show'], $_GET['order'], $_GET['dir'], $user['uid']);
$output = array('SHOW'	=> ucwords(Scrub::text($_GET['show'])),
				'USER_URL'  => isset($user['uid']) ? 'userID=' . $user['uid'] : '',
				'USER_NAME'	=> isset($user['uid']) ? ' - ' . $userHandler->formatName($user) : '',
				);
$page->setVar($output);

if (is_array($eventList)) {
	foreach ($eventList as $event) {
		$date = new Alaress_Date_Format($event['date'], 'U');

		$output = array('EVENT_ID'	=> $event['eventID'],
						'DATE'		=> $date->getDateOnly(),
						'START'		=> $event['startTime'],
						'FINISH'	=> $event['finishTime'],
						'NAME'		=> Scrub::text($event['name']),
						'CODE'		=> !empty($event['code']) ? Scrub::text($event['code']) : 'N/A',
						'TYPE'		=> $event['typeName'],
						'LOCATION'	=> Scrub::text($event['location']),
						'TUTORS'	=> Scrub::text($event['tutorName1'] . (!empty($event['tutorName2'])?", ".$event['tutorName2']:"")),
						'CANCELLED'	=> $event['cancelled'] == 'true' ? 'cancelled' : '',
						'PASSED'	=> $event['date'] < strtotime("12am") ? 'passed' : '',
						'ON_OFF' =>  ++$j%2? 'dark' : '',

						);
		$page->setVar($output);
		$page->setVar('modify_link_block', '');
		if ($groupHandler->getPermission(0) >= WRITE_ACCESS)
			$page->parse('modify_link_block', 'modify_link');

		$page->parse('event_list_block', 'event_list', true);
	}
	$page->setVar('modify_title_block', '');
	if ($groupHandler->getPermission(0) >= WRITE_ACCESS)
		$page->parse('modify_title_block', 'modify_title');

	$page->parse('has_events_block', 'has_events');
} else
	$page->setVar('has_events_block', 'There are no upcoming events assigned to you.');

$page->outputPage();
?>
