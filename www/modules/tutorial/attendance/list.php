<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/event.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/attendance.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/enrolment.php';

$page = new SitePage();
$eventHandler = new Event;
$attendanceHandler = new Attendance;
$enrolmentHandler = new Enrolment;
$groupHandler = new Group(TUTORIAL_SERVICE);

$page->setFile(TEMPLATE_content, 'tutorial/attendance/list.html');
$page->setBlock(TEMPLATE_content, 'event', 'event_block');
$page->setBlock('event', 'has_students', 'has_students_block');
$page->setBlock('has_students', 'student', 'student_block');
$page->setBlock('student', 'reason_select', 'reason_select_block');
$page->setBlock(TEMPLATE_content, 'previous_link', 'previous_link_block');
$page->setBlock(TEMPLATE_content, 'next_link', 'next_link_block');

$page->setTitle('Tutorial Management');

// check access
if (($permission = $groupHandler->getPermission(0)) < WRITE_ACCESS)
	error(19);

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
			$j = 0;
			$page->setVar('student_block', '');
			$page->setVar('has_students_block', '');
			foreach ($attendanceList as $attendance) {
				$output = array('STUDENT_ID'	=> $attendance['studentID'],
								'STUDENT_NAME'	=> formatName($attendance),
								'ATTENDANCE_SELECTED'	=> $attendance['present'] != 'false' ? 'checked="checked"' : '',
								'ACTION'		=> !empty($attendance['present']) ? "Modify" : "Save",
								'ON_OFF'		=> ++$j%2 ? 'dark':'',
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
			$page->parse('has_students_block', 'has_students');
		} else
			$page->setVar('has_students_block', '<p>There are no students enrolled for this event.</p>');
			
		$page->parse('event_block', 'event', true);
	}
} else
	$page->setVar('event_block', '<p>No events to display.</p>');



$page->outputPage();

?>