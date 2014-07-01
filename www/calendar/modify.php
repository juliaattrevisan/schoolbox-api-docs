<?php

/* Direct access to modify.php -_-
 */
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');
$page = new SitePage();

$page->setFile(TEMPLATE_content, 'calendar/modify.html');
$page->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');
$page->setBlock(TEMPLATE_content, 'recurring_dates', 'recurring_dates_block');
$page->setBlock(TEMPLATE_content, 'recurring_reminder', 'recurring_reminder_block');

/* Modify existing event
 */
if (is_numeric($_GET['eventID'])) {
	$mapper = new \Schoolbox\Calendar\EventMapper();

	try {
		$event = $mapper->findById($_GET['eventID']);
	}
	catch (\Exception $e) {
		error(160);
		exit;
	}

	/* Default values
	 */
	$defaultTypeId = $event->getType()->getId();
	$defaultSid = $event->getServiceId();
	$defaultAccessLevel = $event->getAccess();

	/* Only provide publish date if one was provided and if it
	 * wasn't useless somehow
	 */
	if ($event->getPublishDateTime() && strlen($event->getPublishDateTime()->getDatabase())) {
		$publishDate = $event->getPublishDateTime()->getLocalShort();
	}
	else {
		$publishDate = '';
	}

	/* Miscellaneous setvarring
	 * Hide advanced options by default
	 */
	$page->setVar(array(
		'FUNCTION' => 'Update',
		'SHOW_ADVANCED' => '',
		'ADVANCED_SHOW' => '',
		'ADVANCED_CLOSE' => 'hidden',
	));

	$page->setVar(array(
		'EVENT_ID' => $event->getId(),
		'EVENT_USER_ID' => $_SESSION['user']->getId(),
		'EVENT_AUTHOR_ID' => $_SESSION['user']->getId(),
		'EVENT_NOTE' => $event->getNote(),
		'EVENT_ALLDAY_CHECKED' => $event->getAllDay() ? 'checked="checked"' : '',
		'EVENT_TIMEINPUTS' => $event->getAllDay() ? 'hide' : '',
		'EVENT_START_DATE' => $event->getStartDateTime()->getUsingFormat('d/m/Y'),
		'EVENT_START_HOUR' => $event->getStartDateTime()->getUsingFormat('H'),
		'EVENT_START_MIN' => $event->getStartDateTime()->getUsingFormat('i'),
		'EVENT_START_AM' => $event->getStartDateTime()->getUsingFormat('A') == 'AM' ? 'selected="selected"' : '',
		'EVENT_START_PM' => $event->getStartDateTime()->getUsingFormat('A') == 'PM' ? 'selected="selected"' : '',
		'EVENT_END_DATE' => $event->getEndDateTime()->getUsingFormat('d/m/Y'),
		'EVENT_END_HOUR' => $event->getEndDateTime()->getUsingFormat('H'),
		'EVENT_END_MIN' => $event->getEndDateTime()->getUsingFormat('i'),
		'EVENT_END_AM' => $event->getEndDateTime()->getUsingFormat('A') == 'AM' ? 'selected="selected"' : '',
		'EVENT_END_PM' => $event->getEndDateTime()->getUsingFormat('A') == 'PM' ? 'selected="selected"' : '',
		'EVENT_PUBLISH_DATE' => $publishDate,
		'EVENT_LOCATION' => $event->getLocation(),
		'EVENT_DETAIL' => $event->getDetail(),
		'EVENT_REDIRECT' => $event->setupRedirect($_GET['returnTo'], $event->getStartDateTime()->getUsingFormat('d-m-Y'), $_GET['sid'], $_GET['userId']),
	));

	/* Recurrence
	 */
	// Is there a recurrence? If so don't show the repeat selector.
	if ($event->getSeriesId() == null) {
		$page->setVar('recurring_reminder_block', '');
		$page->parse('recurring_dates_block', 'recurring_dates');
	}
	else {
		$eventRecurrenceMapper = new \Schoolbox\Calendar\EventRecurrenceMapper($mapper);
		$recurrence = $eventRecurrenceMapper->getSeriesRecurrence($event);

		$page->setVar('EVENT_REC_FREQ', ucfirst($recurrence['freq']));
		$page->setVar('EVENT_REC_START', $recurrence['start']->getLongDateOnly());
		$page->setVar('EVENT_REC_END', $recurrence['end']->getLongDateOnly());
		$page->parse('recurring_reminder_block', 'recurring_reminder');
		$page->setVar('recurring_dates_block', '');
	}

	$page->parse('delete_button_block', 'delete_button');
}
/* Create new event
 */
else {

	/* Set up default dates
	 */
	$event = new \Schoolbox\Calendar\Event();
	$event->setupDates($_GET['date']);

	$defaultTypeId = '';
	$defaultSid = '';
	$defaultAccessLevel = '';

	/* Miscellaneous setvarring
	 * Hide advanced options by default
	 */
	$page->setVar(array(
		'FUNCTION' => 'Add',
		'SHOW_ADVANCED' => '',
		'ADVANCED_SHOW' => '',
		'ADVANCED_CLOSE' => 'hidden',
	));

	$page->setVar(array(
		'EVENT_ID' => '',
		'EVENT_USER_ID' => $_SESSION['user']->getId(),
		'EVENT_AUTHOR_ID' => $_SESSION['user']->getId(),
		'EVENT_NOTE' => '',
		'EVENT_ALLDAY_CHECKED' => 'checked="checked"',
		'EVENT_TIMEINPUTS' => 'hide',
		'EVENT_START_DATE' => $event->getStartDateTime()->getUsingFormat('d/m/Y'),
		'EVENT_START_HOUR' => '',
		'EVENT_START_MIN' => '',
		'EVENT_START_AM' => '',
		'EVENT_START_PM' => '',
		'EVENT_END_DATE' => $event->getEndDateTime()->getUsingFormat('d/m/Y'),
		'EVENT_END_HOUR' => '',
		'EVENT_END_MIN' => '',
		'EVENT_END_AM' => '',
		'EVENT_END_PM' => '',
		'EVENT_PUBLISH_DATE' => '',
		'EVENT_LOCATION' => '',
		'EVENT_DETAIL' => '',
		'EVENT_REDIRECT' => $event->setupRedirect($_GET['returnTo'], $event->getStartDateTime()->getUsingFormat('d-m-Y'), $_GET['sid'], $_GET['userId']),
	));

	/* Recurrence
	 */
	$page->setVar(array(
		'recurring_reminder_block' => '',
		'delete_button_block' => ''
	));

	$page->parse('recurring_dates_block', 'recurring_dates');
}

/* Event Types
 */
$calendarEventTypeMapper = new \Schoolbox\Calendar\EventTypeMapper();
$allEventTypes = $calendarEventTypeMapper->findAll();

$page->dropDown('type_drop', $allEventTypes, $defaultTypeId, TEMPLATE_content, array(
	'id' => 'EVENT_EVENTTYPE_ID',
	'type' => 'EVENT_EVENTTYPE_NAME'
));

/* Only provide Group option if groups have been defined
 */
$groupHandler = new \Group();
$groupList = $groupHandler->involvedInCategoryList($_SESSION['user']->getId(), true, WRITE_ACCESS);

/* Only provide Campus options if campuses have been defined
 */
$campusMapper = new \Schoolbox\CampusMapper();
$campusList = $campusMapper->getAvailableCampusesForUser($_SESSION['user']);

/* Render all the dropdowns
 */

/* Event Access Levels
 * Requires both $groupList and $campusList for optional values
 */
$page->dropDown('event_level_drop', new \Schoolbox\Calendar\EventLevelCollection($groupList, $campusList), $defaultAccessLevel, TEMPLATE_content, array(
	'id' => 'EVENT_EVENTLEVEL_ID',
	'name' => 'EVENT_EVENTLEVEL_NAME',
));

/* Group dropdown when using Group Event Level
 */

$page->dropDown('event_group_drop', $groupList, $defaultSid, TEMPLATE_content, array(
	'id' => 'EVENT_GROUP_ID',
	'name' => 'EVENT_GROUP_NAME'
));

/* Campus Checkbox Array when using Campus Event Level
 */
if ($campusList->count()) {
	$allCampuses = array();

	foreach($campusList as $current) {
		$allCampuses[] = array(
			'id' => $current->getId(),
			'name' => $current->getName()
		);
	}

	$page->checkList('event_campus_list', $allCampuses, array($_SESSION['campus'][0]), TEMPLATE_content, array(
		'id' => 'EVENT_CAMPUS_ID',
		'name' => 'EVENT_CAMPUS_NAME',
	));
}
else {
	$page->setBlock(TEMPLATE_content, 'event_campus_list', 'event_campus_list_block');
	$page->setVar('event_campus_list_block', '');
}

/* Roles, when Campus Event Level is used
 */
$roleMapper = new \Schoolbox\RoleMapper();
$roleList = $roleMapper->findAll();

/* Roles checkbox array when using Campus Event Level
 * This really needs to be updated - why is checkList behaviour different to
 * the dropDown one when it comes to collections?
 */
$allRoles = array();

foreach($roleList as $current) {
	$allRoles[] = array(
		'id' => $current->getId(),
		'name' => $current->getName()
	);
}

$page->checkList('event_role_list', $allRoles, $defaultSid, TEMPLATE_content, array(
	'id' => 'EVENT_ROLE_ID',
	'name' => 'EVENT_ROLE_NAME'
));

$page->outputPage();



//require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');
//
//$page = new SitePage();
//$calendarHandler = new Calendar();
//$groupHandler = new Group();
//
//// if modifying an event
//if (is_numeric($_GET['eventID'])) {
//    $page->setFile(TEMPLATE_content, 'calendar/modify.html');
//    $page->setTab("calendar");
//    $page->setTitle('Update Calendar Event');
//
//    $page->setBlock(TEMPLATE_content, 'recurring_dates', 'recurring_dates_block');
//    $page->setBlock(TEMPLATE_content, 'recurring_reminder', 'recurring_reminder_block');
//    $page->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');
//
//    $event = $calendarHandler->getByID($_GET['eventID']);
//
//    if (!$event['id']) {
//        error(2);
//    }
//
//    // check security perms
//    if ($calendarHandler->getPerm($event['id']) < 2) {
//        error(34);
//    }
//
//    if ($event['publish_date'] != null && !preg_match('/^0000-00-00/', $event['publish_date'])) {
//        $pubDateObject = new Alaress_Date_Format($event['publish_date'], 'y-m-d h:i:s');
//        $publishDate = $pubDateObject->getLocalShort();
//    } else {
//        $publishDate = "";
//    }
//
//    $startDate = new Alaress_Date_Format(
//        new DateTime($event['startDate'].' '.$event['startTime']),
//        'y-m-d h:i:s'
//    );
//    $endDate = new Alaress_Date_Format(
//        new DateTime($event['endDate'].' '.$event['endTime']),
//        'y-m-d h:i:s'
//    );
//
//    $output = array(
//        'FUNCTION' => 'Update',
//        'EVENT_ID' => $event['id'],
//        'EVENT_USER_ID' => $event['owner'] ?: 0,
//		'EVENT_AUTHOR_ID' => $event['author_id'] ?: 0,
//        'START_DATE' => $startDate->getusingFormat('d/m/Y'),
//        'START_HOUR' => $event['allday'] ? '' : $startDate->getUsingFormat('h'),
//        'START_MIN' => $event['allday'] ? '' :$startDate->getUsingFormat('i'),
//        'START_AM' => $startDate->getUsingFormat('A') == 'AM' ? 'selected="selected"' : '',
//        'START_PM' => $startDate->getUsingFormat('A') == 'PM' ? 'selected="selected"' : '',
//        'TIME_OFF' => $event['allday'] ? 'hide' : '',
//        'END_DATE' => $endDate->getusingFormat('d/m/Y'),
//        'END_HOUR' => $event['allday'] ? '' :$endDate->getUsingFormat('h'),
//        'END_MIN' => $event['allday'] ? '' :$endDate->getUsingFormat('i'),
//        'END_AM' => $endDate->getUsingFormat('A') == 'AM' ? 'selected="selected"' : '',
//        'END_PM' => $endDate->getUsingFormat('A') == 'PM' ? 'selected="selected"' : '',
//        'LEVEL' => '',
//        'GROUP' => '',
//        'ALLDAY_CHECKED' => ($event['allday'] ? 'checked="checked"' : ''),
//        'DESCRIPTION' => Scrub::text($event['note']),
//        'LOCATION' => Scrub::text($event['location']),
//        'ATTENDANCE' => $event['attendance'] ? 'checked="checked"' : '',
//        'DETAIL' => Scrub::text($event['detail']),
//        'RETURN_TO' => parseReturnTo($_GET['returnTo'], $startDate->getUsingFormat('d-m-Y'), $event['sid']?$event['sid']:$_GET['sid'], $event['owner']),
//        'PUBLISH_DATE' => $publishDate,
//    );
//
//    $page->setVar($output);
//
//    $page->dropDown(
//        'type_drop', $calendarHandler->getTypeList(), $event['type'], TEMPLATE_content,
//        array('eventTypeID' => 'TYPE_NUMBER', 'eventType' => 'TYPE_NAME')
//    );
//
//    $campusList = $calendarHandler->getCampusList();
//
//    if (count($campusList) > 0) {
//        $page->checkList('campus_list', $campusList, $calendarHandler->getEventCampus($event['id']));
//    }
//    else {
//        $page->setBlock(TEMPLATE_content, 'campus_list', 'campus_list_block');
//        $page->setVar('campus_list_block', '');
//    }
//
//    $groupList = $groupHandler->involvedInCategoryList($_SESSION['id'], true, WRITE_ACCESS);
//
//	/* Render a list of User Groups
//	 */
//	$page->setBlock(TEMPLATE_content, 'usergroup', 'usergroup_block');
//
//	foreach($groupList as $currentUserGroup) {
//		$page->setVar(array(
//			'USERGROUP_ID' => $currentUserGroup['id'],
//			'USERGROUP_NAME' => $currentUserGroup['name'],
//			'USERGROUP_SELECTED' => in_array($currentUserGroup['id'], array($event['sid'])) ? 'checked="checked"' : '',
//		));
//
//		$page->parse('usergroup_block', 'usergroup', true);
//	}
//
//    $page->checkList('group_list', $calendarHandler->getGroupList(), $calendarHandler->getEventGroup($event['id']));
//
//    $levelList[] = array('LEVEL_ID' => 1, 'LEVEL_NAME' => 'Personal');
//
//    if (count($groupList) > 0) {
//        $levelList[] = array('LEVEL_ID' => 2, 'LEVEL_NAME' => 'Group');
//    }
//
//    if (count($campusList) > 0) {
//        $levelList[] = array('LEVEL_ID' => 3, 'LEVEL_NAME' => 'School');
//    }
//
//    $page->dropDown('level_drop', $levelList, $event['access']);
//
//    $page->parse('delete_button_block', 'delete_button', true);
//
//    //
//    // Is there a recurrence? If so don't show the repeat selector.
//    if ($event['series_id'] == null) {
//        $page->setVar('recurring_reminder_block', '');
//        $page->parse('recurring_dates_block', 'recurring_dates');
//    }
//    else {
//        $recurrence = $calendarHandler->getSeriesRecurrence($event['series_id']);
//        $page->setVar('REC_FREQ', ucfirst($recurrence['freq']));
//        $page->setVar('REC_START', $recurrence['start']->getLongDateOnly());
//        $page->setVar('REC_END', $recurrence['end']->getLongDateOnly());
//        $page->parse('recurring_reminder_block', 'recurring_reminder');
//        $page->setVar('recurring_dates_block', '');
//    }
//
//    if ($event['series_id'] || ($event['publish_date'] != '')) {
//        // set the advanced options to be shown by default since there is information in there
//        $page->setVar(
//            array(
//                'SHOW_ADVANCED' => ' style="display: table-row;"',
//                'OPAC_ADVANCED' => ' style="opacity: 1;"',
//                'ADV_OFF' => ' style="display:none"',
//                'ADV_ON' => ''
//            )
//        );
//    }
//    else {
//        // no data in advanced options, hide them by default
//        $page->setVar(
//            array(
//                'SHOW_ADVANCED' => '',
//                'OPAC_ADVANCED' => '',
//                'ADV_OFF' => '',
//                'ADV_ON' => ' style="display:none"'
//            )
//        );
//    }
//
//    $page->outputPage();
//} // if creating a new event
//else {
//    $page->setFile(TEMPLATE_content, 'calendar/modify.html');
//    $page->setTab("calendar");
//    $page->setBlock(TEMPLATE_content, 'recurring_dates', 'recurring_dates_block');
//    $page->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');
//    $page->setBlock(TEMPLATE_content, 'recurring_reminder', 'recurring_reminder_block');
//	$page->setBlock(TEMPLATE_content, 'usergroup', 'usergroup_block');
//    $page->setTitle('Add Calendar Event');
//
//    $date = $_GET['date'];
//    $note = '';
//    $type = '';
//
//    // test whether or not this has come from an inline form on the calendar, or not
//    if(isset($_GET['from']) && $_GET['from'] == 'inline') {
//        $date = date('d-m-Y', $_GET['date'] / 1000);
//        $note = $_GET['note'];
//        $type = $_GET['type'];
//    }
//
//    list($selectedDay, $selectedMonth, $selectedYear) = explode("-", $date);
//
//    $selectedDate = @mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear);
//
//    if ($selectedDate <= 0) {
//        $selectedDate = time();
//    }
//
//    $startDate = new Alaress_Date_Format($selectedDate, 'U');
//    $endDate = new Alaress_Date_Format($selectedDate, 'U');
//
//    $output = array(
//        'FUNCTION' => 'Add',
//        'EVENT_ID' => '',
//        'EVENT_USER_ID' => $_GET['userId'] ?: $_SESSION['user']->getId(),
//        'EVENT_AUTHOR_ID' => $_SESSION['user']->getId(),
//        'START_DATE' => $startDate->getUsingFormat('d/m/Y'),
//        'START_HOUR' => '',
//        'START_MIN' => '',
//        'START_AM' => '',
//        'START_PM' => '',
//        'TIME_OFF' => 'hide',
//        'END_DATE' => $startDate->getUsingFormat('d/m/Y'),
//        'END_HOUR' => '',
//        'END_MIN' => '',
//        'END_AM' => '',
//        'END_PM' => '',
//        'ALLDAY_CHECKED' => 'checked="checked"',
//        'LEVEL' => '',
//        'GROUP' => '',
//        'DESCRIPTION' => $note,
//        'LOCATION' => '',
//        'ATTENDANCE' => '',
//        'DETAIL' => '',
//        'RETURN_TO' => parseReturnTo($_GET['returnTo'], $startDate->getUsingFormat('d-m-Y'), $_GET['sid'], $_GET['userId']),
//        'PUBLISH_DATE' => '',
//    );
//
//    $page->setVar($output);
//
//    $page->dropDown(
//        'type_drop', $calendarHandler->getTypeList(), $type, TEMPLATE_content,
//        array('eventTypeID' => 'TYPE_NUMBER', 'eventType' => 'TYPE_NAME')
//    );
//
//    $campusList = $calendarHandler->getCampusList();
//
//    if (count($campusList) > 0) {
//        $page->checkList('campus_list', $campusList, array($_SESSION['campus'][0]));
//    } else {
//        $page->setBlock(TEMPLATE_content, 'campus_list', 'campus_list_block');
//        $page->setVar('campus_list_block', '');
//    }
//
//    $page->checkList('group_list', $calendarHandler->getGroupList(), '');
//
//    $groupList = $groupHandler->involvedInCategoryList($_SESSION['id'], true, WRITE_ACCESS);
//
//    if ($_GET['level'] == 2) {
//        //
//        // Is current category not it group list? Put it there (as first)
//        if (!in_array($_GET['sid'], array_map(function($b) { return $b['id']; }, $groupList))) {
//            try {
//                $this_category = Schoolbox\Resource\CategoryMapper::getInstance()->findById($_GET['sid']);
//                array_unshift(
//                    $groupList,
//                    array(
//                        'id'   => $_GET['sid'],
//                        'name' => $this_category->getName(),
//                    )
//                );
//            }
//            catch (Schoolbox\Mapper\Exception $e) {}
//        }
//
//		/* Render a list of User Groups
//		 */
//		foreach($groupList as $currentUserGroup) {
//			$page->setVar(array(
//				'USERGROUP_ID' => $currentUserGroup['id'],
//				'USERGROUP_NAME' => $currentUserGroup['name'],
//				'USERGROUP_SELECTED' => in_array($_GET['sid'], array($event['sid'])) ? 'checked="checked"' : '',
//			));
//
//			$page->parse('usergroup_block', 'usergroup', true);
//		}
//    } else {
//		/* Render a list of User Groups
//		 */
//		foreach($groupList as $currentUserGroup) {
//			$page->setVar(array(
//				'USERGROUP_ID' => $currentUserGroup['id'],
//				'USERGROUP_NAME' => $currentUserGroup['name'],
//				'USERGROUP_SELECTED' => '',
//			));
//
//			$page->parse('usergroup_block', 'usergroup', true);
//		}
//    }
//
//    $levelList[] = array('LEVEL_ID' => 1, 'LEVEL_NAME' => 'Personal');
//
//    if (count($groupList) > 0) {
//        $levelList[] = array('LEVEL_ID' => 2, 'LEVEL_NAME' => 'Group');
//    }
//    if (count($campusList) > 0) {
//        $levelList[] = array('LEVEL_ID' => 3, 'LEVEL_NAME' => 'School');
//    }
//
//    $page->dropDown('level_drop', $levelList, $_GET['level']);
//
//    $page->setVar('delete_button_block', '');
//    $page->setVar('recurring_reminder_block', '');
//    $page->parse('recurring_dates_block', 'recurring_dates');
//
//    // set the advanced options to be hidden by default since there can't be any data in there
//    $page->setVar(
//        array(
//            'SHOW_ADVANCED' => '',
//            'OPAC_ADVANCED' => '',
//            'ADV_OFF' => '',
//            'ADV_ON' => ' style="display:none"'
//        )
//    );
//
//    $page->outputPage();
//}
//
//
///**
// * Ensure that the user hasn't modified the URL in an untoward manner.
// * If they have, or it's somehow absent, default to week view.
// *
// * @param string $returnView
// * @param string $returnDate
// * @param int    $groupId
// * @param int    $userId
// *
// * @return string
// * @internal param $returnTo
// */
//function parseReturnTo($returnView, $returnDate = "", $groupId = null, $userId = null) {
//    if ($returnView === 'dashboard') {
//        return '/';
//    }
//    elseif ($returnView === 'upcomingEventAttendance') {
//        return '/calendar/attendance/upcomingEvents/';
//    }
//    elseif ($returnView === 'homepage') {
//        return '/homepage/'.$groupId;
//    }
//    elseif ($returnView === 'calendar') {
//        $return = "/calendar/full/$returnDate";
//        if ($userId) {
//            $return .= '/'.$userId;
//        }
//        return $return;
//    }
//    // Otherwise - guess using Back to the future!
//    return '';
//}
