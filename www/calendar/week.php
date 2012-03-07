<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2002-2003 Lektec Consulting         *
 *                                                           *
 ************************************************************/

require_once '../library/calendar.php';
require_once '../library/submissionStudent.php';
require_once '../library/group.php';

$calendarHandler = new Calendar;
$groupHandler = new Group;
$subStuHandler = new SubmissionStudent;

$this->setFile(TEMPLATE_content, 'calendar/week.html');
$this->setTab("calendar");
$this->setTitle('Calendar Week');

$this->setBlock(TEMPLATE_content, "day_header", "day_header_block");
$this->setBlock(TEMPLATE_content, "day_block", "day_listing");
$this->setBlock("day_block", "note_block", "note_listing");
$this->setBlock("note_block", "time", "time_block");

$this->setBlock("note_block", "attendance_icon", "attendance_icon_block");
$this->setBlock("note_block", "attendance_status", "attendance_status_block");
$this->setBlock("note_block", "attendance_request_button", "attendance_request_button_block");
$this->setBlock("note_block", "attendance_list_button", "attendance_list_button_block");

list($selectedDay, $selectedMonth, $selectedYear) = explode("-", $this->arg[0]);

$selectedDate = @mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear);
if ($selectedDate <= 0)
    $selectedDate = time();

$selectedDay = date("j", $selectedDate);
$selectedMonth = date("n", $selectedDate);
$selectedYear = date("Y", $selectedDate);

$this->setVar('SELECTED_DAY', $selectedDay);
$this->setVar('SELECTED_MONTH', $selectedMonth);
$this->setVar('SELECTED_YEAR', $selectedYear);


// setup movement buttons
$catList = $groupHandler->involvedInCategoryList($_SESSION['id']);
$this->checkList('group', $catList, $catList, TEMPLATE_content, array('name' => 'GROUP_NAME', 'id' => 'GROUP_ID'));
$this->checkList('campus', $calendarHandler->getCampusList(), $_SESSION['campus']);
$this->checkList('event_type', $calendarHandler->getTypeList(), '', TEMPLATE_content, array('eventType' => 'EVENT_TYPE_NAME', 'eventTypeID' => 'EVENT_TYPE_ID', 'eventColour' => 'EVENT_TYPE_COLOUR'));
$this->setVar('BACK', date("j-n-Y", strtotime("-1 week", $selectedDate)));
$this->setVar('FORWARD', date("j-n-Y", strtotime("+1 week", $selectedDate)));


// render rest of page
$dayOfWeek = date("w", mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear));
$startOfWeek = mktime(0, 0, 0, $selectedMonth, ($selectedDay - $dayOfWeek), $selectedYear);
$endOfWeek = strtotime("+6 days", $startOfWeek);

if (date("m", $startOfWeek) != date("m", $endOfWeek)) {
    $this->setVar('MONTH', date('jS F', $startOfWeek) . ' - ' . date("jS F", $endOfWeek));
}
else {
    $this->setVar('MONTH', date("jS", $startOfWeek) . ' - ' . date('jS F', $endOfWeek));
}


$output = array('YEAR' => date("Y", $startOfWeek),
    'DAY' => $selectedDay,
);
$this->setVar($output);

// week list
for ($i = 0; $i < 7; $i++) {

    $currentDay = strtotime("+$i day", $startOfWeek);

    $this->setVar('DATE', date("l jS", $currentDay));
    $this->setVar('ADD', '/calendar/modify.php?date=' . date("d-m-Y", $currentDay) . '&returnTo=week');
    $this->parse('day_header_block', 'day_header', true);
}

for ($i = 0; $i < 7; $i++) {

    // output submission events
    $currentDay = strtotime("+$i day", $startOfWeek);

    //output selected classes
    if (date("d-m-Y", $selectedDate) == date("d-m-Y", $currentDay))
        $this->setVar("MONTABLE_CSS", "calTableSelected");
    else if (date("d-m-Y", $currentDay) == date("d-m-Y"))
        $this->setVar("MONTABLE_CSS", "calTableCurrent");
    else
        $this->setVar("MONTABLE_CSS", "calTable");

    $submissionList = $calendarHandler->getSubmissionEvents($currentDay);
    if (is_array($submissionList)) {
        foreach ($submissionList as $submission) {

            /* Clear attendance blocks */
            $this->setVar(array(
                'attendance_icon_block' => '',
                'attendance_status_block' => '',
                'attendance_request_button_block' => '',
                'attendance_list_button_block' => '',
            ));

            /* Get status to set action */
            if ($groupHandler->isStudent($_SESSION['id'])) {

                $current_time = new DateTime();

                $student_submission = $subStuHandler->getStatus($_SESSION['id'], $submission['id']);

                if (!$submission['accept_submission']) {
                    $action = 'View Details';
                } elseif ($student_submission['status'] == 1 && $current_time > $student_submission['open_date'] && $current_time < $student_submission['late_date']) {
                    $action = 'Submit Work';
                } else {
                    $action = 'View Details';
                }

                $this->setVar('MODIFY', '<a class="btn go-to float-left" href="/submission/submissionHistory/' . $submission['id'] . '"><span></span>' . $action . '</a>');

            } elseif ($groupHandler->isStaff($_SESSION['id'])) {
                $this->setVar('MODIFY', '<a class="btn go-to float-left" href="/submission/viewSubmission/' . $submission['id'] . '"><span></span>View Submissions</a>');
            } else {
                $this->setVar('MODIFY', '');
            }

            $userHandler = new User();

            $output = array(
                'TIME' => $submission['date']->format('g:iA'), // g:i a
                'NOTE' => 'WORK DUE ' . $submission['category'] . (!empty($submission['synergyCode']) ? ' (' . $submission['synergyCode'] . ')' : '') . ' - ' . $submission['name'],
                'COLOUR' => $calendarHandler->displayColour(4),
                'LOCATION' => '',
                'DETAIL' => '',
                'EVENT_TYPE' => 'submission',
                'TYPE' => 'Due Work',
                'AUTHOR' => $userHandler->formatName($submission),
                'AUTHOR_ID' => $submission['owner'],
                'EVENT_LEVEL' => '',
            );

            $this->setVar($output);
            $this->parse('time_block', 'time');
            $this->parse("note_listing", "note_block", true);
            $subout = 1;
        }
    }


    if (Cfg::get('site_location') == "mgs" && Cfg::get('external_enable') && $groupHandler->isStaff($_SESSION['id'])) {
        $birthdayList = $calendarHandler->getStudentBirthdays($currentDay);
        if (is_array($birthdayList) && in_array(3, $_SESSION['campus'])) {
            foreach ($birthdayList as $birthday) {

                $output = array('TIME' => '',
                    'NOTE' => 'Student Birthday - ' . $birthday['name'] . ' in ' . $birthday['form'] . ' turns ' . $birthday['age'],
                    'COLOUR' => $calendarHandler->displayColour(1),
                    'MODIFY' => "",
                    'LOCATION' => '',
                    'DETAIL' => '',
                    'EVENT_TYPE' => 'bday',
                    'TYPE' => 'Birthday',
                    'AUTHOR' => '',
                    'AUTHOR_ID' => '',
                    'EVENT_LEVEL' => '',
                );
                $this->setVar($output);
                $this->setVar('time_block', '');
                $this->parse("note_listing", "note_block", true);

                $subout = 1;
            }
        }
    }

    $eventList = $calendarHandler->getDayEvents($currentDay);

    if (is_array($eventList)) {
        foreach ($eventList as $event) {

            $userHandler = new User();

            $output = array(
                'EVENT_ID' => $event['id'],
                'TIME' => rtrim(
                    ($event['startTime'] != "12:00AM" ? $event['startTime'] : "") . " - " .
                        ($event['endTime'] != "12:00AM" ? $event['endTime'] : ""),
                    "- "),
                'NOTE' => Scrub::html($event['note']),
                'COLOUR' => $calendarHandler->displayColour($event['type']),
                'MODIFY' => $calendarHandler->getPerm($event['id']) > 1 ? '<a class="btn modify float-left" href="/calendar/modify.php?eventID=' . $event['id'] . '&returnTo=month"><span></span>Modify</a>' : '',
                'LOCATION' => $event['location'],
                'DETAIL' => $event['detail'],
                'EVENT_TYPE' => 'type' . $event['type'],
                'TYPE' => $event['eventType'],
                'AUTHOR' => $userHandler->formatName($event),
                'AUTHOR_ID' => $event['owner'],
            );

            if ($event['access'] == 3) {
                if (stristr($event['campusList'], ',')) {
                    foreach (explode(',', $event['campusList']) as $campus) {
                        $output['EVENT_LEVEL'] .= ' campus' . $campus;
                    }
                }
                else
                    $output['EVENT_LEVEL'] .= ' campus' . $event['campusList'];

            }
            else if ($event['access'] == 2)
                $output['EVENT_LEVEL'] = 'group' . $event['sid'];
            else
                $output['EVENT_LEVEL'] = 'personal';

            $this->setVar($output);
            if ($output['TIME'])
                $this->parse('time_block', 'time');
            else
                $this->setVar('time_block', '');

            /* Attendance */
            $this->setVar(array(
                'attendance_icon_block' => '',
                'attendance_status_block' => '',
                'attendance_request_button_block' => '',
                'attendance_list_button_block' => '',
            ));

            if ($event['attendance']) {
                $this->parse('attendance_icon_block', 'attendance_icon');

                if ($groupHandler->isStudent($_SESSION['id'])) {
                    if ($attendance = $calendarHandler->getAttendanceByEventAndUser($event['id'], $_SESSION['id'])) {
                        /* Attendance Status */
                        if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_PENDING) {
                            $this->setVar('ATTENDANCE_STATUS', 'Pending');
                        }
                        elseif ($attendance['status'] == Calendar::ATTENDANCE_STATUS_APPROVED) {
                            $this->setVar('ATTENDANCE_STATUS', 'Approved');
                        }
                        elseif ($attendance['status'] == Calendar::ATTENDANCE_STATUS_REJECTED) {
                            $this->setVar('ATTENDANCE_STATUS', 'Rejected');
                        }

                        $this->parse('attendance_status_block', 'attendance_status');

                        $this->setVar('ATTENDANCE_REQUEST_ACTION', 'Unattend');
                    }
                    else {
                        $this->setVar('ATTENDANCE_REQUEST_ACTION', 'Attend');
                    }

                    $this->parse('attendance_request_button_block', 'attendance_request_button');
                }
                elseif ($groupHandler->isStaff($_SESSION['id'])) {
                    $this->parse('attendance_list_button_block', 'attendance_list_button');
                }
            }

            $this->parse("note_listing", "note_block", true);
        }

    } else
        if (!$subout) $this->setVar("note_listing", "");

    $this->parse("day_listing", "day_block", true);
    $this->setVar("note_listing", "");
}

?>
