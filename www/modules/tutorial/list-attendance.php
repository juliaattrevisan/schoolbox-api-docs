<?php
require_once '../../conf/conf.php';
require_once SITE_root . '/include/sitePage.php';
require_once SITE_root . '/www/modules/tutorial/library/event.php';
require_once SITE_root . '/www/modules/tutorial/library/attendance.php';

$page = new SitePage();
$page->setFile(TEMPLATE_content, 'tutorial/list-attendance.html');
$page->setBlock(TEMPLATE_content, 'event', 'event_block');
$page->setBlock('event', 'student', 'student_block');
$page->setBlock('student', 'reason_select', 'reason_select_block');
$page->setTitle('Tutorial Management');

$eventHandler = new Event;
$attendanceHandler = new Attendance;

if (is_array($_POST['reason'])) {
	foreach ($_POST['reason'] as $studentID => $reason)
		$attendanceHandler->setAttendance($_POST['eventID'], $studentID, isset($_POST['present'][$studentID]), $reason);
	localRedir('/modules/tutorial/list-attendance.php?week=' . $_POST['week']);
}
elseif (isset($_GET['ajax'])) {
	
	list($field, $eventID, $studentID) = explode("_", $_GET['ajax']);
	if (is_numeric($eventID) && is_numeric($studentID)) {
		switch ($field) {
			case 'reason':
				$attendanceHandler->setReason($eventID, $studentID, $_GET['value']);
				break;
			case 'present':
				$value = $_GET['value'] == 'true' ? true : false;
				$attendanceHandler->setPresent($eventID, $studentID, $value);
				break;
		}
	}
	
} 
elseif (isset($_POST['keyword'])) {
	require_once SITE_root . '/www/modules/tutorial/library/enrolment.php';
	require_once SITE_root . '/library/user.php';
	$userHandler = new User;
	$userList = $userHandler->searchUser($_POST['keyword'], 3);
	if (is_array($userList)) {
		$enrolmentHandler = new Enrolment;
		$enrolmentHandler->create($_POST['code'], $userList[0]['uid']);
	}
	localRedir('/modules/tutorial/list-attendance.php?week=' . $_POST['week']);
}
elseif (is_numeric($_GET['remove']) && is_numeric($_GET['eventID'])) {
	$attendanceHandler->deleteEnrolment($_GET['eventID'], $_GET['remove']);
	localRedir('/modules/tutorial/list-attendance.php?week=' . $_GET['week']);
}
else {

	$page->setBlock(TEMPLATE_content, 'previous_link', 'previous_link_block');
	$page->setBlock(TEMPLATE_content, 'next_link', 'next_link_block');
	
	$week = $_GET['week'];
	if (!is_numeric($week))
		$week = date("W") - 1;
	
	$output = array('WEEK_END'	=> date(DATE_short_no_time, strtotime("+$week week Saturday", strtotime("1st Jan"))),
					'NEXT_WEEK'	=> $week+1,
					'PREVIOUS_WEEK'	=> $week-1,
					'WEEK'	=> $week,
					);

	if ($week < date("W", strtotime("31st Dec")))
		$page->parse('next_link_block', 'next_link');
	else
		$page->setVar('next_link_block', '');	
		
	if ($week > 0)
		$page->parse('previous_link_block', 'previous_link');
	else
		$page->setVar('previous_link_block', '');				
				
	$page->setVar($output);
		
	$eventList = $eventHandler->getWeek($week);
	$reasonList = $attendanceHandler->getReasonList();
	if (is_array($eventList)) {
		foreach ($eventList as $event) {
			$output = array('EVENT_ID'		=> $event['eventID'],
							'EVENT_DATE'	=> outputDate(DATE_short_no_time, $event['date']),
							'EVENT_NAME'	=> scrubText($event['name']),
							'EVENT_CODE'	=> scrubText($event['code']),
							);
			$page->setVar($output);
			$attendanceList = $attendanceHandler->getAllByEvent($event['eventID']);
			if (is_array($attendanceList)) {
				$page->setVar('student_block', '');
				foreach ($attendanceList as $attendance) {
					$output = array('STUDENT_ID'	=> $attendance['studentID'],
									'STUDENT_NAME'	=> $attendance['studentName'],
									'ATTENDANCE_SELECTED'	=> $attendance['present'] == 'true' ? 'checked="checked"' : '',
									);
					$page->setVar($output);
					
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