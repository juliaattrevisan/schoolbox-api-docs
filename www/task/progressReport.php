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

use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;

// URL: task/progressReport/$classId/$unitId
list($class_id, $unit_id) = $this->arg;

/** @var Category $category */
try {
    $category = CategoryMapper::getInstance()->findById($class_id);
    CategoryMapper::getInstance()->loadJoinedModel('codes', $category);
}
catch (Schoolbox\Mapper\Exception $e) {
    error(6);
}

// Check permission on category
$group = new Group();
if ($group->getPermission($class_id) < WRITE_ACCESS) {
    error(6);
}

// Get list of students for this class
/** @var UnitInstance[]|\Schoolbox\Collection $unit_list */
$unit_list = UnitInstanceMapper::getInstance()->findForCategoryId($category->getId());
$code      = implode(',', $category->getCodes()->getColumnValues('code'));
$student_list = array();
if ($code && Config::get('external_enable')) {
    $class_handler = new External_Class();
    $student_list  = $class_handler->getStudents($code);
}
else {
    $group_handler = new Group();
    $student_list  = $group_handler->getMemberList($category->getId());
}

// Dumb <!-- blocks --> and {VARIABLES}
$this->setFile(TEMPLATE_content, 'task/progressReport.html');
$this->setBlock(TEMPLATE_content, 'header_unit', 'header_unit_block');
$this->setBlock(TEMPLATE_content, 'subheader_unit', 'subheader_unit_block');
$this->setBlock('subheader_unit', 'subheader_unit_task', 'subheader_unit_task_block');
$this->setBlock(TEMPLATE_content, 'student', 'student_block');
$this->setBlock('student', 'student_unit', 'student_unit_block');
$this->setBlock('student_unit', 'student_unit_task', 'student_unit_task_block');
$this->setBlock('student_unit_task', 'student_unit_task_check', 'student_unit_task_check_block');
$this->setBlock('student_unit_task_check', 'student_unit_task_check_status', 'student_unit_task_check_status_block');
$this->setBlock('student_unit_task', 'student_unit_task_submission_box', 'student_unit_task_submission_box_block');
$this->setBlock('student_unit_task', 'student_unit_task_online_assess', 'student_unit_task_online_assess_block');
$this->setTitle('Class Mark Sheet');

$this->setVar(
    array(
        'CATEGORY'          => $category->getName(),
        'CATEGORY_ID'       => $category->getId(),
        'header_unit_block' => '',
        'student_block'     => '',
    )
);

$submission_work_type_list = array();

// The header
foreach ($unit_list as $unit) {
    // output unit header
    $this->setVar(
        array(
            'HEADER_UNIT_NAME'          => $unit->getTitle(),
            'HEADER_UNIT_COUNT'         => $unit->getTasks()->count(),
            'subheader_unit_task_block' => '',
        )
    );
    $task_list = $unit->getTasks();

    foreach ($task_list as $task) {
        // output task header beneath unit header
        $this->setVar('SUBHEADER_UNIT_TASK_NAME', $task->getTitle());
        $this->parse('subheader_unit_task_block', 'subheader_unit_task', true);
        $box_id = $task->getSubmissionBox()->getId();
        if ($box_id) {
            SubmissionBoxMapper::getInstance()->loadJoinedModel('mark_type', $task->getSubmissionBox());
            $detail = new DueWork_Detail($box_id);
            $detail ->processStudents($student_list);
            $submission_work_type_list[$box_id] = $detail->getSitepageData();
        }
    }
    $this->parse('subheader_unit_block', 'subheader_unit', true);
    $this->parse('header_unit_block',    'header_unit',    true);
}

$status_list = array(
    Task\InstanceCompletion::COMPLETION_STATUS_INCOMPLETE => 'Incomplete',
    Task\InstanceCompletion::COMPLETION_STATUS_REQUIRES_CONFIRMATION => 'Pending',
    Task\InstanceCompletion::COMPLETION_STATUS_COMPLETE => 'Complete',
);

// The students
foreach ($student_list as $student) {
    $this->setVar(
        array(
            'STUDENT_ID'         => $student['uid'],
            'STUDENT_SYNERGY_ID' => $student['synergy_id'],
            'STUDENT_SURNAME'    => $student['lastname'],
            'STUDENT_FIRSTNAME'  => $student['firstname'],
            'student_unit_block' => '',
        )
    );
    foreach ($unit_list as $unit) {
        $this->setVar('student_unit_task_block', '');
        foreach ($unit->getTasks() as $task) {
            $student_status = '-';
            foreach ($task->getCompletionList() as $completion) {
                if ($completion->getStudent()->getId() == $student['uid']) {
                    $student_status = $completion->getCompletionStatus();
                    break;
                }
            }
            $completion = $task->getCompletionList();
            $this->setVar(
                array(
                    'STUDENT_UNIT_TASK_ID'                    => $task->getId(),
                    'STUDENT_UNIT_TASK_STATUS'                => $student_status,
                    'student_unit_task_check_block'           => '',
                    'student_unit_task_online_assess_block'   => '',
                    'student_unit_task_submission_box_block'  => '',
                    'student_unit_task_check_status_block'    => '',
                )
            );
            $box_id = $task->getSubmissionBox()->getId();
            if (!empty($box_id)) {
                // What's this student's mark for this submission?
                // For now use the DueWork_Detail class
                $this->setVar('STUDENT_UNIT_TASK_SUBMISSION_MARK', $submission_work_type_list[$box_id][$student['uid']]['return']['mark']);
                $this->parse('student_unit_task_submission_box_block', 'student_unit_task_submission_box', true);
            }
            elseif ($task->getAssessment()->getId()) {
                // What is the mark for this student?
                $this->setVar(
                    array(
                        'STUDENT_UNIT_TASK_ASSESS_TOTAL_MARKS'           => 0,
                        'STUDENT_UNIT_TASK_ASSESS_TOTAL_AVAILABLE_MARKS' => 10,
                    )
                );
                $this->parse('student_unit_task_online_assess_block', 'student_unit_task_online_assess', true);
            }
            else {
                foreach ($status_list as $status => $label) {
                    $this->setVar(
                        array(
                            'STUDENT_UNIT_TASK_STATUS_TEXT'     => $label,
                            'STUDENT_UNIT_TASK_STATUS_VALUE'    => $status,
                            'STUDENT_UNIT_TASK_STATUS_SELECTED' => ($status == $student_status ? 'selected' : ''),
                        )
                    );
                    $this->parse('student_unit_task_check_status_block', 'student_unit_task_check_status', true);
                }
                $this->parse('student_unit_task_check_block', 'student_unit_task_check', true);
            }
            $this->parse('student_unit_task_block', 'student_unit_task', true);
        }
        $this->parse('student_unit_block', 'student_unit', true);
    }
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
