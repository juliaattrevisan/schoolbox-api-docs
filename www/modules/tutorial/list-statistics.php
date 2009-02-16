<?php

require_once '../../conf/conf.php';
require_once SITE_root . '/include/sitePage.php';
require_once SITE_root.'/www/modules/tutorial/library/event.php';
require_once SITE_root.'/library/group.php';

$page = new SitePage();
$page->setFile(TEMPLATE_content, 'tutorial/list-statistics.html');
$page->setBlock(TEMPLATE_content, 'has_events', 'has_events_block');
$page->setBlock('has_events', 'event_list', 'event_list_block');
$page->setBlock('event_list', 'attendance', 'attendance_block');

$page->setTitle('Tutorials');

$groupHandler = new Group(TUTORIAL_SERVICE);

// check access
if ($groupHandler->getPermission(0) < WRITE_ACCESS)
	error(19);

if (!isset($_GET['show']))
	$_GET['show'] = 'all';

$eventHandler = new Event;
$eventList = $eventHandler->getStatistics($_GET['show']);
$page->setVar('SHOW', ucwords(scrubText($_GET['show'])));

if (is_array($eventList)) {
	$i = 0;
	foreach ($eventList as $event) {

		$i++;
		if (($event['code'] != $last['code'] || empty($last['code'])) && $i > 1) {
			
			$output = array('EVENT_ID'	=> $last['eventID'],
							'DATE'		=> outputDate('d/m/Y', $last['date']),
							'START'		=> $last['start'],
							'FINISH'	=> $last['finish'],
							'NAME'		=> scrubText($last['name']),
							'CODE'		=> !empty($last['code']) ? scrubText($last['code']) : 'N/A',
							'LOCATION'	=> scrubText($last['location']),
							'TUTORS'	=> scrubText(implode(", ", $eventHandler->getTutorsByEvent($last['code']))),
							'COUNT'		=> $eventTotal,
							'STUDENT_COUNT'	=> $eventHandler->getStudentCount($last['code'], $last['eventID']),
							'ATTENDANCE' 	=> $last['expected'] > 0 ? number_format(($last['present'] / $last['expected'])*100, 2) : 'N/A',
							
							);
			$page->setVar($output);
			$page->parse('event_list_block', 'event_list', true);
			
			$eventTotal = 0;
			
		}
		
		$last = $event;
		$eventTotal++;
		
	}

	$output = array('EVENT_ID'	=> $event['eventID'],
					'DATE'		=> outputDate('d/m/Y', $event['date']),
					'START'		=> $event['start'],
					'FINISH'	=> $event['finish'],
					'NAME'		=> scrubText($event['name']),
					'CODE'		=> scrubText($event['code']),
					'LOCATION'	=> scrubText($event['location']),
					'TUTORS'	=> scrubText(implode(", ", $eventHandler->getTutorsByEvent($event['code']))),
					'COUNT'		=> $eventTotal,
					'STUDENT_COUNT'	=> $eventHandler->getStudentCount($event['code'], $event['eventID']),
					'ATTENDANCE' 	=> $event['expected'] > 0 ? number_format(($event['present'] / $event['expected'])*100, 2) : 'N/A',
					
					);
	$page->setVar($output);
	$page->parse('event_list_block', 'event_list', true);

	$page->parse('has_events_block', 'has_events');
} else
	$page->setVar('has_events_block', 'No events to display.');
	
$page->outputPage();
?>