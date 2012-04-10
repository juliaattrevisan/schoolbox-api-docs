<?php

require_once SITE_root . '/library/submission.php';
require_once SITE_root . '/library/submissionReturn.php';
require_once SITE_root . '/library/group.php';
require_once SITE_root . '/library/user.php';

/**
 * Order by date descending.
 *
 * @param $a
 * @param $b
 *
 * @return int
 */
function cmp($a, $b) {
	if ($a['relevant_date'] == $b['relevant_date']) {
		return 0;
	}
	else if ($a['relevant_date'] < $b['relevant_date']) {
		return 1;
	}

	return -1;
}

$this->setFile(TEMPLATE_content, 'submission/dueWork.html');
$this->setTab('submission');
$this->setTitle('Due Work');

$this->setBlock(TEMPLATE_content, 'week', 'week_block');
$this->setBlock('week', 'day', 'day_block');
$this->setBlock('day', 'event', 'event_block');

$this->setBlock(TEMPLATE_content, 'summary', 'summary_block');
$this->setBlock('summary', 'summary_due', 'summary_due_block');

$this->setBlock(TEMPLATE_content, 'activity', 'activity_block');
$this->setBlock(TEMPLATE_content, 'is_parent', 'is_parent_block');

$this->setVar('summary_block', '');
$this->setVar('activity_block', '');
$this->setVar('is_parent_block', '');

$groupHandler = new Group();
$userHandler = new User();
$returnHandler = new SubmissionReturn();
$submissionHandler = new Submission();

/* Instantiate for current date time
 */
$current_time = new DateTime();

/* ideally session children would use the id as the array key as this is ridiculous
 */
$childList = array();

if (is_array($_SESSION['children'])) {
	foreach ($_SESSION['children'] as $child) {
		$childList[] = $child['id'];
	}
}

/* From calendar format url e.g. /submission/dueWork/10/04/2012/17
 */
if (count($this->arg) > 3) {
	$userID = $this->arg[3];
}

/* get selected user from session (?)
 */
else if (is_array($_SESSION['children'])) {
    $userID = $_SESSION['children'][0]['id'];
}
else {
    //displaying users on due work, so log this as the last time they viewed due work
    $submissionHandler->logPageView(1);
    $submissionHandler->logPageView(2);
    $userID = $_SESSION['id'];
}

if ((count($this->arg) < 3) && is_numeric($this->arg[1]) && in_array($this->arg[1], $childList)) {
	$userID = $this->arg[1];
}
else if ((count($this->arg) < 3) && is_numeric($this->arg[1]) && $groupHandler->isStaff()) {
	$userID = $this->arg[1];
}

$user = $userHandler->getByID($userID);

if (!isset($user['uid'])) {
	error(2);
}

if (is_array($_SESSION['children'])) {
	$this->dropDown('child', $_SESSION['children'], $user['uid'], 'is_parent', array('id' => 'CHILD_ID', 'name' => 'CHILD_NAME'));
	$this->parse('is_parent_block', 'is_parent');
}

$categoryList = $groupHandler->involvedInCategoryList($user['uid']);

if (is_array($categoryList)) {
	foreach($categoryList as $category) {
		$categoryIDList[] = $category['id'];
	}
}

/* Catch if not involved in any categories
 */
if (count($categoryList) == 0) {
	error(135);
}

/* get selected date
 */
if (isset($this->arg[0])) {
	$selectedDate = strtotime($this->arg[0]);
}

if ($selectedDate <= 0) {
	$selectedDate = time();
}

$selectedDateObj = new Alaress_Date_Format($selectedDate, 'U');

/* output general data
 */
$output = array(
	'CURRENT_DATE' => date('F Y', $selectedDateObj->getUnixTimestamp()),
	'NOT_TODAY' => date('dmY', $selectedDate) != date('dmY') ? 'not-today' : '',
	'TODAY_DATE' => date('Y-m-d'),
	'NEXT_MONTH' => date('Y-m-d', strtotime('+1 month', $selectedDate)),
	'PREV_MONTH' => date('Y-m-d', strtotime('-1 month', $selectedDate)),
	'SELECTED_DATE' => $selectedDateObj->getLocalShort(),
	'WORK_USER_ID' => $userID,
	'WORK_USER_NAME' => $userID == $_SESSION['id'] ? 'My' : $userHandler->formatName($user) . "'s",
);

$this->setVar($output);

$selectedMonth 	= date("n", $selectedDate);
$selectedYear 	= date("Y", $selectedDate);

/* calculate month layout
 */
$firstDay = date("w", mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));
$numDaysinMonth = date("d", mktime(0, 0, 0, $selectedMonth + 1, 0, $selectedYear));
$numWeeksMonth = ceil(($firstDay + $numDaysinMonth) / 7);
$totalDays = $numWeeksMonth * 7 + (6 - $firstDay);

$startDay = mktime(12, 0, 0, $selectedMonth, 1, $selectedYear) - strtotime("+$firstDay day", 0);

// output calendar
$summary = array();

for ($i = 0; $i < $totalDays; $i++) {
	$currentDay = strtotime("+$i day", $startDay);

	$day = $i % 7;

	if ($i >= $firstDay && $i < $numDaysinMonth + $firstDay) {
		$this->setVar('OTHER_MONTH', '');
	}

	$output = array(
		'DATE' => date("j", $currentDay),
		'PAST' => $i >= $firstDay && $i < $numDaysinMonth + $firstDay ? '' : 'past',
		'ACTIVE' => date('dmY', $currentDay) == date('dmY', $selectedDate) ? 'active' : '',
		'TODAY' => date('dmY') == date('dmY', $currentDay) ? 'today' : '',
		'RIGHTY' => $day > 3 ? 'righty' : '',
	);

	$this->setVar($output);

	$eventList = $submissionHandler->getByDate($currentDay, $user['uid'], $categoryIDList);

	if (is_array($eventList)) {
		foreach ($eventList as $event) {
			$due = ($event['realname'] && $event['open_date'] < time() && $event['late_date'] > time() && $event['accept_submission']);

			$date = new Alaress_Date_Format(strtotime($event['date']), 'U');

			$output = array(
				'EVENT_ID' => $event['id'],
				'TIME' => $date->getTimeOnly(),
				'EVENT' => Scrub::text($event['name']),
				'EVENT_PAST' => !$due ? 'fade' : '',
				'EVENT_CAT_ID' => $event['category'],
			);

			$this->setVar($output);
			$this->parse('event_block', 'event', true);

			/* record overview for summary box */
			$summary[$event['category']] = array(
				'name' => $event['categoryName'],
				'total' => ++$summary[$event['category']]['total'],
				'due' => $due ? ++$summary[$event['category']]['due'] : $summary[$event['category']]['due'],
				'code' => explode(',', $event['categoryCode']),
			);
		}
	}

	$this->parse('day_block', 'day', true);
	$this->setVar('event_block', '');

	if ($day == 6 && $i) {
		$this->parse('week_block', 'week', true);
		$this->setVar('day_block', '');
	}
}

/** UPCOMING WORK **/

/* Upcoming and Returned work segments are now combined. Returned from the
 * submissionHandler as key/value pair. Limits are in the context of "limits
 * per work type (either upcoming or returned)"
 */
$allUpcomingWork = $submissionHandler->getRecentWork($userID, $categoryIDList, UPCOMING_WORK_TYPE);
$allAwaitingWork = $submissionHandler->getRecentWork($userID, $categoryIDList, AWAITING_WORK_TYPE);
$allReturnedWork = $submissionHandler->getRecentWork($userID, $categoryIDList, RETURNED_WORK_TYPE);

/* Try to get the $limit. If it can't get even amounts of one type, try to fill
 * it out with remainders from the other type, balancing the numbers. It works
 * unidirectionally, so balancing occurs between the current type and the next,
 * rather than looking at the limits of all three and dividing up meaningfully.
 */
$max = 12/3;

$limit = $max;
$allWork = array();

foreach(array($allUpcomingWork, $allAwaitingWork, $allReturnedWork) as $current) {
	for ($i=0; (is_array($current) && $i < count($current)) && ($i < $limit); $i++) {
		$allWork[] = $current[$i];
	}

	/* Recalculate how much can be displayed of the next type, if the current type
	 * doesn't have enough items
	 */
	if ($i < ($limit-1)) {
		$limit = ($limit - $i) + $max;
	}
}

usort($allWork, 'cmp');

$this->setBlock(TEMPLATE_content, 'work', 'work_block');

/* Alternating row color index */
$j = 0;


/* Check if system config restrict_student_submissions is on
 *
 * This is used to hide marks in the Recent Work Activity/Online Assessments
 * parts of the page
 */
$restrictedSubmissions = false;

if ($groupHandler->isParentOfChild($this->arg[1])) {
	if (Cfg::get('restrict_student_submissions')) {
		$restrictedSubmissions = true;
	}
}

if (count($allWork) > 0) {
    foreach($allWork as $currentWork) {

		if (!$restrictedSubmissions) {
			if ($currentWork['status'] == UPCOMING_WORK_TYPE) {
				if (!$currentWork['accept_submission']) {
					$status = 'Work due';
					$action = 'View Details';
				}
				else if ($current_time > $currentWork['open_date']) {
					if ($current_time < $currentWork['date']) {
						$status = 'Work due';
						$action = 'Submit Work';
					}
					else if ($current_time < $currentWork['late_date']) {
						$status = 'Work overdue';
						$action = 'Submit Work';
					}
					else {
						$status = 'Missed due date';
						$action = 'View Details';
					}
				}
				else {
					$status = 'Open date approaching';
					$action = 'View Details';
				}
			}
			else if ($currentWork['status'] == AWAITING_WORK_TYPE) {
				$action = 'View Details';

				if ($current_time < $currentWork['date']) {
					$status = 'Work submitted';
				}
				else if ($currentWork['submitted'] < $currentWork['late_date']) {
					$status = 'Work submitted late';
				}
			}
			else if ($currentWork['status'] == RETURNED_WORK_TYPE) {
				$action = 'View Details';

				if (!empty($currentWork['mark'])) {
					$status = 'Work marked';
				}
				else {
					$status = 'Work returned';
				}
			}
		}
		/* Status to display when Restrict Student Submissions is on
 		 */
		else {
			$status = '';
			$action = '';
		}

        // if the relevant_date is set to the start of end of a day, only show the day itselfand truncate the time
        $shortFormat = Cfg::get('date_short');
        $timePos = strpos($shortFormat, 'g:i') ? strpos($shortFormat, 'g:i') - 1 : strlen($shortFormat);
        if( $currentWork['relevant_date']->format('H:i:s') == '00:00:00' ||
            $currentWork['relevant_date']->format('H:i:s') == '23:59:59' )
        {
            $this->setVar('WORK_DATE', $currentWork['relevant_date']->format(substr($shortFormat, 0, $timePos)));
        } else {
            $this->setVar('WORK_DATE', $currentWork['relevant_date']->format(Cfg::get('date_short')));
        }


        $this->setVar(array(
            'WORK_SUBJECT' => $currentWork['categoryName'],
            'WORK_DESCRIPTION' => $currentWork['name'],
            'WORK_ACTION' => $action,
            'WORK_STATUS' => $status,
            'WORK_BOX_ID' => $currentWork['bid'],
            'WORK_FILE_ID' => $currentWork['fid'],
            'ROW_COLOR' => !($j++ % 2) ? 'dark' : ''
        ));

        $this->parse('work_block', 'work', true);
    }
}
else {
    $this->setVar('work_block','');
}

/* Online Assessments
 */
$this->setBlock(TEMPLATE_content, 'allAssess', 'allAssess_block');
$this->setBlock('allAssess', 'attempt', 'attempt_block');

$this->setBlock('attempt', 'restricted_student_submissions_marks', 'restricted_student_submissions_marks_block');
$this->setBlock('restricted_student_submissions_marks', 'unmarked', 'unmarked_block');

$this->setBlock('attempt', 'restricted_student_submissions_actions', 'restricted_student_submissions_actions_block');
$this->setBlock('restricted_student_submissions_actions', 'viewMarks', 'viewMarks_block');
$this->setBlock('restricted_student_submissions_actions', 'takeTest', 'takeTest_block');

$assessHandler = new Assess_Main();
$attemptHandler = new Assess_Attempt();
$responseHandler = new Assess_Response();
$sectionHandler = new Assess_Section();

/* Store a lookup so you don't need to make new queries all day
 */
foreach($categoryList as $key => $value) {
    $categoryLookup[$value['id']] = $value['name'];
}

/* Make a list of assessments
 */
$allAssessments = $assessHandler->getList($categoryIDList, 10);
$j=0;

if ($allAssessments) {
	$attemptsExisted = false;

    foreach($allAssessments as $currentAssessment) {
		$allAttempts = $attemptHandler->getList($currentAssessment['assess_id'], $this->arg[2]);

		/* If multiple attempts were made, list them
		 */
		if (is_array($allAttempts) && count($allAttempts)) {
			$attemptsExisted = true;

			foreach($allAttempts as $currentAttempt) {
				$firstSection = $sectionHandler->getFirst($currentAssessment['assess_id']);

				/* No attempts yet
				 */
				if (!$currentAttempt) {
					$this->setVar(array(
						'ASSESS_CATEGORY' => $categoryLookup[$currentAssessment['category_id']],
						'ASSESS_ASSESS_ID' => $currentAssessment['assess_id'],
						'ASSESS_TITLE' => $currentAssessment['title'],
						'ASSESS_FINISH' => '',
						'ASSESS_FIRST_SECTION_ID' => $firstSection['section_id'],
						'ATTEMPT_ATTEMPT_ID' => $currentAttempt['attempt_id'],
						'ATTEMPT_TOTAL_MARKS' => number_format(0, 2),
						'ATTEMPT_TOTAL_AVAILABLE_MARKS' => number_format($totalAvailableMarks, 2),
						'ATTEMPT_PERCENTAGE' => 0,
					));

					$this->setVar('viewMarks_block', '');
					$this->parse('takeTest_block', 'takeTest');
					$this->setVar('unmarked_block', '');
				}
				else {

					/* Finish time
					 */
					if ($currentAttempt['finish'] != '0000-00-00 00:00:00') {
						$finish = date('jS F Y, g:i:sa', strtotime($currentAttempt['finish']));
						$timeTaken = $assessHandler->sec2hms($currentAttempt['timeTaken']);
					}
					else {
						$finish = '';
						$timeTaken = '';
					}

					/* Hide marks and actions
					 */
					if ($restrictedSubmissions) {
						$this->setVar('restricted_student_submissions_marks_block', '');
						$this->setVar('restricted_student_submissions_actions_block', '');
					}
					else {
						$this->parse('restricted_student_submissions_marks_block', 'restricted_student_submissions_marks');
						$this->parse('restricted_student_submissions_actions_block', 'restricted_student_submissions_actions');
					}

					/* The total available marks for this attempt
					 */
					$totalAvailableMarks = $attemptHandler->getTotalMarks($currentAssessment['assess_id'], $currentAttempt['attempt_id']);

					if ($totalAvailableMarks > 0) {
						$marks = $attemptHandler->getMarksForAttempt($currentAttempt['attempt_id']);

						$percentage = round(100 * $marks / $totalAvailableMarks);
					}
					else {
						$percentage = 0;
					}

					/* Count how many responses haven't been automarked; indicate
					 * whether the assess has been reviewed or not
					 */
					if ($currentAttempt['reviewed_at'] == null) {
						$numNotAutomarked = $responseHandler->numNotAutomarked($currentAttempt['attempt_id']);

						$this->setVar(array(
							'ATTEMPT_UNMARKED_RESPONSES' => $numNotAutomarked
						));

						$this->parse('unmarked_block', 'unmarked');
					}
					else {
						$this->setVar('unmarked_block', '');
					}

					$this->setVar(array(
						'ASSESS_CATEGORY' => $categoryLookup[$currentAssessment['category_id']],
						'ASSESS_ASSESS_ID' => $currentAssessment['assess_id'],
						'ASSESS_TITLE' => $currentAssessment['title'],
						'ASSESS_FINISH' => date('jS F Y, g:i:sa', strtotime($currentAttempt['finish'])),
						'ASSESS_FIRST_SECTION_ID' => $firstSection['section_id'],
						'ATTEMPT_ATTEMPT_ID' => $currentAttempt['attempt_id'],
						'ATTEMPT_TOTAL_MARKS' => number_format($marks, 2),
						'ATTEMPT_TOTAL_AVAILABLE_MARKS' => number_format($totalAvailableMarks, 2),
						'ATTEMPT_PERCENTAGE' => $percentage,
					));

					/* Hide link to take the unattempted assesment directly
					 */
					$this->setVar('takeTest_block', '');

					/* Find out if this test has been marked; if it has, allow the
					 * student to see the feedback, otherwise remove link altogether
					 */
					if ($currentAttempt['reviewed_at']) {
						$this->parse('viewMarks_block', 'viewMarks');
					}
					else {
						$this->setVar('viewMarks_block', '');
					}
				}

                $this->setVar('DARK',$j++ % 2 ?'' : 'dark');
				$this->parse('attempt_block', 'attempt', true);
			}
		}
    }

	/* Clear only if attempts never existed for any assess.
	 * Previously this would get incorrectly cleared for an
	 * assess that existed but had no attempts coming after
	 * an assess that existed and had attempts.
	 */
	if (!$attemptsExisted) {
		$this->setVar('attempt_block', '');
	}

	$this->parse('allAssess_block', 'allAssess', true);
}
else {
    $this->setVar('allAssess_block', '');
}

/* Completed percentages for each subject
 */
$percentage = array();
$categoryIDs = array();
$j = 0;
$synClass = new External_Class;

// subject summary
foreach ($categoryList as $category) {
	$categoryID = $category['id'];

	/* yes it actually requires THIS MUCH code to get a teachers name. */
	if (Cfg::get('external_enable')) {
		$teacherOutput = array();
		$teacherList = array();
        $codeList = explode(',',$category['code']);

		if (is_array($codeList)) {
			foreach ($codeList as $code) {
				if(is_array($temp = $synClass->getTeacherName($code))) {
                    $teacherList = array_merge($teacherList, $temp);
				}
			}
		}

		if (is_array($teacherList)) {
			foreach ($teacherList as $teacherSynID) {
				$teacher = $userHandler->getBySynergy($teacherSynID);
				$teacherName = $userHandler->formatName($teacher);

				$emailObj = new Email();
				$emailAddress = $emailObj->formatEmailFromArray($teacher);

				$teacherOutput[] = '<a href="'.$_SESSION['mailhandle']."$emailAddress\">$teacherName</a>";
			}
		}
	}

    /* Count the number of submission boxes for this Subject, and the percentage
     * of items submitted. If there are no submission boxes, just have 100%
     */
    $submissions = $submissionHandler->getSubmissionsForSubject($categoryID);
    $numBoxes = $submissionHandler->countSubmissionBoxes($categoryID);

    if ($numBoxes) {
        $thisPercentage = round(100*count($submissions)/$numBoxes);
    }
    else {
        $thisPercentage = 100;
    }

    /* Accumulate into array for Javascript
     */
    $percentage[] = $thisPercentage;
    $categoryIDs[] = $categoryID;

	$output = array(
		'SUMMARY_CAT_ID' => $categoryID,
		'SUMMARY_CAT_NAME' => Scrub::text($category['name']),
		'SUMMARY_TOTAL' => $summary[$category['id']]['total'],
		'SUMMARY_DUE' => $summary[$category['id']]['due'],
		'SUMMARY_TEACHER' => Cfg::get('external_enable') ? implode(', ', $teacherOutput) : '',
		'DARK' => $j++ % 2 ? 'dark' : '',
		'PERCENTAGE_COMPLETED' => $thisPercentage,
	);

	$this->setVar($output);

	$this->setVar('summary_due_block', '');

	if ($category['due'] > 0) {
		$this->parse('summary_due_block', 'summary_due');
	}

	$this->parse('summary_block', 'summary', true);
}

/* Give jquery ProgressBar values to use
 */
$this->setVar(array(
    'PERCENTAGE_COMPLETED_ARRAY' => implode(',', $percentage),
    'CATEGORY_IDS' => implode(',', $categoryIDs)
));

$j = 0;

/* activities feed
 */
$activityLog = $submissionHandler->getActivityLog($user['uid'], 15, $categoryIDList);

if (is_array($activityLog)) {
	foreach ($activityLog as $activity) {
        $taskName = $activity['name'];

		if ($activity['fid']) {
			$taskName = '<a href="/send.php?id=' . $activity['fid'] . '&save=1">' . $activity['name'] . '</a>';
		}

		$categoryName = '<a href="/homepage/' . $activity['category'] . '">' . Scrub::text($activity['categoryName']) . '</a>';

        switch($activity['new']) {

            case 'new':
                $stringDesc = "New {$activity['typeName']} $taskName added for $categoryName";
                break;

            case 'overdue':
                $stringDesc = "<strong>$taskName for $categoryName is overdue</strong> ";
                break;

            case 'submitted':
                $stringDesc = 'Work submitted to '.$activity['name'] .' for <a href="/homepage/' . $activity['category'] . '">' . Scrub::text($activity['categoryName']) . '</a>';
                break;

            case 'returned':
                if($activity['typeName']) {
                    // format for output
                    $rtn = $returnHandler->getByUserAndTime($user['uid'], $activity['activity_date']);
                    $marksheet = Marksheet_Factory::build($rtn['slug']);

                    // legacy support
                    if($rtn['slug'] == 'free') {
                        $mark = $rtn['mark'];
                    }
					else {
                        $mark = $marksheet->convertRawMark($rtn['norm_mark'], $rtn['max_mark'], true, false);
                    }

                    $stringDesc = 'You received ' . $mark;
                }
                else if($activity['fid']) {
                    $stringDesc = '<a href="/submission/send.php?id='.$activity['fid'].'&return=1">Work returned</a>';
				}
                else {
                    $stringDesc = 'Work returned';
				}

                $stringDesc .= ' for '.$activity['name']. ' in <a href="/homepage/' . $activity['category'] . '">' . Scrub::text($activity['categoryName']) . '</a>';
				break;

            default:
                $stringDesc = $categoryName . ' submissions for ' . Scrub::text($activity['name']) . ' are now closed ';
                break;
        }

		$activityTime = new Alaress_Date_Relative($activity['activity_date']);
		$activityTime->setPrefix('');

		$output = array(
			'ACTIVITY_DESCRIPTION' => $stringDesc,
			'ACTIVITY_DATE' => $activityTime->getRelativeTime(),
			'DARK' => $j++ % 2 ? 'dark' : '',
		);

		$this->setVar($output);
		$this->parse('activity_block', 'activity', true);
	}
}

/* subject drop down
 */
$this->dropDown('subject', $categoryList, '', TEMPLATE_content, array('id' => 'SUBJECT_ID', 'name' => 'SUBJECT_NAME'));
