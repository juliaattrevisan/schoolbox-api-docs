<?php

require_once '../../conf/conf.php';
require_once SITE_root . '/include/sitePage.php';
require_once SITE_root.'/www/modules/tutorial/library/event.php';
require_once SITE_root.'/library/group.php';

$page = new SitePage();
$page->setFile(TEMPLATE_content, 'tutorial/list-event.html');
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

// default
if (!isset($_GET['show']))
	$_GET['show'] = 'week';


// output events
$eventHandler = new Event;
$eventList = $eventHandler->getAll($_GET['show']);
$page->setVar('SHOW', ucwords(scrubText($_GET['show'])));

if (is_array($eventList)) {
	foreach ($eventList as $event) {
		$output = array('EVENT_ID'	=> $event['eventID'],
						'DATE'		=> outputDate('l, jS M Y', $event['date']),
						'START'		=> $event['start'],
						'FINISH'	=> $event['finish'],
						'NAME'		=> scrubText($event['name']),
						'CODE'		=> !empty($event['code']) ? scrubText($event['code']) : 'N/A',
						'LOCATION'	=> scrubText($event['location']),
						'TUTORS'	=> scrubText($event['tutorName1'] . (!empty($event['tutorName2'])?", ".$event['tutorName2']:"")),
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