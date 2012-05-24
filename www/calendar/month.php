<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2002-2003 Lektec Consulting         *
 *                                                           *
 ************************************************************/

//require_once "include/permission.php";
require_once "../library/group.php";
require_once '../library/submissionStudent.php';
require_once "../library/calendar.php";

$calendarHandler = new Calendar;
$groupHandler = new Group;
$subStuHandler = new SubmissionStudent;

$this->setFile(TEMPLATE_content, 'calendar/month.html');
$this->setTab("calendar");
$this->setTitle('Calendar Month');

$this->setBlock(TEMPLATE_content, "month_week", "month_week_listing");
$this->setBlock("month_week", "day_block", "day_listing");
$this->setBlock("day_block", "note_block", "note_listing");
$this->setBlock("note_block", "time", "time_block");

$this->setBlock("note_block", "attendance_icon", "attendance_icon_block");
$this->setBlock("note_block", "attendance_status", "attendance_status_block");
$this->setBlock("note_block", "attendance_request_button", "attendance_request_button_block");
$this->setBlock("note_block", "attendance_list_button", "attendance_list_button_block");
$this->setBlock('note_block', 'has_category', 'has_category_block');

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

if(isset($_SESSION['calendarFilter'])) {
    $selected['group'] = $_SESSION['calendarFilter']['groups'];
    $selected['campus'] = $_SESSION['calendarFilter']['campus'];
    $selected['event_type'] = $_SESSION['calendarFilter']['events'];
}
else {
    if(count($catList)) {
        foreach($catList as $cat) {
            $selected['group'][] = $cat['id'];
        }
    }

    $selected['campus'] = $_SESSION['campus'];

    $typeList = $calendarHandler->getTypeList();
    if(count($typeList)) {
        foreach($typeList as $type) {
            $selected['event_type'][] = $type['eventTypeID'];
        }
    }

}

$this->checkList('group', $catList, $selected['group'], TEMPLATE_content, array('name' => 'GROUP_NAME', 'id' => 'GROUP_ID'));
$this->checkList('campus', $calendarHandler->getFilterCampusList($_SESSION['id']), $selected['campus']);
$this->checkList('event_type', $calendarHandler->getTypeList(), $selected['event_type'], TEMPLATE_content, array('eventType' => 'EVENT_TYPE_NAME', 'eventTypeID' => 'EVENT_TYPE_ID', 'eventColour' => 'EVENT_TYPE_COLOUR'));

$this->setVar('BACK', date("j-n-Y", strtotime("-1 month", $selectedDate)));
$this->setVar('FORWARD', date("j-n-Y", strtotime("+1 month", $selectedDate)));

// output calendar
$firstDay = date("w", mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));
//$numDaysinMonth = date("t", $selectedMonth);
$numDaysinMonth = date("d", mktime(0, 0, 0, $selectedMonth + 1, 0, $date)); //
$numWeeksMonth = ceil(($firstDay + $numDaysinMonth) / 7);
$totalDays = $numWeeksMonth * 7 + (6 - $firstDay);

// render rest of page
$dayOfWeek = date("w", mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear));
$startOfWeek = mktime(0, 0, 0, $selectedMonth, ($selectedDay - $dayOfWeek), $selectedYear);
$endOfWeek = strtotime("+6 days", $startOfWeek);

$this->setVar('MONTH', date("F", mktime(0, 0, 0, $selectedMonth, 1, $selectedYear)));

$output = array('YEAR' => date("Y", $startOfWeek),
    'DAY' => $selectedDay,
);
$this->setVar($output);

// get top left starting day of calendar view

$startDay = mktime(3, 0, 0, $selectedMonth, 1, $selectedYear) - strtotime("+$firstDay day", 0);

for ($i = 0; $i < $totalDays; $i++) {
    $currentDay = strtotime("+$i day", $startDay);

    $day = $i % 7;

	if ($i >= $firstDay && $i < $numDaysinMonth + $firstDay) {
		$this->setVar('OTHER_MONTH', '');
	}
	else {
		$this->setVar('OTHER_MONTH', ' otherMonth');
	}

	$output = array(
		'DATE' => date("jS", $currentDay),
		'ADD' => '/calendar/modify.php?date=' . date("d-m-Y", $currentDay) . '&returnTo=month',
		'ON_OFF' => !($day % 2) ? 'dark' : '',
	);

    if (date("d-m-Y", $selectedDate) == date("d-m-Y", $currentDay)) {
        $this->setVar("MONTABLE_CSS", "calTableSelected");
	}
    else if (date("d-m-Y", $currentDay) == date("d-m-Y")) {
        $this->setVar("MONTABLE_CSS", "calTableCurrent");
	}
    else {
        $this->setVar("MONTABLE_CSS", "calTable");
	}

    $this->setVar($output);

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
                $action = 'View Details';
                $this->setVar('MODIFY', '<a class="btn go-to float-left" href="/submission/submissionHistory/' . $submission['id'] . '"><span></span>' . $action . '</a>');

            } elseif ($groupHandler->isStaff($_SESSION['id'])) {
                $this->setVar('MODIFY', '<a class="btn go-to float-left" href="/submission/viewSubmission/' . $submission['id'] . '"><span></span>View Submissions</a>');
            } else {
                $this->setVar('MODIFY', '');
            }

            $userHandler = new User();

            $output = array(
                'TIME' => $submission['date']->format('g:iA') != '11:59PM'? $submission['date']->format('g:iA') : '', // g:i a
                'NOTE' => $submission['category'] . (!empty($submission['synergyCode']) ? ' (' . $submission['synergyCode'] . ')' : '') . '<br />' . $submission['name'],
                'COLOUR' => $calendarHandler->displayColour(4),
                'LOCATION' => '',
                'DETAIL' => '',
                'EVENT_TYPE' => 'submission',
                'TYPE' => 'Due Work',
                'AUTHOR' => $userHandler->formatName($submission),
                'AUTHOR_ID' => $submission['owner'],
                'EVENT_LEVEL' => '',
                'has_category_block' => '',
            );

            $this->setVar($output);
            if($submission['date']->format('g:iA') != '11:59PM')
                $this->parse('time_block', 'time');
            else
                $this->setVar('time_block','');
            $this->parse("note_listing", "note_block", true);

            $subout = 1;
        }
    }

    if (Cfg::get('site_location') == 'mgs' && Cfg::get('external_enable') && $groupHandler->isStaff($_SESSION['id'])) {
        $birthdayList = $calendarHandler->getStudentBirthdays($currentDay);

        if (is_array($birthdayList) && in_array(3, $_SESSION['campus'])) {
            foreach ($birthdayList as $birthday) {
                $output = array('TIME' => '',
                    'NOTE' => 'Student Birthday - ' . $birthday['name'] . ' in ' . $birthday['form'] . ' turns ' . $birthday['age'],
                    'LOCATION' => '',
                    'DETAIL' => '',
                    'COLOUR' => $calendarHandler->displayColour(3),
                    'MODIFY' => '',
                    'TYPE' => 'Birthday',
                    'AUTHOR' => '',
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
        $userHandler = new User();

        foreach ($eventList as $event) {
			$this->setVar(array(
				'attendance_icon_block' => '',
				'attendance_status_block' => '',
				'attendance_request_button_block' => '',
				'attendance_list_button_block' => '',
			));

			/* If the Publish Date hasn't been reached yet, only show the events to Superusers
			 * in a different way so they can still edit/click them. Otherwie, they don't appear
			 * at all for everyone else
			 */
			$publishDate = new Alaress_Date_Format($event['publish_date'], 'y-m-d h:i:s');

			/* Publish date in the future
			 */
			if ($publishDate->getUnixTimestamp() >= time()) {
				if ($groupHandler->isSuperuser()) {
					$cssClass = 'translucent';
				}
				else {
					continue;
				}
			}
			else {
				$cssClass = '';
			}

            $output = array(
                'EVENT_ID' => $event['id'],
                'TIME' => rtrim(
                    ($event['startTime'] != "12:00AM" ? $event['startTime'] : "") . " - " .
                        ($event['endTime'] != "12:00AM" ? $event['endTime'] : ""),
                    "- "),
                'NOTE' => Scrub::text($event['note']),
                'LOCATION' => Scrub::text($event['location']),
                'DETAIL' => Scrub::text($event['detail']),
                'COLOUR' => '#' . $event['eventColour'],
                'MODIFY' => $calendarHandler->getPerm($event['id']) > 1 ? '<a class="btn modify float-left" href="/calendar/modify.php?eventID=' . $event['id'] . '&returnTo=month"><span></span>Modify</a>' : '',
                'EVENT_TYPE' => 'type' . $event['type'],
                'TYPE' => $event['eventType'],
                'AUTHOR' => $userHandler->formatName($event),
                'AUTHOR_ID' => $event['owner'],
				'EVENT_VISIBLE' => $cssClass,
            );

            if ($event['access'] == 3) {
                if (stristr($event['campusList'], ',')) {
                    foreach (explode(',', $event['campusList']) as $campus) {
                        $output['EVENT_LEVEL'] .= ' campus' . $campus;
                    }
                }
                else {
                    $output['EVENT_LEVEL'] .= ' campus' . $event['campusList'];
				}
            }
            else if ($event['access'] == 2) {
                $output['EVENT_LEVEL'] = 'group' . $event['sid'];
			}
            else {
                $output['EVENT_LEVEL'] = 'personal';
			}

            $this->setVar($output);

			if ($output['TIME']) {
				$this->parse('time_block', 'time');
			}
			else {
				$this->setVar('time_block', '');
			}

			/* Attendance */

            if ($event['attendance']) {
                $this->parse('attendance_icon_block', 'attendance_icon');

                if ($groupHandler->isStudent($_SESSION['id'])) {
                    if ($attendance = $calendarHandler->getAttendanceByEventAndUser($event['id'], $_SESSION['id'])) {
                        /* Attendance Status */
                        if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_PENDING) {
                            $this->setVar('ATTENDANCE_STATUS', 'Pending');
                        }
                        else if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_APPROVED) {
                            $this->setVar('ATTENDANCE_STATUS', 'Approved');
                        }
                        else if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_REJECTED) {
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
                else if ($groupHandler->isStaff($_SESSION['id'])) {
                    $this->parse('attendance_list_button_block', 'attendance_list_button');
                }
            }

			/* Show a homepage if appropriate
			 */
			if (is_numeric($event['categoryId'])) {
				$this->setVar(array(
					'HAS_CATEGORY_ID' => $event['categoryId'],
					'HAS_CATEGORY_NAME' => $event['categoryName'],
				));

				$this->parse('has_category_block', 'has_category');
			}
			else {
				$this->setVar('has_category_block', '');
			}

            $this->parse("note_listing", "note_block", true);
        }
    }
	else if (!$subout) {
		$this->setVar("note_listing", "");
	}

    $this->parse('day_listing', 'day_block', true);
    $this->setVar('note_listing', '');

    if ($day == 6 && $i) {
        $this->parse('month_week_listing', 'month_week', true);
        $this->setVar('day_listing', '');
    }
}
