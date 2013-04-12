<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/event.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';

$page = new SitePage();
$page->setFile(TEMPLATE_content, 'tutorial/event/modify.html');
$page->setTitle("Tutorial Management");

$eventHandler = new Event;
$groupHandler = new Group(TUTORIAL_SERVICE);
$userHandler = new User;

// check access
if (($permission = $groupHandler->getPermission(0)) < WRITE_ACCESS)
	$page->error(19);

if (is_numeric($_GET['eventID'])) {

	$event = $eventHandler->getByID($_GET['eventID']);
	if (!isset($event['eventID']))
		$page->error(2);

	// check user is one of the tutors
	if ($permission < FULL_ACCESS && !in_array($_SESSION['id'], array($event['tutor1ID'], $event['tutor2ID'])))
		$page->error(19);

	$page->setBlock(TEMPLATE_content, 'sequence_link', 'sequence_link_block');
	$page->setBlock(TEMPLATE_content, 'date', 'date_block');
	$page->setBlock(TEMPLATE_content, 'date_sequence', 'date_sequence_block');
	$page->setBlock(TEMPLATE_content, 'recurring_dates', 'recurring_dates_block');
	$page->setBlock(TEMPLATE_content, 'delete_sequence', 'delete_sequence_block');

	$page->setVar('recurring_dates_block', '');

	$page->setVar('date_block', '');
	$page->setVar('date_sequence_block', '');
	$page->setVar('sequence_link_block', '');
	$page->setVar('delete_sequence_block', '');

	if (!isset($_GET['sequence'])) {
		/* modify page */
		$page->parse('date_block', 'date');
		$page->setVar('HEADING', 'Modify Tutorial');
		$page->parse('sequence_link_block', 'sequence_link');
	} else {
		/* modify sequence page */
		$page->setVar('HEADING', 'Modify Tutorial Sequence');
		$page->parse('date_sequence_block', 'date_sequence');
		$page->parse('delete_sequence_block', 'delete_sequence');
	}

	$sequence = $eventHandler->getSequence($event['sequenceID']);

	list($hour, $minute) = explode(":", $event['start']);
	list($minute, $period) = explode(" ", $minute);

	list($tohour, $tominute) = explode(":", $event['finish']);
	list($tominute, $toperiod) = explode(" ", $tominute);

	$date = new Alaress_Date_Format($event['startDate'], 'y-m-d');
	$startDate = new Alaress_Date_Format($sequence['start_date'], 'U');
	$endDate = new Alaress_Date_Format($sequence['end_date'], 'U');

	$output = array('ACTION'		=> 'Modify',
					'ACTION_TYPE'	=> isset($_GET['sequence']) ? 'sequence' : 'single',
					'EVENT_ID'		=> $event['eventID'],
					'SEQUENCE_ID'	=> $event['sequenceID'],
					'NAME'			=> $event['name'],
					'CODE'			=> $event['code'],
					'LOCATION'		=> $event['location'],
					'DATE'			=> $date->getLocal(),
					'HOUR'			=> $hour,
					'MIN'			=> $minute,
					'AM'			=> $period == 'AM' ? 'selected="selected"' : '',
					'PM'			=> $period == 'PM' ? 'selected="selected"' : '',
					'TOHOUR'		=> $tohour,
					'TOMIN'			=> $tominute,
					'AMTO'			=> $toperiod == 'AM' ? 'selected="selected"' : '',
					'PMTO'			=> $toperiod == 'PM' ? 'selected="selected"' : '',
					'DESCRIPTION'	=> $event['description'],
					'CANCELLED_SELECTED'	=> $event['cancelled'] == 'true' ? 'checked="checked"' : '',

					'NAME_ENABLED'	=> $event['typeID']==2 ? 'table-row' : 'none',
					'CLASS_ENABLED'	=> $event['typeID']==1 ? 'table-row' : 'none',

					'WEEKDAY_SUNDAY_SELECTED'		=> '',
					'WEEKDAY_MONDAY_SELECTED'		=> 'selected="selected"',
					'WEEKDAY_TUESDAY_SELECTED'		=> '',
					'WEEKDAY_WEDNESDAY_SELECTED'	=> '',
					'WEEKDAY_THURSDAY_SELECTED'		=> '',
					'WEEKDAY_FRIDAY_SELECTED'		=> '',
					'WEEKDAY_SATURDAY_SELECTED'		=> '',

					'RECURRENCE_ONCE_SELECTED'		=> '',
					'RECURRENCE_WEEK_SELECTED'		=> 'selected="selected"',
					'RECURRENCE_FORTNIGHT_SELECTED'	=> '',

					'TOTAL_EVENTS'	=> $sequence['total'],
					'START_DATE'	=> $startDate->getLocal(),
					'END_DATE'		=> $endDate->getLocal(),
					);

	$tutor1 = $userHandler->getByID($event['tutor1ID']);
	$tutor2 = $userHandler->getByID($event['tutor2ID']);

	$page->dropDown('tutor_list_1', $eventHandler->getTutorList(), $tutor1['synergy_id'], TEMPLATE_content, array('id' => 'TUTOR_ID', 'name' => 'TUTOR_NAME'));
	$page->dropDown('tutor_list_2', $eventHandler->getTutorList(), $tutor2['synergy_id'], TEMPLATE_content, array('id' => 'TUTOR_ID', 'name' => 'TUTOR_NAME'));

	$page->dropDown('type_list', $eventHandler->getTypeList(), $event['typeID'], TEMPLATE_content, array('typeID' => 'TYPE_ID', 'type' => 'TYPE_NAME'));
	$page->dropDown('class_list', $eventHandler->getClassList(), $event['classID'], TEMPLATE_content, array('classID' => 'CLASS_ID', 'class' => 'CLASS_NAME', 'code' => 'CLASS_CODE'));

	$page->setVar($output);

} else {


	$page->setBlock(TEMPLATE_content, 'sequence_link', 'sequence_link_block');
	$page->setBlock(TEMPLATE_content, 'date_sequence', 'date_sequence_block');
	$page->setBlock(TEMPLATE_content, 'delete_sequence', 'delete_sequence_block');


	$page->setVar('sequence_link_block', '');
	$page->setVar('date_sequence_block', '');
	$page->setVar('delete_sequence_block', '');

	$output = array('ACTION'		=> 'Add',
					'HEADING'		=> 'Add Tutorial',
					'EVENT_ID'		=> '',
					'NAME'			=> '',
					'CODE'			=> '',
					'LOCATION'		=> '',
					'DATE'			=> date("d/m/Y"),
					'HOUR'			=> '',
					'MIN'			=> '',
					'AM'			=> '',
					'PM'			=> '',
					'TOHOUR'		=> '',
					'TOMIN'			=> '',
					'TOPERIOD'		=> '',
					'DESCRIPTION'	=> '',
					'CANCELLED_SELECTED'	=> '',

					'NAME_ENABLED'	=> 'none',
					'CLASS_ENABLED'	=> 'table-row',

					'WEEKDAY_SUNDAY_SELECTED'		=> '',
					'WEEKDAY_MONDAY_SELECTED'		=> 'selected="selected"',
					'WEEKDAY_TUESDAY_SELECTED'		=> '',
					'WEEKDAY_WEDNESDAY_SELECTED'	=> '',
					'WEEKDAY_THURSDAY_SELECTED'		=> '',
					'WEEKDAY_FRIDAY_SELECTED'		=> '',
					'WEEKDAY_SATURDAY_SELECTED'		=> '',

					'RECURRENCE_ONCE_SELECTED'		=> '',
					'RECURRENCE_WEEK_SELECTED'		=> 'selected="selected"',
					'RECURRENCE_FORTNIGHT_SELECTED'	=> '',
					);

	$page->dropDown('tutor_list_1', $eventHandler->getTutorList(), $_SESSION['sid'], TEMPLATE_content, array('id' => 'TUTOR_ID', 'name' => 'TUTOR_NAME'));
	$page->dropDown('tutor_list_2', $eventHandler->getTutorList(), '', TEMPLATE_content, array('id' => 'TUTOR_ID', 'name' => 'TUTOR_NAME'));

	$page->dropDown('type_list', $eventHandler->getTypeList(), '', TEMPLATE_content, array('typeID' => 'TYPE_ID', 'type' => 'TYPE_NAME'));
	$page->dropDown('class_list', $eventHandler->getClassList(), '', TEMPLATE_content, array('classID' => 'CLASS_ID', 'class' => 'CLASS_NAME', 'code'	=> 'CLASS_CODE'));


	$page->setVar($output);
}


$page->outputPage();

?>
