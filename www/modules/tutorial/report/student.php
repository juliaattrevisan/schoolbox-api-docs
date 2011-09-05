<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/event.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$page = new SitePage();
$page->setFile(TEMPLATE_content, 'tutorial/report/student.html');
$page->setBlock(TEMPLATE_content, 'has_events', 'has_events_block');
$page->setBlock('has_events', 'class_list', 'class_list_block');
$page->setBlock('class_list', 'event_list', 'event_list_block');

$page->setTitle('Tutorial Management');

// check access
$groupHandler = new Group(TUTORIAL_SERVICE);
if ($groupHandler->getPermission(0) < FULL_ACCESS)
	error(19);

if (!isset($_GET['to']))
	$_GET['to'] = date("d/m/Y");

$fromObj = new Alaress_Date_Format($_GET['from']);
$from = $fromObj->getUnixTimestamp();

$toObj = new Alaress_Date_Format($_GET['to']);
$to = $toObj->getUnixTimestamp();

$eventHandler = new Event;
$userHandler = new User;

if (is_numeric($_GET['userID'])) {
	$user = $userHandler->getByID($_GET['userID'], 3);
}

$fromDate = new Alaress_Date_Format($from, 'U');
$toDate = new Alaress_Date_Format($from, 'U');

$output = array('FROM'			=> $fromDate->getLocalShort(),
				'TO'			=> $toDate->getLocalShort(),
				'STUDENT'		=> isset($user) ? $userHandler->formatName($user) : '',
				'STUDENT_ID'	=> $user['uid'],
				);
$page->setVar($output);

if (isset($user['uid'])) {

		$eventList = $eventHandler->getByStudent($user['uid'], $from, $to);
		if (is_array($eventList)) {

			$i = 0;
			$total = count($eventList);
			foreach ($eventList as $event) {

				$i++;
				if ($event['code'] != $last['code'] && $i > 1) {

					$output = array('TUTORIAL'		=> Scrub::text($last['name']),
									'CODE'			=> Scrub::text($last['code']),
									'TYPE'			=> $last['typeName'],
									'ATTENDANCE'	=> number_format(($totalAttended/($totalAttended+$totalMissed))*100, 2),
									'TOTAL_ATTENDED'	=> number_format($totalAttended, 2),
									'TOTAL_MISSED'		=> number_format($totalMissed, 2),
									);
					$page->setVar($output);

					$page->parse('class_list_block', 'class_list', true);
					$page->setVar('event_list_block', '');

					$totalAttended = 0;
					$totalMissed = 0;

				}

				$hoursAttended = $event['hours'];
				$hoursMissed = 0;
				if ($event['present']!='true') {
					$hoursAttended = 0;
					$hoursMissed = $event['hours'];

				}

				$date = new Alaress_Date_Format($event['date'], 'U');

				$output = array('EVENT_ID'	=> $event['eventID'],
								'DATE'		=> $date->getDateOnly(),
								'START'		=> $event['start'],
								'FINISH'	=> $event['finish'],
								'PRESENT'	=> $event['present']=='true'?'Present':'<em>Absent</em>',
								'HOURS_ATTENDED'	=> number_format($hoursAttended, 2),
								'HOURS_MISSED'		=> number_format($hoursMissed, 2),
								'ON_OFF'		=> ++$j%2? 'dark' : '',
								);
				$page->setVar($output);
				$page->parse('event_list_block', 'event_list', true);

				$last = $event;
				$totalAttended += $hoursAttended;
				$totalMissed += $hoursMissed;

			}

			// last class
			$output = array('TUTORIAL'		=> Scrub::text($last['name']),
							'CODE'			=> Scrub::text($last['code']),
							'TYPE'			=> $last['typeName'],
							'ATTENDANCE'	=> number_format(($totalAttended/($totalAttended+$totalMissed))*100, 2),
							'TOTAL_ATTENDED'	=> number_format($totalAttended, 2),
							'TOTAL_MISSED'		=> number_format($totalMissed, 2),
							);
			$page->setVar($output);
			$page->parse('class_list_block', 'class_list', true);

		} else
			$page->setVar('class_list_block', '<p>No attendance to display.</p>');

		$page->parse('has_events_block', 'has_events');
}
else
	$page->setVar('has_events_block', '');




$page->outputPage();
?>