<?php
require_once '../../conf/conf.php';
require_once SITE_root . '/include/sitePage.php';
require_once SITE_root . '/www/modules/tutorial/library/event.php';
require_once SITE_root . '/www/modules/tutorial/library/enrolment.php';
require_once SITE_root . '/www/modules/tutorial/library/attendance.php';

$page = new SitePage();
$page->setFile(TEMPLATE_content, 'tutorial/list-enrolment.html');
$page->setBlock(TEMPLATE_content, 'date_list', 'date_list_block');
$page->setBlock(TEMPLATE_content, 'enrolment_list', 'enrolment_list_block');
$page->setBlock('enrolment_list', 'attendance_list', 'attendance_list_block');
$page->setBlock('attendance_list', 'image', 'image_block');

$page->setTitle('Tutorial Management');

$eventHandler = new Event;
$enrolmentHandler = new Enrolment;
$attendanceHandler = new Attendance;

// check access
if (!$_SESSION['su'] && isStaff($_SESSION['gid']))
	error(19);

if (isset($_POST['keyword'])) {
	require_once SITE_root . '/library/user.php';
	$userHandler = new User;
	$userList = $userHandler->searchUser($_POST['keyword'], 3);
	if (is_array($userList)) {
		$enrolment = array('code'		=> $_POST['code'],
						   'eventID'	=> $_POST['eventID'],
						   'studentID'	=> $userList[0]['uid'],
						   );
		$enrolmentHandler->create($enrolment);
	}
	localRedir('/modules/tutorial/list-enrolment.php?eventID='.$_POST['eventID']);
}
elseif (is_numeric($_GET['delete']) && isset($_GET['code'])) {

	$enrolmentHandler->deleteByStudent($_GET['delete'], $_GET['code'], $_GET['eventID']);

	localRedir('/modules/tutorial/list-enrolment.php?eventID=' . $_GET['eventID']);

} else {
	
	$event = $eventHandler->getByID($_GET['eventID']);
	if (!isset($event['eventID'])) 
		error(2);
		
	$output = array('EVENT_NAME'	=> scrubText($event['name']),
					'EVENT_CODE'	=> scrubText($event['code']),
					'EVENT_ID'		=> $event['eventID'],
					'WEEK'			=> date("W", $event['date']) - 1,
					);
	$page->setVar($output);
	
	$enrolmentList = $enrolmentHandler->getAllByEvent($event['code'], $event['eventID']);
	if (is_array($enrolmentList)) {
		$i = 0;
		foreach ($enrolmentList as $enrolment) {
			$i++;
			$attendance = $attendanceHandler->getByStudent($enrolment['studentID'], $event['code']);
			$percentage = $attendance['expected'] > 0 ? number_format(($attendance['present']/$attendance['expected'])*100, 2) . " %" : 'N/A';
			$output = array('STUDENT_ID'	=> $enrolment['studentID'],
							'STUDENT_NAME'	=> $enrolment['studentName'],
							'PERCENTAGE'	=> $percentage,
							);
			$page->setVar($output);
			
			// output attendance per date
			$attendanceList = $attendanceHandler->getAllByStudent($enrolment['studentID'], $event['code'], $event['eventID']);
			if (is_array($attendanceList)) {
				$page->setVar('attendance_list_block', '');
				foreach ($attendanceList as $attendance) {
					$output = array('DATE'			=> outputDate("j/n", $attendance['date']),
									'ATTENDANCE' 	=> $attendance['present'] == 'true' ? "tick" : "cross",
									'REASON'		=> scrubText($attendance['reason']),
									);
					$page->setVar($output);
					
					// hide image if attendance not marked
					$page->setVar('image_block', '');
					if (!empty($attendance['present']))
						$page->parse('image_block', 'image');
					
					// only parse date headings once
					if ($i == 1)
						$page->parse('date_list_block', 'date_list', true);
						
					$page->parse('attendance_list_block', 'attendance_list', true);
				}
			}
			
			
			$page->parse('enrolment_list_block', 'enrolment_list', true);
		}
	} else
		$page->setVar('enrolment_list_block', '');
	
	$page->outputPage();
}

?>