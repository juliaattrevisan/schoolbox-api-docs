<?php

require_once SITE_root . '/library/synergetic-user.php';
require_once SITE_root . '/library/submission.php';
require_once SITE_root . '/library/submissionReturn.php';
require_once SITE_root . '/library/group.php';
require_once SITE_root . '/library/user.php';
require_once SITE_root . '/library/synergetic-class.php';

$this->setFile(TEMPLATE_content, 'submission/dueWork.html');
$this->setTab('submission');
$this->setBlock(TEMPLATE_content, 'week', 'week_block');
$this->setBlock('week', 'day', 'day_block');
$this->setBlock('day', 'event', 'event_block');

$this->setBlock(TEMPLATE_content, 'has_due', 'has_due_block');
$this->setBlock('has_due', 'due', 'due_block');
$this->setBlock('due', 'download', 'download_block');

$this->setBlock(TEMPLATE_content, 'has_return', 'has_return_block');
$this->setBlock('has_return', 'return', 'return_block');
$this->setBlock('return', 'download_rt', 'download_rt_block');

$this->setBlock(TEMPLATE_content, 'summary', 'summary_block');
$this->setBlock('summary', 'summary_due', 'summary_due_block');

$this->setBlock(TEMPLATE_content, 'activity', 'activity_block');

$this->setBlock(TEMPLATE_content, 'is_parent', 'is_parent_block');

$this->setVar('has_due_block', '');
$this->setVar('has_return_block', '');
$this->setVar('summary_block', '');
$this->setVar('activity_block', '');
$this->setVar('is_parent_block', '');



$groupHandler = new Group;

$userHandler = new User;

$returnHandler = new SubmissionReturn;

$submissionHandler = new Submission;




/* ideally session children would use the id as the array key as this is ridiculous */
$childList = array();
if (is_array($_SESSION['children'])) {
	foreach ($_SESSION['children'] as $child) {
		$childList[] = $child['id'];
	}
}

// get selected user
if(is_array($_SESSION['children']))
    $userID = $_SESSION['children'][0]['id'];
else {
    //displaying users on due work, so log this as the last time they viewed due work
    $submissionHandler->logPageView(1);
    $submissionHandler->logPageView(2);
    $userID = $_SESSION['id'];
}

if (is_numeric($this->arg[1]) && in_array($this->arg[1], $childList))
	$userID = $this->arg[1];
elseif (is_numeric($this->arg[1]) && $groupHandler->isStaff())
	$userID = $this->arg[1];

$user = $userHandler->getByID($userID);

if (!isset($user['uid']))
	error(2);


if (is_array($_SESSION['children'])) {

	$this->dropDown('child', $_SESSION['children'], $user['uid'], 'is_parent', array('id' => 'CHILD_ID', 'name' => 'CHILD_NAME'));

	$this->parse('is_parent_block', 'is_parent');
}



// get category list
$subjectList = false;
if (SYNERGY_enable && $user['synergy_id'])  {
	$synUser = new SynergeticUser;
	$subjectList = $synUser->getSubjectList($user['synergy_id'], $user['uid']);
}

$categoryList = $groupHandler->involvedCategoryList($user['uid'], $subjectList, 1);

if (is_array($categoryList)) {
	foreach($categoryList as $category) {
		$categoryIDList[] = $category['id'];
	}
}

//Catch if not involved in any categories
if (count($categoryList) == 0)
	error(135);

// get selected date
if (isset($this->arg[0]))
	$selectedDate = strtotime($this->arg[0]);

if ($selectedDate <= 0)
	$selectedDate = time();


// output general data
$output = array('CURRENT_DATE'	=> outputDate('F Y', $selectedDate),
				'NOT_TODAY'		=> date('dmY', $selectedDate) != date('dmY') ? 'not-today' : '',
				'TODAY_DATE'	=> date('Y-m-d'),
				'NEXT_MONTH'	=> date('Y-m-d', strtotime('+1 month', $selectedDate)),
				'PREV_MONTH'	=> date('Y-m-d', strtotime('-1 month', $selectedDate)),
				'SELECTED_DATE'	=> outputDate('Y-m-d', $selectedDate),
                'USER_ID'       => $this->arg[1],
				);
$this->setVar($output);




$selectedMonth 	= date("n", $selectedDate);
$selectedYear 	= date("Y", $selectedDate);

// calculate month layout
$firstDay 		= date("w", mktime(0,0,0,$selectedMonth, 1, $selectedYear));
$numDaysinMonth = date("d", mktime(0, 0, 0, $selectedMonth + 1, 0, $selectedYear));
$numWeeksMonth 	= ceil(($firstDay + $numDaysinMonth)/7);
$totalDays 		= $numWeeksMonth * 7 + (6 - $firstDay);

$startDay = mktime(12, 0, 0, $selectedMonth, 1, $selectedYear) - strtotime("+$firstDay day", 0);



// output calendar
$summary = array();
for ($i = 0; $i < $totalDays; $i++) {

	$currentDay = strtotime("+$i day", $startDay);

	$day = $i % 7;

	if($i >= $firstDay && $i < $numDaysinMonth+$firstDay)
		$this->setVar('OTHER_MONTH', '');


	$output = array('DATE'		=> date("j", $currentDay),
					'PAST'		=> $i >= $firstDay && $i < $numDaysinMonth+$firstDay ? '' : 'past',
					'ACTIVE'	=> date('dmY', $currentDay) == date('dmY', $selectedDate) ? 'active' : '',
					'TODAY'		=> date('dmY') == date('dmY', $currentDay) ? 'today' : '',
					'RIGHTY'	=> $day > 3 ? 'righty' : '',
					);

	$this->setVar($output);

	$eventList = $submissionHandler->getByDate($currentDay, $user['uid'], $categoryIDList);

	if (is_array($eventList)) {
		foreach ($eventList as $event) {

			$due = ( !empty($event['realname']) && $event['late_date'] > time() );

			$output = array('EVENT_ID'		=> $event['id'],
							'TIME'			=> outputDate('h:ia', strtotime($event['date'])),
							'EVENT'			=> scrubText($event['description']),
							'EVENT_PAST' 	=> !$due ? 'fade' : '',
							'EVENT_CAT_ID'	=> $event['category'],
							);

			$this->setVar($output);
			$this->parse('event_block', 'event', true);

			/* record overview for summary box */
			$summary[$event['category']] = array('name' 	=> $event['categoryName'],
												 'total' 	=> ++$summary[$event['category']]['total'],
												 'due'		=> $due ? ++$summary[$event['category']]['due'] : $summary[$event['category']]['due'],
												 'code'		=> explode(',', $event['categoryCode']),
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

$j = 0;
// upcoming due work
$workList = $submissionHandler->getUpcoming($user['uid'], $categoryIDList);

if (is_array($workList)) {
	foreach ($workList as $work) {
		/* display date as time if today */
		$left = $work['date'] - time();
		$date = $left < 86400 && $left > 0 ? '<strong>' . outputDate('g:i a', $work['date']) . '</strong>' : outputDate(DATE_short_no_time, $work['date']);

		$output = array('DUE_SUBJECT'	=> scrubText($work['categoryName']),
						'DUE_DESC'		=> scrubText($work['description']),
						'DUE_DATE'		=> $date,
						'FILE_ID'		=> $work['file_id'],
						'BOX_ID'		=> $work['id'],
						'DARK'			=> $j++ % 2 ? '' : 'dark',
						);

		$this->setVar($output);

		/* display file download link */
		$this->setVar('download_block', '');
		if ($work['file_id'] > 0)
			$this->parse('download_block', 'download');

		$this->parse('due_block', 'due', true);
	}

	$this->parse('has_due_block', 'has_due');
}

$j = 0;
// returned work
$returnList = $returnHandler->getByUser($user['uid']);
if (is_array($returnList)) {
	foreach ($returnList as $return) {

		$output = array('RETURN_SUBJECT'	=> scrubText($return['categoryName']),
						'RETURN_DESC'		=> scrubText($return['description']),
						'RETURN_MARK'		=> scrubText($return['mark']),
						'RETURN_COMMENT'	=> scrubText($return['comment']),
						'RETURN_FILE_ID'	=> $return['id'],
						'RETURN_BOX_ID'		=> $return['box_id'],
						'DARK'				=> $j++ % 2 ? '' : 'dark',
						);

		$this->setVar($output);

		/* display file download link */
		$this->setVar('download_rt_block', '');
        $file = RETURNS_directory . '/' . $return['id']. '.dat';
		if (is_readable($file))
			$this->parse('download_rt_block', 'download_rt');


		$this->parse('return_block', 'return', true);
	}

	$this->parse('has_return_block', 'has_return');
}

$j = 0;

/* Completed percentages for each subject
 */
$percentage = array();
$categoryIDs = array();

// subject summary
foreach ($categoryList as $category) {
	$categoryID = $category['id'];
	/* yes it actually requires THIS MUCH code to get a teachers name. */
	if (SYNERGY_enable) {

		$synClass = new SynergeticClass;

		$teacherOutput = array();
		$teacherList = array();
        $codeList = explode(',',$category['code']);
		if (is_array($codeList)) {
			foreach ($codeList as $code) {
				if(is_array($temp = $synClass->getTeacherName($code)))
                    $teacherList = array_merge($teacherList, $temp);
			}
		}

		if (is_array($teacherList)) {
			foreach ($teacherList as $teacherSynID) {
				$teacher = $userHandler->getBySynergy($teacherSynID);
				$teacherName = formatName($teacher);
				$emailAddress = formatEmail($teacher);
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

	$output = array('SUMMARY_CAT_ID'	=> $categoryID,
					'SUMMARY_CAT_NAME'	=> scrubText($category['name']),
					'SUMMARY_TOTAL'		=> $summary[$category['id']]['total'],
					'SUMMARY_DUE'		=> $summary[$category['id']]['due'],
					'SUMMARY_TEACHER'	=> SYNERGY_enable ? implode(', ', $teacherOutput) : '',
					'DARK'				=> $j++ % 2 ? 'dark' : '',
                    'PERCENTAGE_COMPLETED' => $thisPercentage,
					);

	$this->setVar($output);

	$this->setVar('summary_due_block', '');
	if ($category['due'] > 0)
		$this->parse('summary_due_block', 'summary_due');

	$this->parse('summary_block', 'summary', true);
}

/* Give jquery ProgressBar values to use
 */
$this->setVar(array(
    'PERCENTAGE_COMPLETED_ARRAY' => implode(',', $percentage),
    'CATEGORY_IDS' => implode(',', $categoryIDs)
));

$j = 0;

// activities feed
$activityLog = $submissionHandler->getActivityLog($user['uid'], 15, $categoryIDList);
if (is_array($activityLog)) {
	foreach ($activityLog as $activity) {

		$taskName = $activity['description'];
		if ($activity['fid'])
				$taskName = '<a href="/send.php?id=' . $activity['fid'] . '&save=1">' . $activity['description'] . '</a>';

		$categoryName = '<a href="/homepage/' . $activity['category'] . '">' . scrubText($activity['categoryName']) . '</a>';

		if ($activity['new'] == 'new') {
			$stringDesc = 'New ' . $activity['typeName'] . ' ' . $taskName . ' added for ' . $categoryName;
		}
		elseif ($activity['new'] == 'overdue') {
			$stringDesc = '<strong>' . $taskName . ' for ' . $categoryName . ' is overdue</strong> ';
		}
		else if($activity['new'] == 'submitted') {
			$stringDesc = 'Work submitted to '.$activity['description'] .' for <a href="/homepage/' . $activity['category'] . '">' . scrubText($activity['categoryName']) . '</a>';
		}
		else if($activity['new'] == 'returned') {
			if($activity['typeName'])
				$stringDesc = 'You received '.$activity['typeName'];
			else if($activity['fid'])
				$stringDesc = '<a href="/submission/send.php?id='.$activity['fid'].'&return=1">Work returned</a>';
			else
				$stringDesc = 'Work returned';

			$stringDesc .= ' for '.$activity['description']. ' in <a href="/homepage/' . $activity['category'] . '">' . scrubText($activity['categoryName']) . '</a>';
		}
		else {
			$stringDesc = $categoryName . ' submissions for ' . scrubText($activity['description']) . ' are now closed ';
		}

		$output = array('ACTIVITY_DESCRIPTION'	=> $stringDesc,
						'ACTIVITY_DATE'			=> getRelativeTime($activity['activity_date'], 0 , ''),
						'DARK'					=> $j++ % 2 ? 'dark' : '',
						);
		$this->setVar($output);
		$this->parse('activity_block', 'activity', true);
	}
}

// subject drop down
$this->dropDown('subject', $categoryList, '', TEMPLATE_content, array('id' => 'SUBJECT_ID', 'name' => 'SUBJECT_NAME'));


/* Online Assessments
 */
require_once(SITE_root . '/library/assess/assess.php');
require_once(SITE_root . '/library/assess/assess-attempt.php');
require_once(SITE_root . '/library/assess/assess-response.php');
require_once(SITE_root . '/library/assess/assess-section.php');

$this->setBlock(TEMPLATE_content, 'allAssess', 'allAssess_block');
$this->setBlock('allAssess', 'attempt', 'attempt_block');
$this->setBlock('attempt', 'viewMarks', 'viewMarks_block');
$this->setBlock('attempt', 'takeTest', 'takeTest_block');
$this->setBlock('attempt', 'unmarked', 'unmarked_block');

$assessHandler = new Assess();
$attemptHandler = new AssessAttempt();
$responseHandler = new AssessResponse();
$sectionHandler = new AssessSection();

/* Store a lookup so you don't need to make new queries all day
 */
foreach($categoryList as $key => $value) {
    $categoryLookup[$value['id']] = $value['name'];
}

/* Make a list of assessments
 */
$allAssessments = $assessHandler->getList($categoryIDList);

if ($allAssessments) {
    foreach($allAssessments as $currentAssessment) {
        $currentAttempt = $attemptHandler->getLatestForUser($currentAssessment['assess_id'], $_SESSION['id']);

        $firstSection = $sectionHandler->getFirst($currentAssessment['assess_id']);

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

            /* Count how many responses haven't been automarked
             */
            $numNotAutomarked = $responseHandler->numNotAutomarked($currentAttempt['attempt_id']);

            if ($numNotAutomarked == 0) {
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

        $this->parse('attempt_block', 'attempt', true);
    }

    $this->parse('allAssess_block', 'allAssess');
}
else {
    $this->setVar('allAssess_block', '');
}
?>