<?php
/**
 * Course component for a class.
 * Before a course is selected, it contains a selector for the course - after
 * this, it contains a list of units and tasks within the course.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Collection;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Assessment\Course\Task;

class Component_Homepage_CourseInstanceController extends Component_Homepage_ControllerAbstract {

    /**
     * @var Collection|UnitInstance[]
     */
    protected $unit_list;

    /**
     * @var Collection|Task\InstanceCompletion[]
     */
    protected $completion_list;

    /**
     * @var array
     */
    protected $student_list;

    /**
     * @var bool
     */
    protected $is_student;

    /**
     * {@inheritdoc}
     */
    public function execute() {
        // Get the list of students for this class (in which method?)
        $group_handler = new Group();
        $code = $this->getContext('code');

        // Should check on user's permissions for this row instead
        $this->is_student = $group_handler->isStudent();

        // Consider getting class by student ID
        if ($code && Config::get('external_enable')) {
            $class_handler = new External_Class();
            $this->student_list = $class_handler->getStudents($code);
        }
        else {
            $this->student_list = $group_handler->getMemberList($this->getContext('id'));
        }

        // Get the list of units for this course, as well as who has actually
        // completed each unit.
        $this->unit_list = UnitInstanceMapper::getInstance()->findForComponent($this->getInstance());

        // Get task instance IDs out of unit list.
        // Then, retrieve for: current user if a student, or all students if a
        // teacher.
        $task_list = array_reduce(
            array_map(
                function($v) { return $v->getColumnValues('id'); },
                $this->unit_list->getColumnValues('tasks')
            ),
            'array_merge',
            array()
        );
        $this_student_id = null;
        if ($this->is_student) {
            $this_student_id = $_SESSION['id'];
        }
        $this->completion_list = Task\InstanceCompletionMapper::getInstance()->findLatestForList(
            $task_list, $this_student_id
        );
    }

    /**
     * {@inheritdoc}
     */
    public function renderTemplate($templateHandle) {
        if (!parent::loadTemplate($templateHandle, 'courseInstance')) {
            return false;
        }

        $this->parseConfig();

        $this->setVar(array(
            'SESSION_NAME'    => session_name(),
            'SESSION_ID'      => session_id(),
            'COMPONENT_NAME'  => $this->getConfig('componentname'),
            'COURSE_HOMEPAGE' => str_replace('/homepage/', '', Config::get('course_homepage')),
        ));

        $this->setBlock($templateHandle,    'course_selector',      'course_selector_block');
        $this->setBlock($templateHandle,    'course_modify',        'course_modify_block');
        $this->setBlock($templateHandle,    'course_unit_list',     'course_unit_list_block');
        $this->setBlock('course_unit_list', 'unit',                 'unit_block');
        $this->setBlock('course_unit_list', 'course_modify',        'course_modify_block');
        $this->setBlock('unit',             'unit_expand',          'unit_expand_block');
        $this->setBlock('unit',             'unit_no_expand',       'unit_no_expand_block');
        $this->setBlock('unit',             'unit_completion',      'unit_completion_block');
        $this->setBlock('unit',             'unit_modify',          'unit_modify_block');
        $this->setBlock('unit',             'unit_delete',          'unit_delete_block');
        $this->setBlock('unit',             'unit_task',            'unit_task_block');
        $this->setBlock('unit',             'unit_actions',         'unit_actions_block');
        $this->setBlock('unit_task',        'unit_task_link',       'unit_task_link_block');
        $this->setBlock('unit_task',        'unit_task_no_link',    'unit_task_no_link_block');
        $this->setBlock('unit_task',        'unit_task_count',      'unit_task_count_block');
        $this->setBlock('unit_task',        'unit_task_completion', 'unit_task_completion_block');
        $this->setBlock('unit_task',        'unit_task_start',      'unit_task_start_block');
        $this->setBlock('unit_task',        'unit_task_modify',     'unit_task_modify_block');
        $this->setBlock('unit_task',        'unit_task_delete',     'unit_task_delete_block');

        $this->setVar('course_selector_block',  '');
        $this->setVar('course_unit_list_block', '');
        $this->setVar('course_modify_block',    '');

        // If courseHomepage is set, a course has been selected.
        // This means that the units and tasks that were imported are displayed.
        // If it isn't, then a selector is displayed (if user has write access)
        if ($this->getConfig('courseHomepage')) {
            // iterate the list of homepages
            $this->setVar('course_modify_block', '');
            if (!$this->is_student) {
                $this->parse('course_modify_block', 'course_modify');
            }
            $this->parse('course_unit_list_block', 'course_unit_list');

            // unit list will be a collection of Unit models
            $this->setVar('unit_block', '');
            $now = new DateTime('now');
            $i = 0;

            $student_count = count($this->student_list);
            foreach ($this->unit_list as $unit) {
                $is_open = $unit->isOpen();
                $this->setVar(
                    array(
                        'UNIT_ID'                  => Scrub::text($unit->getId()),
                        'UNIT_NAME'                => Scrub::text($unit->getTitle()),
                        'UNIT_OPEN_DATE'           => Scrub::text($unit->getOpenDate()->getDatabaseDate()),
                        'UNIT_DUE_DATE'            => Scrub::text($unit->getDueDate()->getDatabaseDate()),
                        'UNIT_BACKGROUND'          => ($i++%2 === 1 ? 'dark' : ''),
                        'UNIT_STUDENT_COUNT'       => $student_count,
                        'UNIT_TASK_COMPLETE_CLASS' => '',
                        'UNIT_EXPANDED_STATE'      => ($is_open ? 'expanded' : 'collapsed'),
                        'UNIT_LIST_CLASS'          => ($is_open ? ''         : 'hide'),
                        'unit_task_block'          => '',
                        'unit_completion_block'    => '',
                        'unit_expand_block'        => '',
                        'unit_no_expand_block'     => '',
                        'unit_delete_block'        => '',
                        'unit_modify_block'        => '',
                        'unit_actions_block'       => '',
                    )
                );
                if (!$this->is_student) {
                    $this->parse('unit_delete_block', 'unit_delete');
                    $this->parse('unit_modify_block', 'unit_modify');
                    if (!$unit->isAfterDueDate()) {
                        $this->parse('unit_actions_block', 'unit_actions');
                    }
                }

                $display_task_list =
                    $unit->getTasks()->count() > 0 &&
                    ($unit->isOpen() || !$this->is_student)
                ;
                if ($display_task_list) {
                    $this->parse('unit_expand_block', 'unit_expand');
                }
                else {
                    $this->parse('unit_no_expand_block', 'unit_no_expand');
                }

                // Output due date and urgency
                // If due date isn't reached, don't display at all, instead only
                // show how long until this unit opens.
                $completion_status = null;
                $rel_time          = null;

                // How many days until this item is due?
                $diff = $now->diff(new DateTime($unit->getDueDate()->getDatabase()));
                $days = $diff->days;
                if ($diff->invert) {
                    $days = 0;
                }
                $urgency = min(10, max(0, $days));

                if ($unit->isBeforeOpenDate()) {
                    $completion_status = 'Opens';
                    $rel_time          = $unit->getOpenDate();
                    $urgency           = null;
                }
                // If student has completed all tasks within this course:
                // show when they completed it
                elseif ($unit->isOpen()) {
                    $completion_status = 'Due';
                    $rel_time          = $unit->getDueDate();

                }
                else {
                    $completion_status = 'Closed';
                    $rel_time          = $unit->getDueDate();
                    $urgency           = 0;
                }
                $rel_time = new Alaress_Date_Relative($rel_time);
                $this->setVar('UNIT_COMPLETION_STATUS', Scrub::text($completion_status));
                $this->setVar('UNIT_RELATIVE_DATE',     Scrub::text($rel_time->getRelativeTime()));
                $this->setVar('UNIT_UPCOMING_URGENCY',  Scrub::text($urgency));
                $this->parse('unit_completion_block', 'unit_completion');

                // Output list of tasks within units, only if the task is open
                if ($display_task_list) {

                    // Group the task list by task, then student.
                    $grouped_list = array();
                    foreach ($this->completion_list as $item) {
                        $student_id       = $item->getStudent()->getId();
                        $task_instance_id = $item->getTaskInstance()->getId();
                        $grouped_list[$task_instance_id][$student_id] = $item;
                    }

                    foreach ($unit->getTasks() as $task) {

                        // Students only:
                        // Output whether this item is complete.
                        $unit_task_class  = '';
                        $unit_task_status = '';
                        if ($this->is_student) {
                            $unit_task_class = 'incomplete';
                            $completion_item = $grouped_list[$task->getId()][$_SESSION['id']];
                            if ($completion_item instanceof Task\InstanceCompletion) {
                                switch ($completion_item->getCompletionStatus()) {
                                    case Task\InstanceCompletion::COMPLETION_STATUS_COMPLETE:
                                        $unit_task_class  = 'complete';
                                        $unit_task_status = 'Complete';
                                        break;
                                    case Task\InstanceCompletion::COMPLETION_STATUS_REQUIRES_CONFIRMATION:
                                        $unit_task_class  = 'pending';
                                        $unit_task_status = 'Pending';
                                        break;
                                    case Task\InstanceCompletion::COMPLETION_STATUS_INCOMPLETE:
                                    default:
                                        break;
                                }
                            }
                        }
                        $this->setVar('UNIT_TASK_COMPLETE_CLASS', $unit_task_class);

                        $complete_count = count($grouped_list[$task->getId()]);
                        $gradient       = round($complete_count / $student_count * 10);
                        $this->setVar(
                            array(
                                'UNIT_TASK_ID'                => Scrub::text($task->getId()),
                                'UNIT_TASK_NAME'              => Scrub::text($task->getTitle()),
                                'UNIT_TASK_CLASS'             => Scrub::text($task->getIconClass()),
                                'UNIT_TASK_TYPE_LABEL'        => Scrub::text($task->getLabel()),
                                'UNIT_TASK_COMPLETION_COUNT'  => $complete_count,
                                'UNIT_TASK_GRADIENT'          => $gradient,
                                'UNIT_TASK_COMPLETION_STATUS' => $unit_task_status,
                                'UNIT_TASK_COMPLETE_CLASS'    => $unit_task_class,
                                'unit_task_link_block'        => '',
                                'unit_task_no_link_block'     => '',
                                'unit_task_count_block'       => '',
                                'unit_task_completion_block'  => '',
                                'unit_task_start_block'       => '',
                                'unit_task_delete_block'      => '',
                                'unit_task_modify_block'      => '',
                            )
                        );

                        // Is there a link for this item?
                        if (strlen($task->getLink()) > 0) {
                            $this->parse('unit_task_link_block', 'unit_task_link');
                        }
                        else {
                            $this->parse('unit_task_no_link_block', 'unit_task_no_link');
                        }

                        // Teacher sees the number of students that have completed
                        // this task, nobody else does.
                        if ($this->is_student) {
                            if ($unit_task_status) {
                                $this->parse('unit_task_completion_block', 'unit_task_completion');
                            }
                            if ($unit->isOpen()) {
                                $this->parse('unit_task_start_block', 'unit_task_start');
                            }
                        }
                        else {
                            $this->parse('unit_task_count_block', 'unit_task_count');
                            $this->parse('unit_task_delete_block', 'unit_task_delete');
                            $this->parse('unit_task_modify_block', 'unit_task_modify');
                        }
                        $this->parse('unit_task_block', 'unit_task', true);
                    }
                }
                $this->parse('unit_block', 'unit', true);
            }

        }
        else {
            $this->parse('course_selector_block', 'course_selector');
        }

        return true;
    }

}