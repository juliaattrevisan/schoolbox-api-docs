<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage;
$calendarHandler = new Calendar;
$groupHandler = new Group;


if (isset($_POST['date'])) {

	$dateObj = new Alaress_Date_Format($_POST['date']);
	$date = $dateObj->getUnixTimestamp();

	$untilObj = new Alaress_Date_Format($_POST['repeat_until']);
	$until = $untilObj->getUnixTimestamp();

	$event = array('owner' 		=> $_SESSION['id'],
				   'access'		=> $_POST['level'],
				   'sid'		=> $_POST['group'],
				   'campusList'	=> !empty($_POST['campus']) ? $_POST['campus'] : array($_SESSION['campus'][0]),
				   'groupList'	=> $_POST['groupCheck'],
				   'type'		=> $_POST['type'],
				   'note'		=> $_POST['note'],
				   'startTime'	=> (($_POST['period'] == 'pm' && $_POST['hour'] != 12 ? 12 : 0) + $_POST['hour']) . ":" . $_POST['minute'] . ":00",
				   'endTime'	=> (($_POST['toperiod'] == 'pm' && $_POST['tohour'] != 12 ? 12 : 0) + $_POST['tohour']) . ":" . $_POST['tominute'] . ":00",
                   'location'   => $_POST['location'],
                   'detail'     => $_POST['detail']
				  );

	$validRepeat = array('daily' => '+1 day', 'weekly' => '+1 week', 'fortnightly' => '+2 weeks', 'monthly' => '+1 month');
	if (isset($validRepeat[$_POST['repeat']]) && $until) {
		$i = 0;
		for ($date; $date <= $until; $date = strtotime($validRepeat[$_POST['repeat']], $date)) {
			if ($i++ > 100) break;

			$dateArray[] = date("Y-m-d", $date);
		}

	} else {
		$dateArray[] = date("Y-m-d", $date);
	}

	if(is_numeric($_POST['id'])) {

		$event['id'] = $_POST['id'];
		$event['date'] = $dateArray[0];
		$calendarHandler->update($event);
	}
	else {
		for ($i = 0; $i < count($dateArray); $i++) {
			$event['date'] = $dateArray[$i];
			$calendarHandler->create($event);
		}
	}

	$page->backToTheFuture(2);


} else if(isset($_GET['delete'])) {
	$calendar = $calendarHandler->getByID($_GET['delete']);
	$calendarHandler->delete($_GET['delete']);
	$date = explode("-", $calendar['date']);
	$date = array_reverse($date);
	$page->backToTheFuture(2);

} elseif (is_numeric($_GET['eventID'])) {



	$page->setFile(TEMPLATE_content, 'calendar/modify.html');
	$page->setTab("calendar");

	$page->setBlock(TEMPLATE_content, 'recurring_dates', 'recurring_dates_block');
	$page->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');

	$event = $calendarHandler->getByID($_GET['eventID']);
	if (!$event['id'])
		error(2);

	// check security perms
	if($calendarHandler->getPerm($event['id']) < 2)
		error(34);


	if ($event['startTime'] != "12:00AM")
		preg_match("/([\d]{1,2}):([\d]{2})(AM|PM)/", $event['startTime'], $startTime);
	else
		$startTime = array('','','','');

	if ($event['endTime'] != "12:00AM")
		preg_match("/([\d]{1,2}):([\d]{2})(AM|PM)/", $event['endTime'], $endTime);
	else
		$endTime = array('','','','');

	$output = array('TITLE'			=> HEADING." - Update Calender Event",
					'FUNCTION'		=> 'Update',
					'EVENT_ID'		=> $event['id'],
					'DATE'			=> date('d/m/Y', strtotime($event['date'])),
					'HOUR'			=> $startTime[1],
					'MIN'			=> $startTime[2],
					'AM'			=> $startTime[3] == 'AM' ? 'selected="selected"' : '',
					'PM'			=> $startTime[3] == 'PM' ? 'selected="selected"' : '',
					'TOHOUR'		=> $endTime[1],
					'TOMIN'			=> $endTime[2],
					'AMTO'			=> $endTime[3] == 'AM' ? 'selected="selected"' : '',
					'PMTO'			=> $endTime[3] == 'PM' ? 'selected="selected"' : '',
					'LEVEL'			=> '',
					'GROUP'			=> '',
					'DESCRIPTION' 	=> $event['note'],
                    'LOCATION'      => $event['location'],
                    'DETAIL'        => $event['detail'],
					'RETURN_TO'		=> Scrub::text($_GET['returnTo']),
					);

	$page->setVar($output);

	$page->dropDown('type_drop', $calendarHandler->getTypeList(), $event['type'],TEMPLATE_content, array('eventTypeID'=>'TYPE_NUMBER', 'eventType'=>'TYPE_NAME'));

	$campusList = $calendarHandler->getCampusList();
	if (count($campusList) > 1) {
		$page->checkList('campus_list', $campusList, $calendarHandler->getEventCampus($event['id']));
	} else {
		$page->setBlock(TEMPLATE_content, 'campus_list', 'campus_list_block');
		$page->setVar('campus_list_block', $campusList[0]['CAMPUS_NAME']);
	}

	$groupList = $groupHandler->involvedInCategoryList($_SESSION['id'],true, WRITE_ACCESS);
	$page->dropDown('group_drop', $groupList, $event['sid'], TEMPLATE_content, array('id'=>'UGROUP_ID','name'=>'UGROUP_NAME'));
	$page->checkList('group_list', $calendarHandler->getGroupList(), $calendarHandler->getEventGroup($event['id']));

	$levelList[] = array('LEVEL_ID'	=> 1, 'LEVEL_NAME'	=> 'Personal');
	if (count($groupList) > 0)
		$levelList[] = array('LEVEL_ID'	=> 2, 'LEVEL_NAME'	=> 'Group');
	if (count($campusList) > 0)
		$levelList[] = array('LEVEL_ID'	=> 3, 'LEVEL_NAME'	=> 'Campus');

	$page->dropDown('level_drop', $levelList, $event['access']);

	$page->parse('delete_button_block', 'delete_button', true);
	$page->setVar('recurring_dates_block', '');

	$page->outputPage();

} else {

	$page->setFile(TEMPLATE_content, 'calendar/modify.html');
	$page->setTab("calendar");
	$page->setBlock(TEMPLATE_content, 'recurring_dates', 'recurring_dates_block');
	$page->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');

	list($selectedDay, $selectedMonth, $selectedYear) = explode("-", $_GET['date']);

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
					'LEVEL'			=> '',
					'GROUP'			=> '',
                    'DESCRIPTION'	=> '',
                    'LOCATION'    	=> '',
                    'DETAIL'	    => '',
					'RETURN_TO'		=> Scrub::text($_GET['returnTo'])

					);
	$page->setVar($output);

	$page->dropDown('type_drop', $calendarHandler->getTypeList(), '',TEMPLATE_content, array('eventTypeID'=>'TYPE_NUMBER', 'eventType'=>'TYPE_NAME'));

	$campusList = $calendarHandler->getCampusList();
	if (count($campusList) > 1) {
		$page->checkList('campus_list', $campusList, array($_SESSION['campus'][0]));
	} else {
		$page->setBlock(TEMPLATE_content, 'campus_list', 'campus_list_block');
		$page->setVar('campus_list_block', $campusList[0]['CAMPUS_NAME']);
	}
	$page->checkList('group_list', $calendarHandler->getGroupList(), '');

	$groupList = $groupHandler->involvedInCategoryList($_SESSION['id'], true, WRITE_ACCESS);
	if($_GET['level'] == 2)
		$page->dropDown('group_drop', $groupList, $_GET['sid'], TEMPLATE_content, array('id'=>'UGROUP_ID','name'=>'UGROUP_NAME'));
	else
		$page->dropDown('group_drop', $groupList, '',TEMPLATE_content, array('id'=>'UGROUP_ID','name'=>'UGROUP_NAME'));

	$levelList[] = array('LEVEL_ID'	=> 1, 'LEVEL_NAME'	=> 'Personal');
	if (count($groupList) > 0)
		$levelList[] = array('LEVEL_ID'	=> 2, 'LEVEL_NAME'	=> 'Group');
	if (count($campusList) > 0)
		$levelList[] = array('LEVEL_ID'	=> 3, 'LEVEL_NAME'	=> 'Campus');

	$page->dropDown('level_drop', $levelList, $_GET['level']);

	$page->setVar('delete_button_block', '');
	$page->parse('recurring_dates_block', 'recurring_dates');

	$page->outputPage();

}

?>