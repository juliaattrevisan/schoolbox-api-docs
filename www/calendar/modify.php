<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page = new SitePage();
$calendarHandler = new Calendar();
$groupHandler = new Group();

// if modifying an event
if (is_numeric($_GET['eventID'])) {
    $page->setFile(TEMPLATE_content, 'calendar/modify.html');
    $page->setTab("calendar");
    $page->setTitle('Update Calender Event');

    $page->setBlock(TEMPLATE_content, 'recurring_dates', 'recurring_dates_block');
    $page->setBlock(TEMPLATE_content, 'recurring_reminder', 'recurring_reminder_block');
    $page->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');

    $event = $calendarHandler->getByID($_GET['eventID']);

    if (!$event['id']) {
        error(2);
    }

    // check security perms
    if ($calendarHandler->getPerm($event['id']) < 2) {
        error(34);
    }

    if ($event['publish_date'] != null && !preg_match('/^0000-00-00/', $event['publish_date'])) {
        $pubDateObject = new Alaress_Date_Format($event['publish_date'], 'y-m-d h:i:s');
        $publishDate = $pubDateObject->getLocalShort();
    } else {
        $publishDate = "";
    }

    $startDate = new Alaress_Date_Format(
        new DateTime($event['startDate'].' '.$event['startTime']),
        'y-m-d h:i:s'
    );
    $endDate = new Alaress_Date_Format(
        new DateTime($event['endDate'].' '.$event['endTime']),
        'y-m-d h:i:s'
    );

    $output = array(
        'FUNCTION' => 'Update',
        'EVENT_ID' => $event['id'],
        'START_DATE' => $startDate->getusingFormat('d/m/y'),
        'START_HOUR' => $event['allday'] ? '' : $startDate->getUsingFormat('h'),
        'START_MIN' => $event['allday'] ? '' :$startDate->getUsingFormat('i'),
        'START_AM' => $startDate->getUsingFormat('A') == 'AM' ? 'selected="selected"' : '',
        'START_PM' => $startDate->getUsingFormat('A') == 'PM' ? 'selected="selected"' : '',
	    'TIME_OFF' => $event['allday'] ? 'hide' : '',
        'END_DATE' => $endDate->getusingFormat('d/m/y'),
        'END_HOUR' => $event['allday'] ? '' :$endDate->getUsingFormat('h'),
        'END_MIN' => $event['allday'] ? '' :$endDate->getUsingFormat('i'),
        'END_AM' => $endDate->getUsingFormat('A') == 'AM' ? 'selected="selected"' : '',
        'END_PM' => $endDate->getUsingFormat('A') == 'PM' ? 'selected="selected"' : '',
        'LEVEL' => '',
        'GROUP' => '',
        'ALLDAY_CHECKED' => ($event['allday'] ? 'checked="checked"' : ''),
        'DESCRIPTION' => $event['note'],
        'LOCATION' => $event['location'],
        'ATTENDANCE' => $event['attendance'] ? 'checked="checked"' : '',
        'DETAIL' => $event['detail'],
        'RETURN_TO' => parseReturnTo($_GET['returnTo'], $_GET['date']),
        'PUBLISH_DATE' => $publishDate,
    );

    $page->setVar($output);

    $page->dropDown(
        'type_drop', $calendarHandler->getTypeList(), $event['type'], TEMPLATE_content,
        array('eventTypeID' => 'TYPE_NUMBER', 'eventType' => 'TYPE_NAME')
    );

    $campusList = $calendarHandler->getCampusList();

    if (count($campusList) > 1) {
        $page->checkList('campus_list', $campusList, $calendarHandler->getEventCampus($event['id']));
    }
	else {
        $page->setBlock(TEMPLATE_content, 'campus_list', 'campus_list_block');
        $page->setVar('campus_list_block', $campusList[0]['CAMPUS_NAME']);
    }

    $groupList = $groupHandler->involvedInCategoryList($_SESSION['id'], true, WRITE_ACCESS);

    $page->dropDown(
        'group_drop', $groupList, $event['sid'], TEMPLATE_content, array('id' => 'UGROUP_ID', 'name' => 'UGROUP_NAME')
    );

    $page->checkList('group_list', $calendarHandler->getGroupList(), $calendarHandler->getEventGroup($event['id']));

    $levelList[] = array('LEVEL_ID' => 1, 'LEVEL_NAME' => 'Personal');

    if (count($groupList) > 0) {
        $levelList[] = array('LEVEL_ID' => 2, 'LEVEL_NAME' => 'Group');
    }

    if (count($campusList) > 0) {
        $levelList[] = array('LEVEL_ID' => 3, 'LEVEL_NAME' => 'Campus');
    }

    $page->dropDown('level_drop', $levelList, $event['access']);

    $page->parse('delete_button_block', 'delete_button', true);

    //
    // Is there a recurrence? If so don't show the repeat selector.
    if ($event['series_id'] == null) {
        $page->setVar('recurring_reminder_block', '');
        $page->parse('recurring_dates_block', 'recurring_dates');
    }
    else {
        $recurrence = $calendarHandler->getSeriesRecurrence($event['series_id']);
        $page->setVar('REC_FREQ', ucfirst($recurrence['freq']));
        $page->setVar('REC_START', $recurrence['start']->getLongDateOnly());
        $page->setVar('REC_END', $recurrence['end']->getLongDateOnly());
        $page->parse('recurring_reminder_block', 'recurring_reminder');
        $page->setVar('recurring_dates_block', '');
    }

    if ($event['series_id'] || $event['publish_date']) {
        // set the advanced options to be shown by default since there is information in there
        $page->setVar(
            array(
                'SHOW_ADVANCED' => ' style="display: table-row;"',
                'OPAC_ADVANCED' => ' style="opacity: 1;"',
                'ADV_OFF' => ' style="display:none"',
                'ADV_ON' => ''
            )
        );
    }
	else {
        // no data in advanced options, hide them by default
        $page->setVar(
            array(
                'SHOW_ADVANCED' => '',
                'OPAC_ADVANCED' => '',
                'ADV_OFF' => '',
                'ADV_ON' => ' style="display:none"'
            )
        );
    }

    $page->outputPage();
} // if creating a new event
else {
    $page->setFile(TEMPLATE_content, 'calendar/modify.html');
    $page->setTab("calendar");
    $page->setBlock(TEMPLATE_content, 'recurring_dates', 'recurring_dates_block');
    $page->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');
    $page->setBlock(TEMPLATE_content, 'recurring_reminder', 'recurring_reminder_block');
    $page->setTitle('Add Calendar Event');

    $date = $_GET['date'];
    $note = '';
    $type = '';

    // test whether or not this has come from an inline form on the calendar, or not
    if(isset($_GET['from']) && $_GET['from'] == 'inline') {
        $date = date('d-m-Y', $_GET['date'] / 1000);
        $note = $_GET['note'];
        $type = $_GET['type'];
    }

    list($selectedDay, $selectedMonth, $selectedYear) = explode("-", $date);

    $selectedDate = @mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear);

    if ($selectedDate <= 0) {
        $selectedDate = time();
    }

	$startDate = new Alaress_Date_Format($selectedDate, 'U');
	$endDate = new Alaress_Date_Format($selectedDate, 'U');

    $output = array(
        'FUNCTION' => 'Add',
        'EVENT_ID' => '',
        'START_DATE' => $startDate->getUsingFormat('d/m/y'),
        'START_HOUR' => '',
        'START_MIN' => '',
        'START_AM' => '',
        'START_PM' => '',
	    'TIME_OFF' => 'hide',
		'END_DATE' => $startDate->getUsingFormat('d/m/y'),
        'END_HOUR' => '',
        'END_MIN' => '',
        'END_AM' => '',
        'END_PM' => '',
	    'ALLDAY_CHECKED' => 'checked="checked"',
        'LEVEL' => '',
        'GROUP' => '',
        'DESCRIPTION' => $note,
        'LOCATION' => '',
        'ATTENDANCE' => '',
        'DETAIL' => '',
        'RETURN_TO' => parseReturnTo($_GET['returnTo'], $_GET['date']),
        'PUBLISH_DATE' => '',
    );

    $page->setVar($output);

    $page->dropDown(
        'type_drop', $calendarHandler->getTypeList(), $type, TEMPLATE_content,
        array('eventTypeID' => 'TYPE_NUMBER', 'eventType' => 'TYPE_NAME')
    );

    $campusList = $calendarHandler->getCampusList();

    if (count($campusList) > 1) {
        $page->checkList('campus_list', $campusList, array($_SESSION['campus'][0]));
    } else {
        $page->setBlock(TEMPLATE_content, 'campus_list', 'campus_list_block');
        $page->setVar('campus_list_block', $campusList[0]['CAMPUS_NAME']);
    }

    $page->checkList('group_list', $calendarHandler->getGroupList(), '');

    $groupList = $groupHandler->involvedInCategoryList($_SESSION['id'], true, WRITE_ACCESS);

    if ($_GET['level'] == 2) {
        $page->dropDown(
            'group_drop', $groupList, $_GET['sid'], TEMPLATE_content,
            array('id' => 'UGROUP_ID', 'name' => 'UGROUP_NAME')
        );
    } else {
        $page->dropDown(
            'group_drop', $groupList, '', TEMPLATE_content, array('id' => 'UGROUP_ID', 'name' => 'UGROUP_NAME')
        );
    }

    $levelList[] = array('LEVEL_ID' => 1, 'LEVEL_NAME' => 'Personal');

    if (count($groupList) > 0) {
        $levelList[] = array('LEVEL_ID' => 2, 'LEVEL_NAME' => 'Group');
    }
    if (count($campusList) > 0) {
        $levelList[] = array('LEVEL_ID' => 3, 'LEVEL_NAME' => 'Campus');
    }

    $page->dropDown('level_drop', $levelList, $_GET['level']);

    $page->setVar('delete_button_block', '');
    $page->setVar('recurring_reminder_block', '');
    $page->parse('recurring_dates_block', 'recurring_dates');

    // set the advanced options to be hidden by default since there can't be any data in there
    $page->setVar(
        array(
            'SHOW_ADVANCED' => '',
            'OPAC_ADVANCED' => '',
            'ADV_OFF' => '',
            'ADV_ON' => ' style="display:none"'
        )
    );

    $page->outputPage();
}


/**
 * Ensure that the user hasn't modified the URL in an untoward manner.
 * If they have, or it's somehow absent, default to week view.
 *
 * @param $returnView
 * @param string $returnDate
 *
 * @return string
 * @internal param $returnTo
 */
function parseReturnTo($returnView, $returnDate = "") {

    /* Allow someone to return to the Upcoming Event Attendance screen -_-
     */
    if ($returnView == 'upcomingEventAttendance') {
        return '/calendar/upcomingEventAttendance';
    } else {
        //the returnTo must be week or month, default to week
        if (!in_array($returnView, array("week", "month"))) {
            $returnView = "week";
        }

        // Shamelessly pinched from above, to test whether the date we've been provided is valid.
        // A blank date will return the user to the current date.
        list($selectedDay, $selectedMonth, $selectedYear) = explode("-", $_GET['date']);

        $selectedDate = @mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear);

        if ($selectedDate <= 0) {
            $returnDate = "";
        }

        return "$returnView/$returnDate";
    }
}
