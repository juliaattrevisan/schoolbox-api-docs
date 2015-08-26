<?php

use Schoolbox\Assessment\Activity;
use Schoolbox\Assessment\ActivityCollection;
use Schoolbox\Assessment\Component\OnlineAssessment;
use Schoolbox\Assessment\Component\OnlineAssessmentMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;

if (!isset($this)) {
    require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');
    SitePage::localRedir('/submission/dueWork');
}

$this->setFile(TEMPLATE_content, 'submission/dueWork.html');
$this->setTab('submission');
$this->setTitle('Due Work');

//$this->setBlock(TEMPLATE_content, 'week', 'week_block');
//$this->setBlock('week', 'day', 'day_block');
//$this->setBlock('day', 'event', 'event_block');

$this->setBlock(TEMPLATE_content, 'activity', 'activity_block');
$this->setBlock(TEMPLATE_content, 'is_parent', 'is_parent_block');

$this->setVar('activity_block', '');
$this->setVar('is_parent_block', '');

$groupHandler      = new Group();
$userHandler       = new User();
$returnHandler     = new SubmissionReturn();
$submissionHandler = new Submission();

//
// Default visibility: parent/student
// If student: allow student
// If staff or superuser: allow private
$visibilities = array(
    SubmissionBox::MARK_VISIBILITY_PARENT_STUDENT,
);
$isStaff = false;
if ($groupHandler->isStudent()) {
    $visibilities[] = SubmissionBox::MARK_VISIBILITY_STUDENT;
} elseif ($groupHandler->isStaff() || $groupHandler->isSuperuser()) {
    $isStaff        = true;
    $visibilities[] = SubmissionBox::MARK_VISIBILITY_PRIVATE;
    $visibilities[] = SubmissionBox::MARK_VISIBILITY_STUDENT;
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
 - /Y-m-d/uid - date and user
 - /Y-m-d/    - date only
 - /uid       - user only
 - /          - default
*/

// parse the datums!
switch (count($this->arg)) {
    case 2:
        // set both the date and uid
        $userID = $this->arg[1];
        if ($this->arg[0] == 0) {
            $targetDate = new Alaress_Date_Format(time(), 'U'); // if no date use today
        } else {
            $targetDate = new Alaress_Date_Format(preg_replace('[^0-9\-]', '', $this->arg[0]), 'Y-m-d');
        }

        break;
    case 1:
        // determine whether this is a uid or date
        if (is_numeric($this->arg[0])) {
            $userID = $this->arg[0];
        } else {
            $targetDate = new Alaress_Date_Format(preg_replace('[^0-9\-]', '', $this->arg[0]), 'Y-m-d');
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
$restrictSubmissions = false;
if ($groupHandler->isStudent()) {
    if ($userID != $_SESSION['id']) {
        error(19);
    }
} elseif (!$groupHandler->isStaff() && $groupHandler->isParent()) {
    $restrictSubmissions = Config::get('restrict_student_submissions');
    if ($groupHandler->isParentOfChild($userID)
        && Config::get('restrict_parent_duework_access')
    ) {
        error(19);
    }
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
$user['self']         = $user['uid'] == $_SESSION['id'];
$user['person_label'] = $user['self'] ? 'You' : $user['firstname'];

// if parent, output the child selector
if (is_array($_SESSION['children'])) {
    $this->dropDown(
        'child',
        $_SESSION['children'],
        $user['uid'],
        'is_parent',
        array('id' => 'CHILD_ID', 'name' => 'CHILD_NAME', 'sid' => 'CHILD_EXTERNAL_ID',)
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
    'CURRENT_DATE'   => date('F Y', $targetDate->getUnixTimestamp()),
    'NOT_TODAY'      => $targetDate->isToday() ? '' : 'not-today',
    'TODAY_DATE'     => date('Y-m-d'),
    'NEXT_MONTH'     => date('Y-m-d', strtotime('+1 month', $targetDate->getUnixTimeStamp())),
    'PREV_MONTH'     => date('Y-m-d', strtotime('-1 month', $targetDate->getUnixTimeStamp())),
    'SELECTED_DATE'  => $targetDate->getDatabaseDate(),
    'WORK_USER_ID'   => $userID,
    'WORK_USER_NAME' => $userID == $_SESSION['id'] ? 'My' : $userHandler->formatName($user) . "'s",
    'USER_TOKEN'     => ($userID == $_SESSION['id'] ? $_SESSION['key'] : $user['key']),
    'WORK_SEMESTER'  => DueWork_All::getSemester(),
    'WORK_YEAR'      => date('Y'),
    'CALENDAR_VIEW'  => $_SESSION['calendar_view'] ? $_SESSION['calendar_view'] : 'month',
    'CALENDAR_COLLAPSE_EVENTS' => Config::get('calendar_collapse_events') ? 1 : '',
    'CALENDAR_START_DAY'       => Config::get('calendar_start_day') ?: 0,
);

$this->setVar($output);

$selectedMonth = $targetDate->getMonth();
$selectedYear  = $targetDate->getYear();

/* calculate month layout
 */
//$firstDay       = (int)date("w", mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));
//$numDaysinMonth = (int)date("d", mktime(0, 0, 0, $selectedMonth + 1, 0, $selectedYear));
//$numWeeksMonth  = ceil(($firstDay + $numDaysinMonth) / 7);
//$totalDays      = $numWeeksMonth * 7 + (6 - $firstDay);
//
//$startDay = mktime(12, 0, 0, $selectedMonth, 1, $selectedYear) - strtotime("+$firstDay day", 0);
//
//// output calendar
//$summary = array();
//
///* Have to replace all the other $user's with this one -_-
// */
//$event_api         = new Calendar_Api_EventType_Submission_Schoolbox_Default(
//    (isset($userID) ? Schoolbox\UserMapper::getInstance()->findById($userID) : $_SESSION['user']),
//    null
//);
///** @var Calendar_Event_Type_Submission[] $event_list */
//$event_list        = $event_api->getEvents($startDay, strtotime("+$totalDays day", $startDay));
//$event_list_by_day = array();
//foreach ($event_list as $event_object) {
//    // Filter out items not yet published, for non-staff
//    if (!$isStaff && $event_object->getPublishDate()->getUnixTimestamp() > $_SERVER['REQUEST_TIME']) {
//        continue;
//    }
//    $date                       = $event_object->getStartTime()->getUsingFormat('dmY');
//    $event_list_by_day[$date][] = $event_object;
//}
//
//for ($i = 0; $i < $totalDays; $i++) {
//    $currentDay = strtotime("+$i day", $startDay);
//
//    $day = $i % 7;
//
//    if ($i >= $firstDay && $i < $numDaysinMonth + $firstDay) {
//        $this->setVar('OTHER_MONTH', '');
//    }
//
//    $this_day = date('dmY', $currentDay);
//    $output   = array(
//        'DATE'   => date("j", $currentDay),
//        'PAST'   => $i >= $firstDay && $i < $numDaysinMonth + $firstDay ? '' : 'past',
//        'ACTIVE' => $this_day == $targetDate->getUsingFormat('dmY') ? 'active' : '',
//        'TODAY'  => date('dmY') == $this_day ? 'today' : '',
//        'RIGHTY' => $day > 3 ? 'righty' : '',
//    );
//
//    $this->setVar($output);
//
//    /** @var Calendar_Event_Type_Submission[] $eventList */
//    $eventList = isset($event_list_by_day[$this_day]) ? $event_list_by_day[$this_day] : array();
//
//    foreach ($eventList as $event) {
//        $date = $event->getStartTime();
//
//        $event_id = $event->getEventId();
//        if ($event->getIsAssessment()) {
//            $event_id = 'assess-' . $event_id;
//        }
//        $category_id = $event->getCategory()->getId();
//        $output      = array(
//            'EVENT_ID'     => $event_id,
//            'TIME'         => $event->getTime(),
//            'EVENT'        => Scrub::text($event->getTitle()),
//            'EVENT_CAT_ID' => $category_id,
//        );
//
//        $this->setVar($output);
//        $this->parse('event_block', 'event', true);
//
//        /* record overview for summary box */
//        $summary[$category_id] = array(
//            'name'  => $event->getTitle(),
//            'cid'   => $category_id,
//            'total' => ++$summary[$category_id]['total'],
//            'code'  => $event->getCategory()->getCodeString(),
//        );
//    }
//
//    $this->parse('day_block', 'day', true);
//    $this->setVar('event_block', '');
//
//    if ($day == 6 && $i) {
//        $this->parse('week_block', 'week', true);
//        $this->setVar('day_block', '');
//    }
//}


// create a custom instance of the upcoming work controller, and set it up for this context
$chf        = new Component_Homepage_Factory(new Schoolbox\User(), $this);
$cdm        = Component_DefinitionMapper::getInstance();
$definition = $cdm->findOneByColumn('class', 'Component_Dashboard_UpcomingWorkController');
$instance   = new Component_Instance();
$instance->setDefinition($definition);

$dueWorkController = $chf->buildComponent($instance);

// apply configs
$dueWorkController->setup(
    array(),
    array(
        'actionable'    => array('label' => 'actionable', 'value' => true),
        'allow_parents' => array('label' => 'allow_parents', 'value' => true),
        'size'          => array('label' => 'size', 'value' => 0),
        'user'          => array('label' => 'user', 'value' => $userID),
    )
);

$dueWorkController->execute();
$dueWorkController->renderTemplate('UPCOMING_WORK');


// subject listings
$j        = 0;
$synClass = new External_Class;

$return_mapper = new SubmissionBox\ReturnedWorkMapper();
$assess_mapper = new OnlineAssessment\AttemptMapper();

$restrict_grades = Cfg::get('restrict_parent_grades') && $groupHandler->isParent();


foreach ($categoryList as $category) {
    $categoryID = $category['id'];

    /* yes it actually requires THIS MUCH code to get a teachers name. */
    if (Cfg::get('external_enable')) {
        $teacherOutput = array();
        $teacherList   = array();
        $codeList      = explode(',', $category['code']);

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

                    $emailObj     = new Email();
                    $emailAddress = $emailObj->formatEmailFromArray($teacher);

                    $teacherOutput[] = array(
                        'id'    => $teacher['uid'],
                        'name'  => $teacherName,
                        'email' => $_SESSION['mailhandle'] . $emailAddress,
                        'synid' => $teacherSynID,
                    );
                }
            }
        }
    }

    $categoryIDs[] = $categoryID;
    $categoryCode  = $category['code'] ? $category['code'] : '';

    //
    // retrieve subject grades.
    // Ensure that grades are properly ordered by date.
    if (!$restrict_grades) {
        $studentGrades = array_merge(
            $return_mapper->getStudentMarks($category['id'], $userID, array('visibility' => $visibilities)),
            $assess_mapper->getStudentMarks($category['id'], $userID)
        );
        usort(
            $studentGrades,
            function ($a, $b) {
                return $a['date'] > $b['date'] ? -1 : 1;
            }
        );
        $studentGrades = array_map(
            function ($row) {
                return $row['mark'];
            },
            $studentGrades
        );
    } else {
        $studentGrades = array();
    }

    $gradeAvg    = -1;
    $grade_count = count($studentGrades);
    $show_grades = Cfg::get('show_overall_grade') && !$restrict_grades;
    $trendDir    = '';
    $trendAmt    = '';
    if ($show_grades) {
        $card  = new Schoolbox\Assessment\SubjectReportCard(
            $category['id'],
            $userID,
            $selectedYear,
            DueWork_All::getSemester($targetDate->getUnixTimestamp()),
            $visibilities
        );
        $grade = $card->getGradeAverage();
        if (isset($grade['grade'])) {
            $gradeAvg = $grade['grade'];
            $trendAmt = $grade['trend'];
            if ($trendAmt > 0) {
                $trendDir = '&#x25B2;';
            } elseif ($trendAmt < 0) {
                $trendDir = '&#x25BC;';
            }
            if ($trendAmt != 0) {
                $trendAmt = sprintf('%0.1f', $trendAmt) . '%';
            }
        }
    }

    // calculate grade averages and trending
    $this->setFile('subjectTpl', 'activity/subjectlist.html');
    $this->setBlock('subjectTpl', 'teacher', 'teacher_block');
    $this->setBlock('subjectTpl', 'subject_grade', 'subject_grade_block');
//    $this->setBlock('subjectTpl', 'icon', 'icon_block');

    $output = array(
        'SUBJ_CAT_ID'       => $categoryID,
        'SUBJ_SUBJECT_NAME' => Scrub::Text($category['name']),
        'SUBJ_SUBJECT_ICON' => '/images/mime/60x60/man.png',
        'SUBJ_SUBJECT_CODE' => $categoryCode,
        'SUBJ_SUBJECT_URL'  => '/homepage/' . $categoryID,
        // This isn't used in activity/subjectlist.html
//        'SUBJ_TOTAL'        => $summary[$category['id']]['total'],
        'SUBJ_TREND_DIR'    => $trendDir,
        'SUBJ_TREND_AMT'    => $trendAmt,
        'SUBJ_HAS_GRADE'    => $show_grades ? 'grade' : '',
        'DARK'              => ++$j % 2 ? '' : 'dark'
    );
    $this->setVar($output);

    if ($gradeAvg == -1) {
        $this->setVar('subject_grade_block', '');
    } else {
        $this->setVar(
            array(
                'SUBJ_GRADE'     => $returnHandler->percent2letter($gradeAvg),
                'SUBJ_GRADEIANT' => (int)($gradeAvg / 10),
            )
        );
        $this->parse('subject_grade_block', 'subject_grade');
    }


    if (Cfg::get('external_enable')) {
        $this->setVar('teacher_block', '');
        if (isset($teacherOutput) && count($teacherOutput)) {
            foreach ($teacherOutput as $teacher) {
                $tOutput = array(
                    'SUBJ_TEACHER_ID'           => $teacher['id'],
                    'SUBJ_TEACHER_NAME'         => $teacher['name'],
                    'SUBJ_TEACHER_URL'          => $teacher['email'],
                    'SUBJ_TEACHER_ROLE'         => 'Teacher',
                    'SUBJ_TEACHER_AVATAR'       => "/portrait?userId={$teacher['synid']}&size=square64",
                    'SUBJ_TEACHER_AVATAR_TITLE' => $teacher['name']
                );
                $this->setVar($tOutput);
                $this->parse('teacher_block', 'teacher', true);
            }
        }
    } else {
        $this->setVar('teacher_block', '');
    }
//    $this->parse('icon_block', 'icon');
    $this->parse('SUBJECT_LIST', 'subjectTpl', true);
}

$feedItems = array();

foreach (getActivities($user['uid'], 15, $categoryIDList, $visibilities, $restrict_grades) as $activity) {
    $mark        = null;
    $numericMark = null;

    $categoryName = '<a href="/homepage/' . $activity->getCategory()->getId() . '">'
        . Scrub::text($activity->getCategory()->getName()) . '</a>';

    //
    // box URL depends on whether this is an assessment or submission
    $boxUrl = $taskName;
    if ($activity->getType() == SubmissionBox\WorkType::TYPE_ONLINE_ASSESSMENT) {
        if ($activity->getEvent() == 'closed') {
            $boxUrl = $activity->getName();
        } else {
            $boxUrl = sprintf(
                '<a href="/assess/intro/%d">%s</a>',
                $activity->getId(),
                $activity->getName()
            );
        }
    } elseif (!$restrictSubmissions) {
        $boxUrl = sprintf(
            '<a href="/submission/submissionHistory/%d/%d">%s</a>',
            $activity->getId(),
            $userID,
            $activity->getName()
        );
    } else {
        $boxUrl = $activity->getName();
    }

    switch ($activity->getEvent()) {
        case 'new':
            $stringDesc = sprintf(
                'New %s %s added in %s',
                strtolower($activity->getTypeName()),
                $boxUrl,
                $categoryName
            );
            break;

        case 'overdue':
            $stringDesc = sprintf(
                '<span class="overdue">"%s" in %s is due</span>',
                $boxUrl,
                $categoryName
            );
            break;

        case 'submitted':
            $stringDesc = sprintf(
                '%s submitted work for %s in %s',
                $user['person_label'],
                $boxUrl,
                $categoryName
            );
            break;

        case 'returned':
            if (!$restrict_grades) {
                //
                // Showing returned work:
                // Always show a link back to submission history
                // If there's a mark, then show that.
                // Otherwise, if there's a file attached, show that.
                $base_mark = $activity->getReturnedNormalizedMark();
                if (isset($base_mark)) {
                    // format for output
                    $rtn       = $returnHandler->getByUserAndTime($user['uid'], $activity->getDate()->getDatabase());
                    // Activity should never not be a submission box
                    $marksheet = Marksheet_Factory::build(
                        $rtn['slug'],
                        ($activity->isSubmission()
                            ? $activity->getSubmissionBox()
                            : new SubmissionBox()
                        )
                    );

                    // legacy support
                    if ($rtn['slug'] == 'free') {
                        $numericMark = $rtn['mark'];
                    } else {
                        $numericMark = $marksheet->convertRawMark($rtn['norm_mark'], $rtn['max_mark'], true, false);
                    }

                    $mark = sprintf(
                        '<a href="/submission/submissionHistory/%s/%s">%s</a>',
                        $rtn['box_id'],
                        $rtn['owner'],
                        $numericMark
                    );

                    $beginning = $user['person_label'] . ' received ' . $mark;
                } else {
                    $file_id   = $activity->getReturnedFileId();
                    $file_name = $activity->getReturnedWork()->getFilename();
                    if ($file_id && $file_name) {
                        $beginning = '<a href="/submission/send.php?id=' . $file_id . '&return=1">Work returned</a>';
                    } else {
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

    $activityTime = new Alaress_Date_Format($activity->getDate());
    $mark = $returnHandler->shortenGrade($numericMark);

    $feedItems[] = array(
        'content'   => $stringDesc,
        'time'      => $activityTime->getRelativeTime(),
        'icon'      => $activity->getEvent(),
        'grade'     => isset($mark) ? $mark : null,
        'gradeiant' => isset($mark) ? (int)($rtn['norm_mark'] / 10) : '',
        'has_grade' => isset($mark) ? 'grade' : 'no-grade',
    );
}

$activityList = new Activity_ListArray($feedItems);
$activityList->setShowAvatars(false);
$activityList->setShowIcons(true);
$activityList->setShowGrade(true);

if (empty($feedItems)) {
    $this->setVar('ACTIVITY_LIST', '<li>You have no recent activity.</li>');
} else {
    $activityList->setTarget($this, 'ACTIVITY_LIST');
    foreach ($activityList as $a) {
        // iterates across and pumps out the list
    }
}

//// subject drop down
//$this->dropDown(
//    'subject',
//    $summary,
//    '',
//    TEMPLATE_content,
//    array(
//        'cid'  => 'SUBJECT_ID',
//        'name' => 'SUBJECT_NAME'
//    )
//);

/**
 * Gets activities from new mapper classes.
 *
 * @param int   $user
 * @param int   $limit
 * @param array $categories
 * @param array $visibilities  Which submission box visibilities are viewable?
 * @param bool  $is_restricted Are student's marks restricted?
 *
 * @return ActivityCollection|Activity[]
 */
function getActivities($user, $limit, $categories, $visibilities, $is_restricted)
{
    $box_mapper    = new SubmissionBoxMapper();
    $assess_mapper = new OnlineAssessmentMapper();
    $submit_mapper = new SubmissionBox\Submission\FileMapper();
    $return_mapper = new SubmissionBox\ReturnedWorkMapper();
    $activities    = $box_mapper
        ->getActivities($user, $limit, $categories)
        ->merge($assess_mapper->getActivities($user, $limit, $categories))
        ->merge($submit_mapper->getActivities($user, $limit, $categories));
    if (!$is_restricted) {
        $activities->merge($return_mapper->getActivities($user, $limit, $categories, $visibilities));
    }
    $activities
        ->sortBy('date', 'desc')
        ->limit(0, 15);
    return $activities;
}
