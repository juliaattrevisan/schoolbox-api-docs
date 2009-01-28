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
					);
	$page->setVar($output);
	
	$enrolmentList = $enrolmentHandler->getAllByEvent($event['code'], $event['eventID']);
	if (is_array($enrolmentList)) {
		foreach ($enrolmentList as $enrolment) {
			$attendance = $attendanceHandler->getByStudent($enrolment['studentID'], $event['code']);
			$percentage = $attendance['expected'] > 0 ? number_format(($attendance['present']/$attendance['expected'])*100, 2) : 'N/A';
			$output = array('STUDENT_ID'	=> $enrolment['studentID'],
							'STUDENT_NAME'	=> $enrolment['studentName'],
							'ATTENDANCE'	=> $percentage,
							);
			$page->setVar($output);
			$page->parse('enrolment_list_block', 'enrolment_list', true);
		}
	} else
		$page->setVar('enrolment_list_block', '');
	
	$page->outputPage();
}

?>