<?php

require_once '../../conf/conf.php';
require_once SITE_root . '/include/sitePage.php';
require_once SITE_root . '/www/modules/tutorial/library/event.php';
require_once SITE_root . '/www/modules/tutorial/library/attendance.php';
require_once SITE_root . '/www/modules/tutorial/library/enrolment.php';
require_once SITE_root . '/library/group.php';

$page = new SitePage();
$page->setFile(TEMPLATE_content, 'tutorial/list-attendance.html');

$page->setBlock(TEMPLATE_content, 'event', 'event_block');
$page->setBlock('event', 'student', 'student_block');
$page->setBlock('student', 'reason_select', 'reason_select_block');

$page->setBlock(TEMPLATE_content, 'previous_link', 'previous_link_block');
$page->setBlock(TEMPLATE_content, 'next_link', 'next_link_block');

$page->setTitle('Tutorial Management');

$eventHandler = new Event;
$attendanceHandler = new Attendance;
$enrolmentHandler = new Enrolment;
$groupHandler = new Group(TUTORIAL_SERVICE);

// check access
if (($permission = $groupHandler->getPermission(0)) < WRITE_ACCESS)
	error(19);

if (is_array($_POST['reason']) && is_numeric($_POST['eventID'])) {
	
	// check user is one of the tutors
	$event = $eventHandler->getByID($_POST['eventID']);
	if ($permission < FULL_ACCESS && !in_array($_SESSION['id'], array($event['tutorID_1'], $event['tutorID_2'])))
		error(19);

	// check event isn't too old
	if ($event['date'] < strtotime("-1 month"))
		error(19);
	
	
	$attendanceHandler->deleteByEvent($_POST['eventID']);
	
	foreach ($_POST['reason'] as $studentID => $reason) {
		$attendance = array('eventID'	=> $_POST['eventID'],
							'studentID'	=> $studentID,
							'present'	=> isset($_POST['present'][$studentID]) ? 'true' : 'false',
							'reason'	=> $reason,
							);
		
		$attendanceHandler->save($attendance);
	}
	
	localRedir('/modules/tutorial/list-attendance.php?week=' . $_POST['week']);
}
elseif (is_numeric($_POST['userID']) && is_numeric($_POST['eventID'])) {

	// check user is one of the tutors
	$event = $eventHandler->getByID($_POST['eventID']);
	if ($permission < FULL_ACCESS && !in_array($_SESSION['id'], array($event['tutorID_1'], $event['tutorID_2'])))
		error(19);
		
	// add student
	require_once SITE_root . '/library/user.php';
	$userHandler = new User;
	$user = $userHandler->getByID($_POST['userID']);
	if (isset($user['uid'])) {
		$enrolment = array('code'		=> $_POST['code'],
						   'eventID'	=> $_POST['eventID'],
						   'studentID'	=> $user['uid'],
						   );
		$enrolmentHandler->create($enrolment);
	}
	localRedir('/modules/tutorial/list-attendance.php?week=' . $_POST['week']);
}
elseif (is_numeric($_GET['remove']) && isset($_GET['code']) && is_numeric($_GET['eventID'])) {

	// check user is one of the tutors
	$event = $eventHandler->getByID($_GET['eventID']);
	if ($permission < FULL_ACCESS && !in_array($_SESSION['id'], array($event['tutorID_1'], $event['tutorID_2'])))
		error(19);

	$enrolmentHandler->deleteByStudent($_GET['remove'], $_GET['code'], $_GET['eventID']);
	localRedir('/modules/tutorial/list-attendance.php?week=' . $_GET['week']);
}
else {
	
	// pagination
	$week = $_GET['week'];
	if (!is_numeric($week))
		$week = date("YW") - 1;
	
	$output = array('WEEK_END'	=> date(DATE_short_no_time, strtotime("+".substr($week, 4)." week Saturday", strtotime("1st Jan"))),
					'NEXT_WEEK'	=> $week+1,
					'PREVIOUS_WEEK'	=> $week-1,
					'WEEK'	=> $week,
					);
	$page->setVar($output);

	if ($week < date("YW", strtotime("31st Dec")))
		$page->parse('next_link_block', 'next_link');
	else
		$page->setVar('next_link_block', '');	
		
	if ($week > 0)
		$page->parse('previous_link_block', 'previous_link');
	else
		$page->setVar('previous_link_block', '');				
				
	
	
	// output events
	$eventList = $eventHandler->getWeek($week);
	$reasonList = $attendanceHandler->getReasonList();
	if (is_array($eventList)) {
		foreach ($eventList as $event) {
			$output = array('EVENT_ID'		=> $event['eventID'],
							'EVENT_DATE'	=> outputDate(DATE_short_no_time, $event['date']),
							'EVENT_NAME'	=> scrubText($event['name']),
							'EVENT_CODE'	=> scrubText($event['code']),
							'ACTION'		=> 'Save',
							'DISABLED'		=> $event['date'] < strtotime("-1 month")  ? "disabled" : "", // disable modifying attendance after 1 month
							'ALLOWADD'		=> date("W", $event['date']) < date("W")  ? "disabled" : "", // disable add student if date has passed
							);
			$page->setVar($output);
			$attendanceList = $attendanceHandler->getAllByEvent($event['eventID']);
			
			// output each student
			if (is_array($attendanceList)) {
				$page->setVar('student_block', '');
				foreach ($attendanceList as $attendance) {
					$output = array('STUDENT_ID'	=> $attendance['studentID'],
									'STUDENT_NAME'	=> formatName($attendance),
									'ATTENDANCE_SELECTED'	=> $attendance['present'] != 'false' ? 'checked="checked"' : '',
									'ACTION'	=> !empty($attendance['present']) ? "Modify" : "Save",
									);
					$page->setVar($output);
					
					// reason dropdown, $page->dropDown wasn't working for me
					if (is_array($reasonList)) {
						$page->setVar('reason_select_block', '');
						foreach ($reasonList as $reason) {
							$output = array('REASON_NAME'		=> scrubText($reason['reason']),
											'REASON_SELECTED'	=> $reason['reason'] == $attendance['reason'] ? 'selected="selected"' : '',
											);
							$page->setVar($output);
							$page->parse('reason_select_block', 'reason_select', true);
						}
					}
					//$page->dropDown('reason_select', $attendanceHandler->getReasonList(), $attendance['reason'], 'student', array('reason' => 'REASON'));
					
					$page->parse('student_block', 'student', true);
					

				}
			} else
				$page->setVar('student_block', '');
				
			$page->parse('event_block', 'event', true);
		}
	} else
		$page->setVar('event_block', '<p>No events to display.</p>');
	
	
	
	$page->outputPage();
}

?>