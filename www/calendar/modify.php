<?php

require_once "include/permission.php";
require_once "../library/calendar.php";

$calendarHandler = new Calendar;

if (isset($_POST['date'])) {

	$date = explode("/", $_POST['date']);
	krsort($date);
	$event = array('owner' 		=> $_SESSION['id'],
				   'access'		=> $_POST['level'],
				   'sid'		=> $_POST['group'],
				   'campus'		=> $_POST['campus'],
				   'group'		=> $_POST['groupCheck'],
				   'priority'	=> $_POST['priority'],
				   'note'		=> $_POST['note'],
				   'date'		=> implode($date),
				   'startTime'	=> (($_POST['period'] == 'PM' ? 12 : 0) + $_POST['hour']) . ":" . $_POST['minute'] . ":00",
				   'endTime'	=> (($_POST['toperiod'] == 'PM' ? 12 : 0) + $_POST['tohour']) . ":" . $_POST['tominute'] . ":00",
				  );
	if(is_numeric($_POST['id'])) {
	
		$event['id'] = $_POST['id'];
		$calendarHandler->update($event);
	} 
	else {

		$event['id'] = $calendarHandler->create($event);
	}

	localRedir('/calendar/' . ($_POST['return'] == 'month' ? 'month' : 'week') . '/' . str_replace("/", "-", $_POST['date']));


} else if(isset($_POST['delete']) && is_numeric($_POST['id'])) {
	$calendarHandler->delete($_POST['id']);
	localRedir("/calendar/" . ($_POST['return'] == 'month' ? 'month' : 'week') . "/" . str_replace("/", "-", $_POST['date']));
	
} elseif (is_numeric($this->arg[0])) {

	$this->setFile(TEMPLATE_content, 'calendar/modify.html');
	
	$this->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');

	$event = $calendarHandler->getByID($this->arg[0]);
	if (!$event['id'])
		error(2);
	
	//check security perms
	if($event['access'] == 1 && $_SESSION['id'] != $event['owner']) {
		if($calendarHandler->checkSecurity($event['access'], $event['id']) < 2)
			error(34);
	}
	
	list($hour, $minute) = explode(":", $event['startTime']);
	list($tohour, $tominute) = explode(":", $event['endTime']);
	
	$am = $hour < 12 ? true : false;
	$amto = $tohour < 12 ? true : false;
	
	$output = array('TITLE'			=> HEADING." - Update Calender Event",
					'FUNCTION'		=> 'Update',
					'EVENT_ID'		=> $event['id'],
					'DATE'			=> date('d/m/Y', strtotime($event['date'])),
					'HOUR'			=> $hour,
					'MIN'			=> $minute,
					'AM'			=> $hour <  12 ? 'checked="checked"' : '',
					'PM'			=> $hour >= 12 ? 'checked="checked"' : '',
					'TOHOUR'		=> $tohour,
					'TOMIN'			=> $tominute,
					'AMTO'			=> $tohour <  12 ? 'checked="checked"' : '',
					'PMTO'			=> $tohour >= 12 ? 'checked="checked"' : '',
					'LEVEL'			=> '',
					'GROUP'			=> '',
					'DESCRIPTION' 	=> $event['note'],
					'RETURN_TO'		=> scrubText($this->arg[1]),
					);
					
	$this->setVar($output);

	$this->dropDown('priority_drop', $calendarHandler->getPriorityList(), $event['priority']);
	
	$campusList = $calendarHandler->getCampusList();
	$this->checkList('campus_list', $campusList, $calendarHandler->getEventCampus($event['id']));

	if (count($campusList) == 1)
		$this->setVar('CAMPUS_ID_READONLY', 'onclick="return false;"');
	else
		$this->setVar('CAMPUS_ID_READONLY', '');

	$groupList = $calendarHandler->getGroupList();
	$this->checkList('group_drop', $groupList, $event['sid']);
	$this->checkList('group_list', $groupList, $calendarHandler->getEventGroup($event['id']));
	

	$levelList[] = array('LEVEL_ID'	=> 1, 'LEVEL_NAME'	=> 'Personal');
	if (count($groupList) > 0)
		$levelList[] = array('LEVEL_ID'	=> 2, 'LEVEL_NAME'	=> 'Group');
	if (count($campusList) > 0)
		$levelList[] = array('LEVEL_ID'	=> 3, 'LEVEL_NAME'	=> 'Campus');
	
	$this->dropDown('level_drop', $levelList, $event['access']);

	$this->parse('delete_button_block', 'delete_button', true);
			
	
} else {

	$this->setFile(TEMPLATE_content, 'calendar/modify.html');
	$this->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');

	list($selectedDay, $selectedMonth, $selectedYear) = explode("-", $this->arg[0]);
	
	$selectedDate = @mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear);
	if ($selectedDate <= 0) 
		$selectedDate = time();

	$output = array('TITLE'			=> HEADING." - Add Single Event",
					'FUNCTION'		=> 'Add',
					'EVENT_ID'		=> '',
					'DATE'			=> date("d/m/Y", $selectedDate),
					'HOUR'			=> '',
					'MIN'			=> '',
					'AM'			=> '',
					'PM'			=> '',
					'TOHOUR'		=> '',
					'TOMIN'			=> '',
					'AMTO'			=> '',
					'PMTO'			=> '',
					'PRIORITY4'		=> '',
					'PRIORITY3'		=> '',
					'PRIORITY2'		=> '',
					'PRIORITY1'		=> '',
					'LEVEL'			=> '',
					'GROUP'			=> '',
					'DESCRIPTION'	=> '',
					'RETURN_TO'		=> scrubText($this->arg[1])
					
					);
	$this->setVar($output);
	
	$this->dropDown('priority_drop', $calendarHandler->getPriorityList(), '');
	
	$campusList = $calendarHandler->getCampusList();
	$this->checkList('campus_drop', $campusList, 1);

	if (count($campusList) == 1)
		$this->setVar('CAMPUS_ID_READONLY', 'onclick="return false;"');
	else
		$this->setVar('CAMPUS_ID_READONLY', '');
		
	$groupList = $calendarHandler->getGroupList();
	$this->checkList('group_drop', $groupList, '');
	

	$levelList[] = array('LEVEL_ID'	=> 1, 'LEVEL_NAME'	=> 'Personal');
	if (count($groupList) > 0)
		$levelList[] = array('LEVEL_ID'	=> 2, 'LEVEL_NAME'	=> 'Group');
	if (count($campusList) > 0)
		$levelList[] = array('LEVEL_ID'	=> 3, 'LEVEL_NAME'	=> 'Campus');
	
	$this->dropDown('level_drop', $levelList, '');

	$this->setVar('delete_button_block', '');
	
}

?>