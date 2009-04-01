<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
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
							'TYPE'		=> $last['typeName'],
							'LOCATION'	=> scrubText($last['location']),
							'COUNT'		=> $eventTotal,
							'STUDENT_COUNT'	=> $eventHandler->getStudentCount($last['code'], $last['eventID']),
							'ATTENDANCE' 	=> $last['expected'] > 0 ? number_format(($last['present'] / $last['expected'])*100, 2) : 'N/A',
							'ON_OFF' 	=>  ++$j%2? 'dark' : '',
							);
			$page->setVar($output);
			
			// output tutors
			$page->setVar('tutor_block', '');			
			$tutorList = $eventHandler->getTutorsByEvent($last['code'], $last['eventID']);
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
					'COUNT'		=> $eventTotal,
					'STUDENT_COUNT'	=> $eventHandler->getStudentCount($event['code'], $event['eventID']),
					'ATTENDANCE' 	=> $event['expected'] > 0 ? number_format(($event['present'] / $event['expected'])*100, 2) : 'N/A',
					'ON_OFF' 	=>  ++$j%2? 'dark' : '',
					
					);
	$page->setVar($output);

	// output tutors
	$page->setVar('tutor_block', '');			
	$tutorList = $eventHandler->getTutorsByEvent($last['code'], $event['eventID']);
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

	$page->parse('has_events_block', 'has_events');
} else
	$page->setVar('has_events_block', 'No events to display.');
	
$page->outputPage();
?>