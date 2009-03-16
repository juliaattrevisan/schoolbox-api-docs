<?php
require_once '../../conf/conf.php';
require_once SITE_root . '/include/sitePage.php';
require_once SITE_root . '/www/modules/tutorial/library/event.php';
require_once SITE_root . '/library/group.php';
require_once SITE_root . '/library/user.php';

$page = new SitePage();
$page->setFile(TEMPLATE_content, 'tutorial/report-hours.html');
$page->setBlock(TEMPLATE_content, 'has_events', 'has_events_block');
$page->setBlock('has_events', 'tutor_list', 'tutor_list_block');
$page->setBlock('tutor_list', 'event_list', 'event_list_block');

$page->setTitle('Tutorial Management');

if (!isset($_GET['to']))
	$_GET['to'] = date("d/m/Y", strtotime("saturday"));

if (!isset($_GET['from']))
	$_GET['from'] = date("d/m/Y", strtotime("-2 week, sunday"));

// check access
$groupHandler = new Group(TUTORIAL_SERVICE);
if ($groupHandler->getPermission(0) < FULL_ACCESS)
	error(19);
	
	
$from = ausDateToUnix($_GET['from']);
$to = ausDateToUnix($_GET['to']);

$output = array('FROM'		=> outputDate("d/m/Y", $from),
				'TO'		=> outputDate("d/m/Y", $to),
);
$page->setVar($output);

$userHandler = new User;
$eventHandler = new Event;
$tutorList = $eventHandler->getTutorList();
if (is_array($tutorList)) {
	$i = 0;
	foreach ($tutorList as $tutor) {
	
		$user = $userHandler->getBySynergy($tutor['id']);
		if (isset($user['uid'])) {
			$eventList = $eventHandler->getByTutor($user['uid'], $from, $to);
			
			if (is_array($eventList)) {
				$i++;
				$totalHours = 0;
				foreach ($eventList as $event) {
				
					$output = array('DATE'		=> outputDate(DATE_short_no_time, $event['date']),
									'MARKED'	=> outputDate(DATE_short_no_time, $event['marked']),
									'START'		=> $event['start'],
									'FINISH'	=> $event['finish'],
									'TUTORIAL'	=> scrubText($event['name']),
									'CODE'		=> scrubText($event['code']),
									'STUDENTS'	=> $event['students'],
									'HOURS'		=> number_format($event['hours'], 2),
									);
					$page->setVar($output);
					$page->parse('event_list_block', 'event_list', true);
					
					$totalHours += $event['hours'];
					
				}
				$output = array('TUTOR_NAME'	=> scrubText($tutor['name']),
								'TOTAL'			=> number_format($totalHours, 2),
								);
				$page->setVar($output);
	
				$page->parse('tutor_list_block', 'tutor_list', true);
				$page->setVar('event_list_block', '');
			}
		}
	}
	
	if ($i == 0)
		$page->setVar('tutor_list_block', '<p>All attendance completed.</p>');
	
	$page->parse('has_events_block', 'has_events');
	
} else
	$page->setVar('has_events_block', 'No tutors to display.');	




$page->outputPage();
?>