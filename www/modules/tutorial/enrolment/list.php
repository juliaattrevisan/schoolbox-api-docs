<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/event.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/enrolment.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/attendance.php';

$page = new SitePage;
$eventHandler = new Event;
$enrolmentHandler = new Enrolment;
$attendanceHandler = new Attendance;
$userHandler = new User;

$page->setFile(TEMPLATE_content, 'tutorial/enrolment/list.html');
$page->setBlock(TEMPLATE_content, 'date_list', 'date_list_block');
$page->setBlock(TEMPLATE_content, 'has_enrolments', 'has_enrolments_block');
$page->setBlock('has_enrolments', 'enrolment_list', 'enrolment_list_block');
$page->setBlock('enrolment_list', 'remove', 'remove_block');
$page->setBlock('enrolment_list', 'remove_grey', 'remove_grey_block');
$page->setBlock('enrolment_list', 'attendance_list', 'attendance_list_block');
$page->setBlock('attendance_list', 'image', 'image_block');

$page->setTitle('Tutorial Management');

// check access
$groupHandler = new Group(TUTORIAL_SERVICE);
if (($permission = $groupHandler->getPermission(0)) < WRITE_ACCESS)
	error(19);

if (is_numeric($_GET['eventID'])) {

	$event = $eventHandler->getByID($_GET['eventID']);
	if (!isset($event['eventID']))
		error(2);

	// check user is one of the tutors
	if ($permission < FULL_ACCESS && !in_array($_SESSION['id'], array($event['tutor1ID'], $event['tutor2ID'])))
		error(19);


	$output = array('EVENT_NAME'	=> scrubText($event['name']),
					'EVENT_CODE'	=> scrubText($event['code']),
					'EVENT_ID'		=> $event['eventID'],
					'WEEK'			=> date("YW", $event['date']) - 1,
					);
	$page->setVar($output);

	$code = $event['typeID'] == 1 ? $event['code'] : '';
	$enrolmentList = $enrolmentHandler->getAllByEvent($code, $event['eventID']);
	if (is_array($enrolmentList)) {
		$i = 0;
		foreach ($enrolmentList as $enrolment) {
			$i++;
			$attendance = $attendanceHandler->getByStudent($enrolment['studentID'], $event['code']);
			$percentage = $attendance['expected'] > 0 ? number_format(($attendance['present']/$attendance['expected'])*100, 2) . " %" : 'N/A';
			$user = $userHandler->getByID($enrolment['studentID']);

			$output = array('STUDENT_ID'	=> $enrolment['studentID'],
							'STUDENT_NAME'	=> $userHandler->formatName($user),
							'PERCENTAGE'	=> $percentage,
							'ON_OFF' 		=>  ++$j%2? 'dark' : '',
							);
			$page->setVar($output);

			// remove button
			$page->setVar('remove_block', '');
			$page->setVar('remove_grey_block', '');
			if ($enrolmentHandler->isEnrolled($enrolment['studentID'], $event['code'], $event['eventID']))
				$page->parse('remove_block', 'remove');
			else
				$page->parse('remove_grey_block', 'remove_grey');

			$emailObj = new Email();
			$emailAll .= $emailObj->formatEmailFromArray($user) . ',';

			// output attendance per date
			$attendanceList = $attendanceHandler->getAllByStudent($enrolment['studentID'], $event['code'], $event['eventID']);
			if (is_array($attendanceList)) {
				$page->setVar('attendance_list_block', '');

				$attendanceDate = new Alaress_Date_Format($attendance['date']);

				foreach ($attendanceList as $attendance) {
					$output = array(
						'DATE' => date("j/n", $attendanceDate->getUnixTimestamp()),
						'ATTENDANCE' => $attendance['present'] == 'true' ? "attendance-tick" : (!empty($attendance['reason']) ? "attendance-cross-blue" : "attendance-cross"),
						'REASON' => scrubText($attendance['reason']),
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

		$emailObj = new Email();
		$email = $emailObj->formatEmailFromArray($_SESSION);

		$output = array('REPLY_TO'	=> $email,
						'EMAIL_ALL'	=> $emailAll,
						);

		$page->setVar($output);

		$page->parse('has_enrolments_block', 'has_enrolments');

	} else
		$page->setVar('has_enrolments_block', '<p>There are no students enrolled in this class.</p>');

	$page->outputPage();

} else
	error(2);

?>