<?php

require_once '../../conf/conf.php';
require_once SITE_root . '/include/sitePage.php';
require_once SITE_root . '/www/modules/tutorial/library/event.php';

$page = new SitePage();
$page->setFile(TEMPLATE_content, 'tutorial/modify-event.html');
$page->setTitle("Tutorial Management");

if (!isStaff($_SESSION['gid']) && !$_SESSION['su'])
	error(19);

$eventHandler = new Event;

if (isset($_POST['name'])) {

	$date = ausDateToUnix($_POST['date']);
	$until = ausDateToUnix($_POST['repeat_until']);	
	$hour = $_POST['period'] == 'pm' && $_POST['hour'] < 12 ? $_POST['hour'] + 12 : $_POST['hour'];
	$tohour = $_POST['toperiod'] == 'pm' && $_POST['tohour'] < 12 ? $_POST['tohour'] + 12 : $_POST['tohour'];

	$event = array('name'			=> $_POST['name'],
				   'code'			=> $_POST['code'],
				   'location'		=> $_POST['location'],
				   'start'			=> $hour.":".(int)$_POST['minute'].":00",
				   'finish'			=> $tohour.":".(int)$_POST['tominute'].":00",
				   'tutorID_1'		=> $_POST['tutorID_1'],
				   'tutorID_2'		=> $_POST['tutorID_2'],
				   'description'	=> $_POST['description'],	
				   'visible'		=> isset($_POST['visible']) ? 'true' : 'false',	
				   'cancelled'		=> isset($_POST['cancelled']) ? 'true' : 'false',
				   );

	
					   
	$validRepeat = array('weekly' => '+1 week', 'fortnightly' => '+2 weeks', 'monthly' => '+1 month');
	if (isset($validRepeat[$_POST['repeat']]) && $until) {
		$i = 0;
		for ($date; $date <= $until; $date = strtotime($validRepeat[$_POST['repeat']], $date)) {
			if ($i++ > 100) break;
				
			$dateArray[] = date("Y-m-d", $date);
		}		
	
	} else {
		$dateArray[] = date("Y-m-d", $date);
	}	  
				  
	if(is_numeric($_POST['eventID'])) {
	
		$event['eventID'] = $_POST['eventID'];
		$event['date'] = $dateArray[0];
		$eventHandler->update($event);
		
		localRedir('/modules/tutorial/'. urldecode($_POST['return']));
	} 
	else {
		for ($i = 0; $i < count($dateArray); $i++) {
			$event['date'] = $dateArray[$i];
			$event['eventID'] = $eventHandler->create($event);
		}
		localRedir('/modules/tutorial/list-enrolment.php?eventID='. $event['eventID']);
	}
		
	
	
}
elseif (is_numeric($_GET['delete'])) {
	$eventHandler->delete($_GET['delete']);
	localRedir('/modules/tutorial/list-attendance.php');
}
elseif (is_numeric($_GET['eventID'])) {

	$event = $eventHandler->getByID($_GET['eventID']);
	if (!isset($event['eventID']))
		error(2);
	
	$page->setBlock(TEMPLATE_content, 'recurring_dates', 'recurring_dates_block');
	$page->setVar('recurring_dates_block', '');
	

	list($hour, $minute) = explode(":", $event['start']);
	list($minute, $period) = explode(" ", $minute);

	list($tohour, $tominute) = explode(":", $event['finish']);
	list($tominute, $toperiod) = explode(" ", $tominute);	

	$output = array('ACTION'		=> 'Modify',
					'EVENT_ID'		=> $event['eventID'],
					'NAME'			=> $event['name'],
					'CODE'			=> $event['code'],
					'LOCATION'		=> $event['location'],
					'DATE'			=> outputDate("d/m/Y", $event['date']),
					'HOUR'			=> $hour,
					'MIN'			=> $minute,
					'AM'			=> $period == 'AM' ? 'selected="selected"' : '',
					'PM'			=> $period == 'PM' ? 'selected="selected"' : '',
					'TOHOUR'		=> $tohour,
					'TOMIN'			=> $tominute,
					'AMTO'			=> $toperiod == 'AM' ? 'selected="selected"' : '',
					'PMTO'			=> $toperiod == 'PM' ? 'selected="selected"' : '',
					'DESCRIPTION'	=> $event['description'],
					'RETURN'		=> scrubText(urlencode($_REQUEST['return'])),
					'VISIBLE_SELECTED'	=> $event['visible'] == 'true' ? 'checked="checked"' : '',
					'CANCELLED_SELECTED'	=> $event['cancelled'] == 'true' ? 'checked="checked"' : '',
					
					
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

	$page->dropDown('tutor_list_1', $eventHandler->getTutorList(), $event['tutorID_1'], TEMPLATE_content, array('id' => 'TUTOR_ID', 'name' => 'TUTOR_NAME'));
	$page->dropDown('tutor_list_2', $eventHandler->getTutorList(), $event['tutorID_2'], TEMPLATE_content, array('id' => 'TUTOR_ID', 'name' => 'TUTOR_NAME'));
	
	
	$page->setVar($output);

} else {
	
	$output = array('ACTION'		=> 'Add',
					'EVENT_ID'		=> '',
					'NAME'			=> '',
					'CODE'			=> '',
					'LOCATION'		=> '',
					'DATE'			=> '',
					'HOUR'			=> '',
					'MIN'			=> '',
					'AM'			=> '',
					'PM'			=> '',
					'TOHOUR'		=> '',
					'TOMIN'			=> '',
					'TOPERIOD'		=> '',
					'DESCRIPTION'	=> '',
					'RETURN'		=> '',
					'VISIBLE_SELECTED'	=> 'checked="checked"',
					'CANCELLED_SELECTED'	=> '',
					
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

	$page->dropDown('tutor_list_1', $eventHandler->getTutorList(), $_SESSION['id'], TEMPLATE_content, array('id' => 'TUTOR_ID', 'name' => 'TUTOR_NAME'));
	$page->dropDown('tutor_list_2', $eventHandler->getTutorList(), '', TEMPLATE_content, array('id' => 'TUTOR_ID', 'name' => 'TUTOR_NAME'));
	
	
	$page->setVar($output);
}


$page->outputPage();

?>