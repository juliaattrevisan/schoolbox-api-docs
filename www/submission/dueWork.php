<?php

if (!isset($this)) {
    require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');
    SitePage::localRedir('/submission/dueWork');
}

$this->setFile(TEMPLATE_content, 'submission/dueWork.html');
$this->setTab('submission');
$this->setTitle('Due Work');

$this->setBlock(TEMPLATE_content, 'week', 'week_block');
$this->setBlock('week', 'day', 'day_block');
$this->setBlock('day', 'event', 'event_block');

//$this->setBlock(TEMPLATE_content, 'summary', 'summary_block');
//$this->setBlock('summary', 'summary_due', 'summary_due_block');

$this->setBlock(TEMPLATE_content, 'activity', 'activity_block');
$this->setBlock(TEMPLATE_content, 'is_parent', 'is_parent_block');

$this->setVar('summary_block', '');
$this->setVar('activity_block', '');
$this->setVar('is_parent_block', '');

$groupHandler = new Group();
$userHandler = new User();
$returnHandler = new SubmissionReturn();
$submissionHandler = new Submission();

//
// Default visibility: parent/student
// If student: allow student
// If staff or superuser: allow private
$visibilities = array(
	\Schoolbox\Assessment\Component\SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT,
);
if ($groupHandler->isStudent()) {
	$visibilities[] = \Schoolbox\Assessment\Component\SubmissionBox::MARK_VISIBILITY_STUDENT;
}
elseif ($groupHandler->isStaff() || $groupHandler->isSuperuser()) {
	$visibilities[] = \Schoolbox\Assessment\Component\SubmissionBox::MARK_VISIBILITY_PRIVATE;
	$visibilities[] = \Schoolbox\Assessment\Component\SubmissionBox::MARK_VISIBILITY_STUDENT;
}

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

/* possible uri formats
 - /y-m-d/uid - date and user
 - /y-m-d/    - date only
 - /uid       - user only
 - /          - default
*/

// parse the datums!
switch (count($this->arg)) {
    case 2:
        // set both the date and uid
        $userID = $this->arg[1];
        if ($this->arg[0] == 0) {
            $targetDate = new Alaress_Date_Format(mktime(), 'U'); // if no date use today
        } else {
            $targetDate = new Alaress_Date_Format(preg_replace('[^0-9\-]', '', $this->arg[0]), 'y-m-d');
        }

        break;
    case 1:
        // determine whether this is a uid or date
        if (is_numeric($this->arg[0])) {
            $userID = $this->arg[0];
        } else {
            $targetDate = new Alaress_Date_Format(preg_replace('[^0-9\-]', '', $this->arg[0]), 'y-m-d');
        }
        break;
    case 0:
        // everything set below
        break;
    default:
        error(2);
        return;
}

// dynamically generate userID
if (!isset($userID)) {
    // check for parenthood
    if (count($childList)) {
        $userID = $_SESSION['children'][0]['id'];
    } else {
        // not specified means looking at our own, so log the view
        $submissionHandler->logPageView(1);
        $submissionHandler->logPageView(2);
        $userID = $_SESSION['id'];
    }
}

// prevent students from seeing anything but their own work
if ($groupHandler->isStudent()) {
    if ($userID != $_SESSION['id']) {
        error(19);
    }
} elseif (!($groupHandler->isStaff() || $groupHandler->isParentOfChild($userID))) {
    error(19);
}


if (!isset($targetDate)) {
    $targetDate = new Alaress_Date_Format(time(), 'U');
}

$user = $userHandler->getByID($userID);

// check this is a valid user
if (!isset($user['uid'])) {
    error(19);
}

// supplement the user array with additional information
$user['self'] = $user['uid'] == $_SESSION['id'];
$user['person_label'] = $user['self'] ? 'You' : $user['firstname'];

// if parent, output the child selector
if (is_array($_SESSION['children'])) {
    $this->dropDown(
        'child', $_SESSION['children'], $user['uid'], 'is_parent', array('id' => 'CHILD_ID', 'name' => 'CHILD_NAME')
    );
    $this->parse('is_parent_block', 'is_parent');
}

// retrieve category listing
$categoryList = $groupHandler->involvedInCategoryList($user['uid']);

// parse IDs into array
if (is_array($categoryList)) {
    foreach ($categoryList as $category) {
        $categoryIDList[] = $category['id'];
    }
}

// Catch if not involved in any categories
if (!isset($categoryIDList)) {
    error(153);
    exit;
}

// output general data
$output = array(
    'CURRENT_DATE' => date('F Y', $targetDate->getUnixTimestamp()),
    'NOT_TODAY' => $targetDate->getUsingFormat('dmY') == date('dmY') ? '' : 'not-today',
    'TODAY_DATE' => date('Y-m-d'),
    'NEXT_MONTH' => date('Y-m-d', strtotime('+1 month', $targetDate->getUsingFormat('U'))),
    'PREV_MONTH' => date('Y-m-d', strtotime('-1 month', $targetDate->getUsingFormat('U'))),
    'SELECTED_DATE' => $targetDate->getLocalShort(),
    'WORK_USER_ID' => $userID,
    'WORK_USER_NAME' => $userID == $_SESSION['id'] ? 'My' : $userHandler->formatName($user) . "'s",
    'USER_TOKEN' => ($userID == $_SESSION['id'] ? $_SESSION['key'] : $user['key']),
);

$this->setVar($output);

$selectedMonth = $targetDate->getUsingFormat('n');
$selectedYear = $targetDate->getUsingFormat('Y');

/* calculate month layout
 */
$firstDay = (int)date("w", mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));
$numDaysinMonth = (int)date("d", mktime(0, 0, 0, $selectedMonth + 1, 0, $selectedYear));
$numWeeksMonth = ceil(($firstDay + $numDaysinMonth) / 7);
$totalDays = $numWeeksMonth * 7 + (6 - $firstDay);

$startDay = mktime(12, 0, 0, $selectedMonth, 1, $selectedYear) - strtotime("+$firstDay day", 0);

// output calendar
$summary = array();

$event_api  = new Calendar_Api_EventType_Submission_Schoolbox_Default(null);
$event_list = $event_api->getEvents($startDay, strtotime("+$totalDays day", $startDay));
$event_list_by_day = array();
foreach ($event_list as $event_object) {
    $date = $event_object->getStartTime()->getUsingFormat('dmY');
    $event_list_by_day[$date][] = $event_object;
}

for ($i = 0; $i < $totalDays; $i++) {
    $currentDay = strtotime("+$i day", $startDay);

    $day = $i % 7;

    if ($i >= $firstDay && $i < $numDaysinMonth + $firstDay) {
        $this->setVar('OTHER_MONTH', '');
    }

    $this_day = date('dmY', $currentDay);
    $output = array(
        'DATE' => date("j", $currentDay),
        'PAST' => $i >= $firstDay && $i < $numDaysinMonth + $firstDay ? '' : 'past',
        'ACTIVE' => $this_day == $targetDate->getUsingFormat('dmY') ? 'active' : '',
        'TODAY' => date('dmY') == $this_day ? 'today' : '',
        'RIGHTY' => $day > 3 ? 'righty' : '',
    );

    $this->setVar($output);

    $eventList = isset($event_list_by_day[$this_day]) ? $event_list_by_day[$this_day] : array();

    foreach ($eventList as $event) {
        $date = $event->getStartTime();

        $event_id = $event->getSubmissionBoxId();
        if ($event->getIsAssessment()) {
            $event_id = 'assess-'.$event_id;
        }
        $category_id = $event->getCategoryId();
        $output = array(
            'EVENT_ID' => $event_id,
            'TIME' => $event->getTime(),
            'EVENT' => Scrub::text($event->getSubmissionBoxName()),
            'EVENT_CAT_ID' => $category_id,
        );

        $this->setVar($output);
        $this->parse('event_block', 'event', true);

        /* record overview for summary box */
        $summary[$category_id] = array(
            'name' => $event->getCategoryName(),
            'total' => ++$summary[$category_id]['total'],
            'code' => $event->getCategoryCodes(),
        );
    }

    $this->parse('day_block', 'day', true);
    $this->setVar('event_block', '');

    if ($day == 6 && $i) {
        $this->parse('week_block', 'week', true);
        $this->setVar('day_block', '');
    }
}


// create a custom instance of the upcoming work controller, and set it up for this context
$chf = new Component_Homepage_Factory(new User, $this);
$cdm = Component_DefinitionMapper::getInstance();
$definition = $cdm->findByColumn('class', 'Component_Dashboard_UpcomingWorkController');
$instance = new Component_Instance();
$instance->definition = $definition[0];

$dueWorkController = $chf->buildComponent($instance);

// apply configs
$dueWorkController->setup(
    array(), array(
        'actionable' => array('label' => 'actionable', 'value' => true),
        'allow_parents' => array('label' => 'allow_parents', 'value' => true),
        'size' => array('label' => 'size', 'value' => 0),
        'user' => array('label' => 'user', 'value' => $userID),
    )
);

$dueWorkController->execute();
$dueWorkController->renderTemplate('UPCOMING_WORK');


// subject listings
$j = 0;
$synClass = new External_Class;

$return_mapper = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper();
$assess_mapper = new \Schoolbox\Assessment\Component\OnlineAssessment\AttemptMapper();
$restrict_submissions = Cfg::get('restrict_student_submissions') && $groupHandler->isParent();

foreach ($categoryList as $category) {
    $categoryID = $category['id'];

    /* yes it actually requires THIS MUCH code to get a teachers name. */
    if (Cfg::get('external_enable')) {
        $teacherOutput = array();
        $teacherList = array();
        $codeList = explode(',', $category['code']);

        if (is_array($codeList)) {
            foreach ($codeList as $code) {
                if (is_array($temp = $synClass->getTeacherName($code))) {
                    $teacherList = array_merge($teacherList, $temp);
                }
            }
        }

        if (is_array($teacherList)) {
            foreach ($teacherList as $teacherSynID) {
                $teacher = $userHandler->getBySynergy($teacherSynID);
                if (is_array($teacher)) {
                    $teacherName = $userHandler->formatName($teacher);

                    $emailObj = new Email();
                    $emailAddress = $emailObj->formatEmailFromArray($teacher);

                    $teacherOutput[] = array(
                        'id' => 0,
                        'name' => $teacherName,
                        'email' => $_SESSION['mailhandle'] . $emailAddress,
                        'synid' => $teacherSynID,
                    );
                }
            }
        }
    }

    $categoryIDs[] = $categoryID;
    $categoryCode = $category['code'] ? '(' . $category['code'] . ')' : '';

    //
    // retrieve subject grades.
    // Ensure that grades are properly ordered by date.
    if (!$restrict_submissions) {
        $studentGrades = array_merge(
            $return_mapper->getStudentMarks($category['id'], $userID, array('visibility' => $visibilities)),
            $assess_mapper->getStudentMarks($category['id'], $userID)
        );
        usort($studentGrades, function($a, $b) { return $a['date'] > $b['date'] ? -1 : 1; });
        $studentGrades = array_map(function($row) { return $row['mark']; }, $studentGrades);
    }
    else {
        $studentGrades = array();
    }

    $gradeAvg    = -1;
    $grade_count = count($studentGrades);
    $show_grades = Cfg::get('show_overall_grade');
    $trendDir = '';
    $trendAmt = '';
    if ($show_grades) {
        if ($grade_count > 0) {
            $gradeAvg = array_sum($studentGrades) / count($studentGrades);
        }

        // if more than 1 assessment, can calculate trending
        if ($grade_count > 1) {
            // get previous average
            array_pop($studentGrades);
            $prevAvg = array_sum($studentGrades) / count($studentGrades);
            if ($gradeAvg > $prevAvg) {
                $trendDir = '&#x25B2;';
            } elseif ($gradeAvg < $prevAvg) {
                $trendDir = '&#x25BC;';
            }
            if($prevAvg != 0) {
                $trendAmt = sprintf('%0.1f', ($gradeAvg - $prevAvg) / $prevAvg * 100) . '%';
            }
        }
    }

    // calculate grade averages and trending
    $this->setFile('subjectTpl', 'activity/subjectlist.html');
    $this->setBlock('subjectTpl', 'teacher', 'teacher_block');
    $this->setBlock('subjectTpl', 'icon', 'icon_block');

    $output = array(
        'SUBJ_CAT_ID' => $categoryID,
        'SUBJ_SUBJECT_NAME' => Scrub::Text($category['name']),
        'SUBJ_SUBJECT_ICON' => '/images/mime/60x60/man.png',
        'SUBJ_SUBJECT_CODE' => $categoryCode,
        'SUBJ_SUBJECT_URL' => '/homepage/' . $categoryID,
        'SUBJ_TOTAL' => $summary[$category['id']]['total'],
        'SUBJ_TREND_DIR' => $trendDir,
        'SUBJ_TREND_AMT' => $trendAmt,
        'SUBJ_HAS_GRADE' => $show_grades ? 'grade' : '',
        'DARK' => ++$j % 2 ? '' : 'dark'
    );
    $this->setVar($output);

    if ($gradeAvg == -1) {
        $output = array(
            'SUBJ_GRADE' => '',
            'SUBJ_GRADEIANT' => 'na'
        );
    } else {
        $output = array(
            'SUBJ_GRADE' => $returnHandler->percent2letter($gradeAvg),
            'SUBJ_GRADEIANT' => (int)($gradeAvg / 10),
        );
    }

    $this->setVar($output);


    if (Cfg::get('external_enable')) {
        $this->setVar('teacher_block', '');
        if (isset($teacherOutput) && count($teacherOutput)) {
            foreach ($teacherOutput as $teacher) {
                $tOutput = array(
                    'SUBJ_TEACHER_NAME' => $teacher['name'],
                    'SUBJ_TEACHER_URL' => $teacher['email'],
                    'SUBJ_TEACHER_ROLE' => 'Teacher',
                    'SUBJ_TEACHER_AVATAR' => "/portrait?userId={$teacher['synid']}&size=square32",
                    'SUBJ_TEACHER_AVATAR_TITLE' => $teacher['name']
                );
                $this->setVar($tOutput);
                $this->parse('teacher_block', 'teacher', true);
            }
        }
    } else {
        $this->setVar('teacher_block', '');
    }
    $this->parse('icon_block', 'icon');
    $this->parse('SUBJECT_LIST', 'subjectTpl', true);
}

$feedItems = array();

foreach (getActivities($user['uid'], 15, $categoryIDList, $visibilities, $restrict_submissions) as $activity) {
    $mark = null;
    $numericMark = null;

    $categoryName = '<a href="/homepage/' . $activity->getCategory()->getId() . '">' . Scrub::text(
        $activity->getCategory()->getName()
    ) . '</a>';

    //
    // box URL depends on whether this is an assessment or submission
    $boxUrl = $taskName;
    if ($activity->getType() == \Schoolbox\Assessment\Component\SubmissionBox\WorkType::TYPE_ONLINE_ASSESSMENT) {
        if ($activity->getEvent() == 'closed') {
            $boxUrl = $activity->getName();
        }
        else {
            $boxUrl = sprintf(
                '<a href="/assess/intro/%d">%s</a>',
                $activity->getId(),
                $activity->getName()
            );
        }
    }
    else {
        $boxUrl = sprintf(
            '<a href="/submission/submissionHistory/%d/%d">%s</a>',
            $activity->getId(),
            $userID,
            $activity->getName()
        );
    }

    switch ($activity->getEvent()) {
        case 'new':
            $stringDesc = sprintf(
                'New %s %s added in %s', strtolower($activity->getTypeName()), $boxUrl, $categoryName
            );
            break;

        case 'overdue':
            $stringDesc = sprintf(
                '<span class="overdue">"%s" in %s is now overdue</span>', $boxUrl, $categoryName
            );
            break;

        case 'submitted':
            $stringDesc = sprintf(
                '%s submitted work for %s in %s', $user['person_label'], $boxUrl, $categoryName
            );
            break;

        case 'returned':
            if (!$restrict_submissions) {
                //
                // Showing returned work:
                // Always show a link back to submission history
                // If there's a mark, then show that.
                // Otherwise, if there's a file attached, show that.
                $base_mark = $activity->getReturnedNormalizedMark();
                if (isset($base_mark)) {
                    // format for output
                    $rtn = $returnHandler->getByUserAndTime($user['uid'], $activity->getDate()->getDatabase());
                    $marksheet = Marksheet_Factory::build($rtn['slug']);

                    // legacy support
                    if ($rtn['slug'] == 'free') {
                        $numericMark = $rtn['mark'];
                    }
                    else {
                        $numericMark = $marksheet->convertRawMark($rtn['norm_mark'], $rtn['max_mark'], true, false);
                    }

                    $mark = sprintf(
                        '<a href="/submission/submissionHistory/%s/%s">%s</a>', $rtn['box_id'], $rtn['owner'], $numericMark
                    );

                    $beginning = $user['person_label'] . ' received ' . $mark;
                }
                else {
                    $file_id   = $activity->getReturnedFileId();
                    $file_name = $activity->getReturnedWork()->getFilename();
                    if ($file_id && $file_name) {
                        $beginning = '<a href="/submission/send.php?id=' . $file_id . '&return=1">Work returned</a>';
                    }
                    else {
                        $beginning = 'Work returned';
                    }
                }

                $stringDesc = sprintf('%s for %s in %s', $beginning, $boxUrl, $categoryName);

            }
            break;

        default:
            $stringDesc = sprintf('Submissions for %s in %s are now closed', $boxUrl, $categoryName);
            break;
    }

    $activityTime = new Alaress_Date_Relative($activity->getDate());
    $activityTime->setPrefix('');

    $mark = $returnHandler->shortenGrade($numericMark);

    $feedItems[] = array(
        'content' => $stringDesc,
        'time' => $activityTime->getRelativeTime(),
        'icon' => $activity->getEvent(),
        'grade' => isset($mark) ? $mark : null,
        'gradeiant' => isset($mark) ? (int)($rtn['norm_mark'] / 10) : '',
        'has_grade' => isset($mark) ? 'grade' : 'no-grade',
    );
}

$activityList = new Activity_ListArray($feedItems);
if (empty($feedItems)) {
	$this->setVar('ACTIVITY_LIST', '<li>You have no recent activity.</li>');
}
else {
	$activityList->setTarget($this, 'ACTIVITY_LIST');
	foreach ($activityList as $a) {
		// iterates across and pumps out the list
	}
}

// subject drop down
$this->dropDown('subject', $categoryList, '', TEMPLATE_content, array('id' => 'SUBJECT_ID', 'name' => 'SUBJECT_NAME'));


/**
 * Gets activities from new mapper classes.
 *
 * @param int   $user
 * @param int   $limit
 * @param array $categories
 * @param array $visibilities  Which submission box visibilities are viewable?
 * @param bool  $is_restricted Are student's marks restricted?
 *
 * @return \Schoolbox\Assessment\ActivityCollection
 */
function getActivities($user, $limit, $categories, $visibilities, $is_restricted) {
	$box_mapper    = new \Schoolbox\Assessment\Component\SubmissionBoxMapper();
	$assess_mapper = new \Schoolbox\Assessment\Component\OnlineAssessmentMapper();
	$submit_mapper = new \Schoolbox\Assessment\Component\SubmissionBox\SubmissionMapper();
	$return_mapper = new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper();
	$params        = array(
		'categories'   => $categories,
		'visibilities' => $visibilities,
	);
	$activities    = $box_mapper
		->getActivities($user, $limit, $categories)
		->merge($assess_mapper->getActivities($user, $limit, $categories))
		->merge($submit_mapper->getActivities($user, $limit, $categories))
	;
	if (!$is_restricted) {
		$activities->merge($return_mapper->getActivities($user, $limit, $categories, $visibilities));
	}
	$activities
		->sortBy('date', 'desc')
		->limit(0, 15)
	;
	return $activities;
}
