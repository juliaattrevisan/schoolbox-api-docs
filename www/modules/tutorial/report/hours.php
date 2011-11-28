<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/event.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/attendance.php';

$page = new SitePage;
$eventHandler = new Event;
$attendanceHandler = new Attendance;

// check access
$groupHandler = new Group(TUTORIAL_SERVICE);
if ($groupHandler->getPermission(0) < FULL_ACCESS)
	error(19);

if (is_array($_POST['processed'])) {
	while (list($i, list($eventID, $tutorID)) = each($_SESSION['hoursReport'])) {
		if (isset($_POST['processed'][$eventID][$tutorID]))
			$eventHandler->process($eventID, $tutorID);
		else
			$eventHandler->process($eventID, $tutorID, false);
	}


	$page->backToTheFuture(1);

} else {

	$page->setFile(TEMPLATE_content, 'tutorial/report/hours.html');
	$page->setBlock(TEMPLATE_content, 'has_events', 'has_events_block');
	$page->setBlock('has_events', 'tutor_list', 'tutor_list_block');
	$page->setBlock('tutor_list', 'event_list', 'event_list_block');

	$page->setTitle('Tutorial Management');

	if (!isset($_GET['to']))
		$_GET['to'] = date("d/m/Y", strtotime("saturday"));

	if (!isset($_GET['from']))
		$_GET['from'] = date("d/m/Y", strtotime("-2 week, sunday"));

	if (!isset($_GET['filter']))
		$_GET['filter'] = 'unprocessed';


	$fromObj = new Alaress_Date_Format($_GET['from'], 'd/m/y');
	$from = $fromObj->getUnixTimestamp();

	$toObj = new Alaress_Date_Format($_GET['to'], 'd/m/y');
	$to = $toObj->getUnixTimestamp();

	$output = array('FROM'		=> $fromObj->getLocalShort(),
					'TO'		=> $toObj->getLocalShort(),
					'FILTER_PROCESSED_SELECTED' => '',
					'FILTER_UNPROCESSED_SELECTED' => '',
					'FILTER_BOTH_SELECTED' => '',
					'FILTER_'.strtoupper($_GET['filter']).'_SELECTED'	=> 'checked="checked"',
	);
	$page->setVar($output);

	$_SESSION['hoursReport'] = array();

	$userHandler = new User;
	$tutorList = $eventHandler->getTutorList();
	if (is_array($tutorList)) {
		$i = 0;
		foreach ($tutorList as $tutor) {

			$user = $userHandler->getBySynergy($tutor['id']);
			if (isset($user['uid'])) {
				$eventList = $eventHandler->getByTutor($user['uid'], $from, $to, $_GET['filter']);

				if (is_array($eventList)) {
					$i = 0;
					$totalHours = 0;
					foreach ($eventList as $event) {

						$output = array('TUTOR_ID'		=> $user['uid'],
										'TUTOR_NAME'	=> Scrub::text($tutor['name']),
										);
						$page->setVar($output);

						if ($event['tutor1ID'] == $user['uid'])
							$processed = $event['tutor1Processed'];
						else
							$processed = $event['tutor2Processed'];

						$date = new Alaress_Date_Format($event['date'], 'U');
						$markedDate = new Alaress_Date_Format($event['marked'], 'U');

						$output = array('EVENT_ID'	=> $event['eventID'],
										'DATE'		=> $date->getDateOnly(),
										'MARKED'	=> $markedDate->getdateOnly(),
										'START'		=> $event['start'],
										'FINISH'	=> $event['finish'],
										'TUTORIAL'	=> Scrub::text($event['name']),
										'CODE'		=> !empty($event['code']) ? Scrub::text($event['code']) : 'N/A',
										'TYPE'		=> $event['typeName'],
										'ENROLLED'	=> $event['enrolled'],
										'ATTENDED'	=> $attendanceHandler->getCountByEvent($event['eventID']),
										'HOURS'		=> number_format($event['hours'], 2),
										'ON_OFF'	=> ++$i%2 ? 'dark' : '',
										'PROCESSED_SELECTED' => $processed == 'true' ? 'checked="checked"' : '',
										);
						$page->setVar($output);
						$page->parse('event_list_block', 'event_list', true);

						$totalHours += $event['hours'];
						$_SESSION['hoursReport'][] = array($event['eventID'], $user['uid']);

					}

					$output = array('TOTAL'			=> number_format($totalHours, 2),
									);
					$page->setVar($output);

					$page->parse('tutor_list_block', 'tutor_list', true);
					$page->setVar('event_list_block', '');
				}
			}
		}

		if ($i == 0)
			$page->setVar('has_events_block', '<p>No results to display.</p>');
		else
			$page->parse('has_events_block', 'has_events');

	} else
		$page->setVar('has_events_block', 'No tutors to display.');




	$page->outputPage();
}

?>
