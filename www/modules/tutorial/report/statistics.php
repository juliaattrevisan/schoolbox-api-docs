<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/event.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$page = new SitePage();
$page->setFile(TEMPLATE_content, 'tutorial/report/statistics.html');
$page->setBlock(TEMPLATE_content, 'has_events', 'has_events_block');
$page->setBlock('has_events', 'event_list', 'event_list_block');
$page->setBlock('event_list', 'tutor', 'tutor_block');

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

$pageOutput = false;

if (is_array($eventList)) {
	$i = 0;
	
	foreach ($eventList as $event) {
		
		$output = array('EVENT_ID'		=> $event['eventID'],
						'NAME'			=> scrubText($event['name']),
						'CODE'			=> !empty($event['code']) ? scrubText($event['code']) : 'N/A',
						'TYPE'			=> 'Tutorials',
						'LOCATION'		=> scrubText($event['location']),
						'COUNT'			=> $eventHandler->getEventCount($event['code'], $event['eventID']),
						'STUDENT_COUNT'	=> $eventHandler->getStudentCount($event['code'], $event['eventID']),
						'ATTENDANCE' 	=> $event['expected'] > 0 ? number_format(($event['attended'] / $event['expected'])*100, 2) : 'N/A',
						'ON_OFF' 		=>  ++$i%2? 'dark' : '',
						);
		$page->setVar($output);
		
		// output tutors
		$page->setVar('tutor_block', '');			
		$tutorList = $eventHandler->getTutorsByEvent($event['code'], $event['eventID']);
		if (is_array($tutorList)) {
			foreach ($tutorList as $tutor) {
				$output = array('TUTOR_NAME'	=> formatName($tutor),
								'TUTOR_ID'		=> $tutor['id'],
								);
				$page->setVar($output);
				$page->parse('tutor_block', 'tutor', true);
			}
		}
		
		$page->parse('event_list_block', 'event_list', true);
		
	}
	$pageOutput = true;
	$page->parse('has_events_block', 'has_events', true);
	$page->setVar('event_list_block', '');
} 
	


$eventList = $eventHandler->getConsultationStatistics($_GET['show']);

if (is_array($eventList)) {
	$i = 0;
	foreach ($eventList as $event) {
	
		$output = array('EVENT_ID'		=> $event['eventID'],
						'NAME'			=> scrubText($event['name']),
						'CODE'			=> $event['code'],
						'TYPE'			=> 'Consultation',
						'COUNT'			=> $event['classes'],
						'LOCATION'		=> scrubText($event['location']),
						'STUDENT_COUNT'	=> $event['students'],
						'ATTENDANCE' 	=> $event['expected'] > 0 ? number_format(($event['attended'] / $event['expected'])*100, 2) : 'N/A',
						'ON_OFF' 		=>  ++$i%2? 'dark' : '',
						);
		$page->setVar($output);
		
		// output tutors
		$page->setVar('tutor_block', '');			
		$tutorList = $eventHandler->getTutorsByEvent($event['code'], $event['eventID']);
		if (is_array($tutorList)) {
			foreach ($tutorList as $tutor) {
				$output = array('TUTOR_NAME'	=> formatName($tutor),
								'TUTOR_ID'		=> $tutor['id'],
								);
				$page->setVar($output);
				$page->parse('tutor_block', 'tutor', true);
			}
		}
		
		$page->parse('event_list_block', 'event_list', true);
		
	}
	
	$pageOutput = true;
	$page->parse('has_events_block', 'has_events', true);

}
	
	
if (!$pageOutput)
	$page->setVar('has_events_block', 'No events to display.');
	
$page->outputPage();
?>