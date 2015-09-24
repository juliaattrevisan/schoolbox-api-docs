<?php
/**
 * Page for the progress report for the tasks on a homepage.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/** @var SitePage $this */
if (!isset($this)) {
    require_once __DIR__ . '/../../include/Bootstrap.php';
    SitePage::localRedir('/task/progressReport');
}
// If form was submitted, redirect
if (isset($_GET['category_id']) && isset($_GET['unit_id'])) {
    SitePage::localRedir(sprintf('/task/progressReport/%d/%d', $_GET['category_id'], $_GET['unit_id']));
}

use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

// URL: task/progressReport/$classId/($unitId)
// Alternate: task/progressReport/$classId/($year/$semester)
// If no year and semester are supplied, then the current year/semester are used.
switch (count($this->arg)) {
    case 2:
        list($class_id, $unit_id) = $this->arg;
        break;
    case 3:
        list($class_id, $year, $semester) = $this->arg;
        break;
    case 1:
    default:
        list($class_id) = $this->arg;
        $year     = date('Y');
        $semester = date('m') <= 6 ? 1 : 2;
        break;
}

/** @var Category $category */
try {
    $category = CategoryMapper::getInstance()->findById($class_id);
    CategoryMapper::getInstance()->loadJoinedModel('codes', $category, array('set_method' => 'setCodes'));
} catch (Schoolbox\Mapper\Exception $e) {
    error(6);
}

// Check permission on category
$group = new Group();
if ($group->getPermission($class_id) < WRITE_ACCESS) {
    error(6);
}

// Check unit permissions
/** @var UnitInstance[]|\Schoolbox\Collection $unit_list */
$unit_list = UnitInstanceMapper::getInstance()->findForCategoryId($category->getId());
if (count($unit_list) === 0) {
    error(159, sprintf('<a href="/homepage/%d">Visit this subject\'s homepage</a>', $category->getId()));
}
if (!isset($unit_id)) {
    // Redirect to first task in the list
    // (that is in the requested year/semester) if any.
    // If not, then just redir to the first one.
    $firstId = null;
    foreach ($unit_list as $unit) {
        if (!isset($firstId)) {
            $firstId = $unit->getId();
        }
        $unitYear     = $unit->getOpenDate()->getYear();
        $unitSemester = $unit->getOpenDate()->getYearHalf();
        if ($year == $unitYear && $semester == $unitSemester) {
            $unit_id = $unit->getId();
            break;
        }
    }
    if (isset($unit_id)) {
        $this->localRedir('/task/progressReport/'.$class_id.'/'.$unit_id);
    } else {
        $this->localRedir('/task/progressReport/'.$class_id.'/'.$firstId);
    }
    exit;
} else {
    // Check that the requested unit exists within this class.
    /** @var UnitInstance $current_unit */
    $current_unit = null;
    foreach ($unit_list as $unit) {
        if ($unit->getId() == $unit_id) {
            $current_unit = $unit;
            break;
        }
    }
    if (!($current_unit instanceof UnitInstance)) {
        error(6);
    }
}

// Get list of students for this class, and group if it has one.
$code         = implode(',', $category->getCodes()->getColumnValues('code'));
$student_list = array();
$classStudentIdList   = array();
$groupStudentIdList   = array();
$removedStudentIdList = array();
if ($code && Config::get('external_enable')) {
    $class_handler = new External_Class();
    $class_list    = $class_handler->getStudents($code);
    if (is_array($student_list)) {
        $externalIdList = array_map(
            function($student) { return $student['synergy_id'];
            },
            $class_list
        );
        $classStudentIdList = \Schoolbox\UserMapper::getInstance()
            ->findByColumn('synergy_id', $externalIdList)
            ->getColumnValues('id');
    }
}
$group_handler = new Group();
$group_list    = $group_handler->getMemberList($category->getId());
if (is_array($group_list)) {
    $groupStudentIdList = array_unique(
        array_map(
            function($student) { return $student['uid'];
            },
            $group_list
        )
    );
}
// Try loading all the students who have completed a task for this group, but
// may no longer be in the class.
$removedStudentIdList = array();
foreach ($current_unit->getTasks() as $task) {
    foreach ($task->getCompletionList() as $completion) {
        $studentId = $completion->getStudent()->getId();
        if (!(in_array($studentId, $classStudentIdList) || in_array($studentId, $groupStudentIdList))) {
            $removedStudentIdList[] = $completion->getStudent()->getId();
        }
    }
}

/** @var User[] $userCollection */
$userCollection = UserMapper::getInstance()->findByColumn(
    'id',
    array_merge($classStudentIdList, $groupStudentIdList, $removedStudentIdList),
    array('order' => array('lastname' => 'asc', 'firstname' => 'asc'))
);
foreach ($userCollection as $userObject) {
    $student_list[] = array(
        'uid'        => $userObject->getId(),
        'synergy_id' => $userObject->getExternalId(),
        'firstname'  => $userObject->getFirstname(),
        'lastname'   => $userObject->getLastname(),
        'name'       => $userObject->formatName(),
        'removed'    => in_array($userObject->getId(), $removedStudentIdList),
    );
}

// Dumb <!-- blocks --> and {VARIABLES}
$this->setFile(TEMPLATE_content, 'task/progressReport.html');

$this->setBlock(TEMPLATE_content, 'unit_selector_semester', 'unit_selector_semester_block');
$this->setBlock(TEMPLATE_content, 'unit_selector_semester_unit_list', 'unit_selector_semester_unit_list_block');
$this->setBlock('unit_selector_semester_unit_list', 'unit_selector_unit', 'unit_selector_unit_block');

$this->setBlock(TEMPLATE_content, 'subheader_unit', 'subheader_unit_block');
$this->setBlock('subheader_unit', 'subheader_unit_task', 'subheader_unit_task_block');
$this->setBlock('subheader_unit_task', 'subheader_unit_task_submission_link', 'subheader_unit_task_submission_link_block');
$this->setBlock('subheader_unit_task', 'subheader_unit_task_assess_link', 'subheader_unit_task_assess_link_block');
$this->setBlock('subheader_unit_task', 'subheader_unit_task_no_link', 'subheader_unit_task_no_link_block');

$this->setBlock(TEMPLATE_content, 'student', 'student_block');
$this->setBlock('student', 'student_unit', 'student_unit_block');
$this->setBlock('student_unit', 'student_unit_task', 'student_unit_task_block');
$this->setBlock('student_unit_task', 'student_unit_task_check', 'student_unit_task_check_block');
$this->setBlock('student_unit_task', 'student_unit_task_submission_box', 'student_unit_task_submission_box_block');
$this->setBlock('student_unit_task_submission_box', 'student_unit_task_submission_box_mark', 'student_unit_task_submission_box_mark_block');
$this->setBlock('student_unit_task_submission_box', 'student_unit_task_submission_box_no_mark', 'student_unit_task_submission_box_no_mark_block');
$this->setBlock('student_unit_task', 'student_unit_task_online_assess', 'student_unit_task_online_assess_block');
$this->setBlock('student_unit_task_online_assess', 'student_unit_task_online_assess_mark', 'student_unit_task_online_assess_mark_block');
$this->setBlock('student_unit_task_online_assess', 'student_unit_task_online_assess_no_mark', 'student_unit_task_online_assess_no_mark_block');

$this->setTitle('Class Mark Sheet');

$this->setVar(
    array(
        'CATEGORY'      => $category->getName(),
        'CATEGORY_ID'   => $category->getId(),
        'CATEGORY_CODE' => $category->getCodeString(),
        'student_block' => '',
        'unit_selector_semester_block' => '',
    )
);

// Unit dropdown, with semester optgroups
$unit_year_list   = array();
$current_year     = $current_unit->getOpenDate()->getYear();
$current_semester = $current_unit->getOpenDate()->getYearHalf();
foreach ($unit_list as $unit) {
    $year     = $unit->getOpenDate()->getYear();
    $semester = $unit->getOpenDate()->getYearHalf();
    $unit_year_list[$year][$semester][] = $unit;
}
foreach (array_reverse($unit_year_list, true) as $year => $unit_semester_list) {
    foreach (array(1, 2) as $semester) {
        $this->setVar(
            array(
                'UNIT_SELECTOR_SEMESTER_SEMESTER' => $semester,
                'UNIT_SELECTOR_SEMESTER_YEAR'     => $year,
                'UNIT_SELECTOR_SEMESTER_SELECTED' => ($year == $current_year && $semester == $current_semester ? 'selected' : ''),
            )
        );
        $this->parse('unit_selector_semester_block', 'unit_selector_semester', true);
    }
}
// Yes it's the same loop again (PHPLib sucks)
foreach ($unit_year_list as $year => $unit_semester_list) {
    foreach (array(2, 1) as $semester) {
        $this->setVar(
            array(
                'UNIT_SELECTOR_UNIT_SEMESTER' => $semester,
                'UNIT_SELECTOR_UNIT_YEAR'     => $year,
                'unit_selector_unit_block'    => '',
            )
        );
        if (!empty($unit_semester_list[$semester])) {
            foreach ($unit_semester_list[$semester] as $unit) {
                $this->setVar(
                    array(
                        'UNIT_SELECTOR_UNIT_NAME'     => $unit->getTitle(),
                        'UNIT_SELECTOR_UNIT_ID'       => $unit->getId(),
                        'UNIT_SELECTOR_UNIT_SELECTED' => ($unit->getId() == $unit_id ? 'selected' : ''),
                    )
                );
                $this->parse('unit_selector_unit_block', 'unit_selector_unit', true);
            }
        }
        $this->parse('unit_selector_semester_unit_list_block', 'unit_selector_semester_unit_list', true);
    }
}

// Store assessment and submission marks here
$student_assess_list     = array();
$student_submission_list = array();
// Stupid handlers
$handler         = new Assess_Attempt();
$section_handler = new Assess_Section();

// The header
$this->setVar(
    array(
        'HEADER_UNIT_NAME'          => $current_unit->getTitle(),
        'HEADER_UNIT_COUNT'         => $current_unit->getTasks()->count(),
        'subheader_unit_task_block' => '',
    )
);
$task_list = $current_unit->getTasks();

foreach ($task_list as $task) {
    // output task header beneath unit header
    $box_id    = $task->getSubmissionBox()->getId();
    $assess_id = $task->getAssessment()->getId();
    $this->setVar(
        array(
            'SUBHEADER_UNIT_TASK_NAME'                  => $task->getTitle(),
            'SUBHEADER_UNIT_TASK_SUBMISSION_ID'         => $box_id,
            'SUBHEADER_UNIT_TASK_ASSESS_ID'             => $assess_id,
            'subheader_unit_task_submission_link_block' => '',
            'subheader_unit_task_assess_link_block'     => '',
            'subheader_unit_task_no_link_block'         => '',
        )
    );
    if (!empty($box_id)) {
        try {
            $detail = new DueWork_Detail($task->getSubmissionBox());
            $detail->processStudents($userCollection);
            $student_submission_list[$box_id] = $detail->getSitepageData();
            $this->parse('subheader_unit_task_submission_link_block', 'subheader_unit_task_submission_link');
        } catch (\Schoolbox\Mapper\Exception $e) {
            $this->parse('subheader_unit_task_no_link_block', 'subheader_unit_task_no_link');
        }
    } elseif (!empty($assess_id)) {
        // attempt_list is a list of all valid attempts, ordered chronologically.
        // Filter to most recent submission only.
        $attempt_list = $handler->getList($assess_id);
        if (!empty($attempt_list)) {
            foreach ($attempt_list as $attempt) {
                $student_assess_list[$assess_id][$attempt['user_id']] = $attempt;
            }
            // Load max mark, because getList doesn't get it.
            foreach ($student_assess_list[$assess_id] as &$attempt) {
                $attempt['maxMark']      = $handler->getTotalMarks($assess_id, $attempt['attempt_id']);
                $attempt['firstSection'] = $section_handler->getFirst($assess_id);
            }
            unset($attempt);
            $this->parse('subheader_unit_task_assess_link_block', 'subheader_unit_task_assess_link');
        } else {
            $this->parse('subheader_unit_task_no_link_block', 'subheader_unit_task_no_link');
        }
    } else {
        $this->parse('subheader_unit_task_no_link_block', 'subheader_unit_task_no_link');
    }
    $this->parse('subheader_unit_task_block', 'subheader_unit_task', true);
}
$this->parse('subheader_unit_block', 'subheader_unit', true);

// Placeholder icons
$status_list = array(
    Task\InstanceCompletion::COMPLETION_STATUS_INCOMPLETE            => array(
        'text'  => 'Incomplete',
        'class' => 'icon-time-value',
    ),
    Task\InstanceCompletion::COMPLETION_STATUS_REQUIRES_CONFIRMATION => array(
        'text'  => 'Self Assessed',
        'class' => 'icon-alert',
    ),
    Task\InstanceCompletion::COMPLETION_STATUS_COMPLETE              => array(
        'text'  => 'Complete',
        'class' => 'icon-approve',
    ),
);

// The students
$j = 0;
foreach ($student_list as $student) {
    $this->setVar(
        array(
            'ON_OFF'                => ++$j % 2 ? '' : 'dark',
            'STUDENT_ID'            => $student['uid'],
            'STUDENT_SYNERGY_ID'    => $student['synergy_id'],
            'STUDENT_SURNAME'       => $student['lastname'],
            'STUDENT_FIRSTNAME'     => $student['firstname'],
            'STUDENT_NAME'          => $student['name'],
            'STUDENT_REMOVED_CLASS' => $student['removed'] ? 'removed-student' : '',
            'student_unit_block' => '',
        )
    );
    $this->setVar('student_unit_task_block', '');
    foreach ($current_unit->getTasks() as $task) {
// Get the completion status for this student.
        $student_status = Task\InstanceCompletion::COMPLETION_STATUS_INCOMPLETE;
        foreach ($task->getCompletionList() as $completion) {
            if ($completion->getStudent()->getId() == $student['uid']) {
                $student_status = $completion->getCompletionStatus();
                break;
            }
        }

        $this->setVar(
            array(
                'STUDENT_UNIT_TASK_ID'                   => $task->getId(),
                'student_unit_task_check_block'          => '',
                'student_unit_task_online_assess_block'  => '',
                'student_unit_task_submission_box_block' => '',
            )
        );
        $box_id    = $task->getSubmissionBox()->getId();
        $assess_id = $task->getAssessment()->getId();
        if (!empty($box_id)) {
            // What's this student's mark for this submission?
            // For now use the DueWork_Detail class
            $this->setVar(
                array(
                    'STUDENT_UNIT_TASK_SUBMISSION_ID'                => $box_id,
                    'student_unit_task_submission_box_mark_block'    => '',
                    'student_unit_task_submission_box_no_mark_block' => '',
                )
            );
            if (isset($student_submission_list[$box_id][$student['uid']]['return']['mark'])) {
                $attempt = $student_submission_list[$box_id][$student['uid']];
                $this->setVar('STUDENT_UNIT_TASK_SUBMISSION_MARK', $attempt['return']['mark']);
                // Gradient: norm_mark/max_mark
                $gradient = '';
                if (isset($attempt['mark']['norm_mark'])) {
                    $gradient = (int)($attempt['mark']['norm_mark'] / 10);
                }
                $this->setVar('STUDENT_UNIT_TASK_SUBMISSION_GRADIENT', $gradient);
                $this->parse('student_unit_task_submission_box_mark_block', 'student_unit_task_submission_box_mark');
            } else {
                $this->parse('student_unit_task_submission_box_no_mark_block', 'student_unit_task_submission_box_no_mark');
            }
            $this->parse('student_unit_task_submission_box_block', 'student_unit_task_submission_box');
        } elseif (!empty($assess_id)) {
            // What is the mark for this student?
            $this->setVar(
                array(
                    'STUDENT_UNIT_TASK_ASSESS_ID'                   => $box_id,
                    'student_unit_task_online_assess_mark_block'    => '',
                    'student_unit_task_online_assess_no_mark_block' => '',
                )
            );
            $this->setVar('STUDENT_UNIT_TASK_ASSESS_ID', $assess_id);
            if (isset($student_assess_list[$assess_id][$student['uid']])) {
                $attempt = $student_assess_list[$assess_id][$student['uid']];
                $this->setVar(
                    array(
                        'STUDENT_UNIT_TASK_ASSESS_FIRST_SECTION_ID'      => $attempt['firstSection']['section_id'],
                        'STUDENT_UNIT_TASK_ASSESS_ATTEMPT_ID'            => $attempt['attempt_id'],
                        'STUDENT_UNIT_TASK_ASSESS_TOTAL_MARKS'           => $attempt['totalMarks'],
                        'STUDENT_UNIT_TASK_ASSESS_TOTAL_AVAILABLE_MARKS' => $attempt['maxMark'],
                        'STUDENT_UNIT_TASK_ASSESS_REVIEWED'              => isset($attempt['reviewedAt']),
                    )
                );
                // Calc gradient
                $gradient = '';
                if (isset($attempt['totalMarks'])) {
                    $gradient = (int)($attempt['totalMarks'] / $attempt['maxMark'] * 10);
                }
                $this->setVar('STUDENT_UNIT_TASK_ASSESS_GRADIENT', $gradient);
                $this->parse('student_unit_task_online_assess_mark_block', 'student_unit_task_online_assess_mark');
            } else {
                $this->parse('student_unit_task_online_assess_no_mark_block', 'student_unit_task_online_assess_no_mark');
            }
            $this->parse('student_unit_task_online_assess_block', 'student_unit_task_online_assess');
        } else {
            $this->setVar(
                array(
                    'STUDENT_UNIT_TASK_STATUS_TEXT'  => $status_list[$student_status]['text'],
                    'STUDENT_UNIT_TASK_STATUS_CLASS' => $status_list[$student_status]['class'],
                    'STUDENT_UNIT_TASK_STATUS'       => $student_status,
                )
            );
            $this->parse('student_unit_task_check_block', 'student_unit_task_check', true);
        }
        $this->parse('student_unit_task_block', 'student_unit_task', true);
    }
    $this->parse('student_unit_block', 'student_unit', true);
    $this->parse('student_block', 'student', true);
}

//// Possible setup of dropdown:
//// split unit dropdown by semester
//
//// Initial block spam
//$this->setBlock(TEMPLATE_content, 'semester_option', 'semester_option_block');
//$this->setBlock(TEMPLATE_content, 'year_option',     'year_option_block');
//
//// Semesters can be hardcoded, no need to look anything up
//// (may change if a school wants Term breakdowns rather than Semester)
//$semesters = array(1, 2);
//foreach ($semesters as $sem) {
//    $this->setVar(array(
//        'SEMESTER_SELECTED' => $sem == $semester ? ' selected' : '',
//        'SEMESTER_VALUE'    => $sem
//    ));
//    $this->parse('semester_option_block', 'semester_option', true);
//}
//
//// Years is trickier, because we first need to find out all the years in which the teacher has ...taught
//// while we figure that out, let's go with "the past 6 years"
//for ($y = $this_year; $y > $this_year - 6; --$y) {
//    $this->setVar(array(
//        'YEAR_SELECTED' => $y == $year ? ' selected' : '',
//        'YEAR_VALUE'    => $y
//    ));
//    $this->parse('year_option_block', 'year_option', true);
//}

//// Get all the tasks for this class/year/semester, and order by start/end date.
//// The finder methods for contexts should be fairly simple.
//$class = \Schoolbox\Resource\Category\ClassContext::getInstance()->findById($class);
//// Other contexts:
//// Schoolbox\Resource\Category\CourseContext
//// Schoolbox\Resource\Category\HomepageContext
//// TeacherContext: a single course unit, viewed from the context of a teacher.
//$unit_list = \Schoolbox\Assessment\Course\Instance\TeacherContext::getInstance()->findForClass($class_id, $year, $semester);
//
//// The header
//foreach ($unit_list as $unit) {
//    // output unit header
//    foreach ($unit->getTaskList() as $task) {
//        // output task header beneath unit header
//    }
//}
//
//foreach ($class->getStudentList() as $student) {
//    // output a row for each student
//    foreach ($unit_list as $unit) {
//        foreach ($unit->getTaskList() as $task) {
//            // output a cell for this student/class/row
//            $student_completion = $task->getResultForStudent();
//            // And spam out <!-- blocks --> and {VARIABLES}
//        }
//    }
//}
