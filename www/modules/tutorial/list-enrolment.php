<?php
require_once '../../conf/conf.php';
require_once SITE_root . '/include/sitePage.php';
require_once SITE_root . '/www/modules/tutorial/library/event.php';
require_once SITE_root . '/www/modules/tutorial/library/enrolment.php';
require_once SITE_root . '/www/modules/tutorial/library/attendance.php';

$page = new SitePage();
$page->setFile(TEMPLATE_content, 'tutorial/list-enrolment.html');
$page->setBlock(TEMPLATE_content, 'enrolment_list', 'enrolment_list_block');
$page->setTitle('Tutorial Management');

$eventHandler = new Event;
$enrolmentHandler = new Enrolment;
$attendanceHandler = new Attendance;

if (isset($_POST['keyword'])) {
	require_once SITE_root . '/library/user.php';
	$userHandler = new User;
	$userList = $userHandler->searchUser($_POST['keyword'], 3);
	if (is_array($userList)) {
		$enrolmentHandler = new Enrolment;
		$enrolmentHandler->create($_POST['code'], $userList[0]['uid']);
	}
	localRedir('/modules/tutorial/list-enrolment.php?code='.$_POST['code']);
}
elseif (is_numeric($_GET['delete']) && isset($_GET['code'])) {
	$enrolmentHandler->delete($_GET['code'], $_GET['delete']);
	localRedir('/modules/tutorial/list-enrolment.php?code=' . $_GET['code']);

} else {
	
	$event = $eventHandler->getByCode($_GET['code']);
	if (!isset($event['eventID'])) 
		error(2);
		
	$output = array('TUTORIAL_NAME'	=> scrubText($event['name']),
					'TUTORIAL_CODE'	=> scrubText($event['code']),
					);
	$page->setVar($output);
	
	$enrolmentList = $enrolmentHandler->getAllByCode($_GET['code']);
	if (is_array($enrolmentList)) {
		foreach ($enrolmentList as $enrolment) {
			$output = array('STUDENT_ID'	=> $enrolment['studentID'],
							'STUDENT_NAME'	=> $enrolment['studentName'],
							'ATTENDANCE'	=> $attendanceHandler->getStudentAttendance($event['code'], $enrolment['studentID']),
							);
			$page->setVar($output);
			$page->parse('enrolment_list_block', 'enrolment_list', true);
		}
	}
	
	$page->outputPage();
}

?>