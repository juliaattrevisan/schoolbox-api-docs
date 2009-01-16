<?php

require_once '../../conf/conf.php';
require_once SITE_root . '/include/sitePage.php';
require_once SITE_root.'/www/modules/tutorial/library/event.php';

$page = new SitePage();
$page->setFile(TEMPLATE_content, 'tutorial/list-statistics.html');
$page->setBlock(TEMPLATE_content, 'has_events', 'has_events_block');
$page->setBlock('has_events', 'event_list', 'event_list_block');
$page->setBlock('event_list', 'attendance', 'attendance_block');

$page->setTitle('Tutorials');

if (!isset($_GET['show']))
	$_GET['show'] = 'all';

$eventHandler = new Event;
$eventList = $eventHandler->getStatistics($_GET['show']);
$page->setVar('SHOW', ucwords(scrubText($_GET['show'])));

if (is_array($eventList)) {
	foreach ($eventList as $event) {
		$output = array('EVENT_ID'	=> $event['eventID'],
						'DATE'		=> outputDate('d/m/Y', $event['date']),
						'START'		=> $event['start'],
						'FINISH'	=> $event['finish'],
						'NAME'		=> scrubText($event['name']),
						'CODE'		=> scrubText($event['code']),
						'LOCATION'	=> scrubText($event['location']),
						'TUTORS'	=> scrubText(implode(", ", $eventHandler->getTutorsByEvent($event['code']))),
						'COUNT'		=> $event['count'],
						'STUDENT_COUNT'	=> $eventHandler->getStudentCount($event['code']),
						'ATTENDANCE' 	=> $event['expected'] > 0 ? number_format(($event['present'] / $event['expected'])*100, 2) : 'N/A',
						
						);
		$page->setVar($output);
		$page->parse('event_list_block', 'event_list', true);
	}
	$page->parse('has_events_block', 'has_events');
} else
	$page->setVar('has_events_block', 'There are no upcoming events assigned to you.');
	
$page->outputPage();
?>