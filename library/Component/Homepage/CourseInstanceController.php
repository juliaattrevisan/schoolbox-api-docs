<?php
/**
 * Course component for a class.
 * Before a course is selected, it contains a selector for the course - after
 * this, it contains a list of units and tasks within the course.
 *
 * This is the Course Outline component.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Collection;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Component_Homepage_CourseInstanceController extends Component_Homepage_ControllerAbstract
{

    /**
     * A list of units within this course.
     *
     * @var Collection|UnitInstance[]
     */
    protected $unit_list;

    /**
     * A list of students who have completed the tasks within the course.
     *
     * @var Collection|Task\InstanceCompletion[]
     */
    protected $completion_list;

    /**
     * A list of students for this category.
     *
     * @var array
     */
    protected $student_list = array();

    /**
     * @var bool
     */
    protected $readOnly;

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        // Get the list of students for this class (in which method?)
        $group_handler = new Group();
        $code          = $this->getContext()->getCodeString();

        // Get the list of units for this course, as well as who has actually
        // completed each unit.
        $this->unit_list = UnitInstanceMapper::getInstance()->findForComponent($this->getInstance());
        $this->readOnly  = $this->permission < WRITE_ACCESS;

        // Build student list
        if (count($this->unit_list)) {
            $classStudentIdList = array();
            $groupStudentIdList = array();
            if ($code && Config::get('external_enable')) {
                $class_handler    = new External_Class();
                $classStudentList = $class_handler->getStudents($code);
                if (is_array($classStudentList)) {
                    $externalIdList     = array_map(
                        function ($student) {
                            return $student['synergy_id'];
                        },
                        $classStudentList
                    );
                    $classStudentIdList = \Schoolbox\UserMapper::getInstance()
                        ->findByColumn('synergy_id', $externalIdList)
                        ->getColumnValues('id');
                }
            }
            $groupStudentList = $group_handler->getMemberList($this->getContext()->getId());
            if (is_array($groupStudentList)) {
                $groupStudentIdList = array_unique(
                    array_map(
                        function ($student) {
                            return $student['uid'];
                        },
                        $groupStudentList
                    )
                );
            }
            /** @var User[] $userCollection */
            $userCollection = UserMapper::getInstance()->findByColumn(
                'id', array_merge($classStudentIdList, $groupStudentIdList),
                array('order' => array('lastname' => 'asc', 'firstname' => 'asc'))
            );
            foreach ($userCollection as $userObject) {
                $this->student_list[] = array(
                    'uid'        => $userObject->getId(),
                    'synergy_id' => $userObject->getExternalId(),
                    'firstname'  => $userObject->getFirstname(),
                    'lastname'   => $userObject->getLastname(),
                );
            }

            // Get task instance IDs out of unit list.
            // Then, retrieve for: current user if a student, or all students if a
            // teacher.
            $task_list       = array_reduce(
                array_map(
                    function ($v) {
                        return $v->getColumnValues('id');
                    },
                    $this->unit_list->getColumnValues('tasks')
                ),
                'array_merge',
                array()
            );
            $this_student_id = null;
            if ($this->readOnly) {
                $this_student_id = $_SESSION['id'];
            }
            $this->completion_list = Task\InstanceCompletionMapper::getInstance()->findLatestForList(
                $task_list, $this_student_id
            );
        } else {
            if ($this->readOnly) {
                return false;
            }
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'courseInstance')) {
            return false;
        }

        $this->setComponentName(strlen(trim($this->getConfig('componentname'))) ? trim($this->getConfig('componentname')) : $this->getComponentName());
        $this->setVar(
            array(
                'SESSION_NAME'    => session_name(),
                'SESSION_ID'      => session_id(),
                'COURSE_HOMEPAGE' => str_replace('/homepage/', '', Config::get('course_homepage')),
            )
        );

        // Course selector blocks
        $this->setBlock($templateHandle, 'course_selector', 'course_selector_block');

        // Unit list blocks
        $this->setBlock($templateHandle, 'course_unit_list', 'course_unit_list_block');
        $this->setBlock('course_unit_list', 'unit', 'unit_block');
        $this->setBlock('unit', 'unit_expand', 'unit_expand_block');
        $this->setBlock('unit', 'unit_no_expand', 'unit_no_expand_block');
        $this->setBlock('unit', 'unit_completion', 'unit_completion_block');
        $this->setBlock('unit', 'unit_actions', 'unit_actions_block');
        $this->setBlock('unit_actions', 'unit_modify', 'unit_modify_block');
        $this->setBlock('unit_actions', 'unit_delete', 'unit_delete_block');
        $this->setBlock('unit', 'unit_task', 'unit_task_block');
        $this->setBlock('unit', 'unit_report_actions', 'unit_report_actions_block');
        $this->setBlock('unit', 'unit_add_task_actions', 'unit_add_task_actions_block');
        $this->setBlock('unit_task', 'unit_task_duework_results', 'unit_task_duework_results_block');
        $this->setBlock('unit_task', 'unit_task_link', 'unit_task_link_block');
        $this->setBlock('unit_task', 'unit_task_no_link', 'unit_task_no_link_block');
        $this->setBlock('unit_task', 'unit_task_count', 'unit_task_count_block');
        $this->setBlock('unit_task', 'unit_task_completion', 'unit_task_completion_block');
        $this->setBlock('unit_task', 'unit_task_due_date', 'unit_task_due_date_block');
        $this->setBlock('unit_task', 'unit_task_start', 'unit_task_start_block');
        $this->setBlock('unit_task', 'unit_task_modify_form', 'unit_task_modify_form_block');
        $this->setBlock('unit_task', 'unit_task_delete_form', 'unit_task_delete_form_block');
        $this->setBlock('unit_task', 'unit_task_actions', 'unit_task_actions_block');
        $this->setBlock('unit_task_actions', 'unit_task_modify', 'unit_task_modify_block');
        $this->setBlock('unit_task_actions', 'unit_task_delete', 'unit_task_delete_block');
        $this->setBlock('unit_task_actions', 'unit_task_quiz_results', 'unit_task_quiz_results_block');
        $this->setBlock('unit_task_actions', 'unit_task_quiz_attempts', 'unit_task_quiz_attempts_block');

        $this->setVar('course_selector_block', '');
        $this->setVar('course_unit_list_block', '');
        $this->setVar('course_modify_block', '');

        $this->setVar('UNIT_READONLY', ($this->readOnly ? 'true' : 'false'));

        // If courseHomepage is set, a course has been selected.
        // This means that the units and tasks that were imported are displayed.
        // If it isn't, then a selector is displayed (if user has write access)
        if (count($this->unit_list)) {
            $this->renderTemplateForUnitList();
        } else {
            $this->renderTemplateForCourseSelector();
        }

        /* Need title injected here
         */
        $this->parseConfig();

        return true;
    }

    /**
     * Renders the unit list template for this component.
     */
    protected function renderTemplateForUnitList()
    {
        // unit list will be a collection of Unit models
        $this->setVar('unit_block', '');
        $i = 0;
        foreach ($this->unit_list as $unit) {
            $this->setVar('UNIT_BACKGROUND', ($i++ % 2 === 1 ? 'dark' : ''));
            $this->renderUnitInUnitList($unit);
        }
        $this->parse('course_unit_list_block', 'course_unit_list');
    }

    /**
     * Renders the course selector template for this component.
     */
    protected function renderTemplateForCourseSelector()
    {
        $this->parse('course_selector_block', 'course_selector');
    }

    /**
     * Displays this unit in the list of units
     *
     * @param UnitInstance $unit The unit that the task is rendered into
     */
    public function renderUnitInUnitList(UnitInstance $unit)
    {

        // Group the task list by task, then student.
        $grouped_list  = array();
        $studentIdList = array_map(
            function ($student) {
                return $student['uid'];
            },
            $this->student_list
        );
        foreach ($this->completion_list as $item) {
            // Only add students who are in the list of students for this homepage.
            $student_id = $item->getStudent()->getId();
            if (in_array($student_id, $studentIdList)) {
                $task_instance_id                             = $item->getTaskInstance()->getId();
                $grouped_list[$task_instance_id][$student_id] = $item;
            }
        }

        // Is this a student? If so, figure out if they've completed all units
        // and change message accordingly.
        $is_completed         = false;
        $completion_date_list = array();
        if ($this->readOnly) {
            $is_completed = true;
            foreach ($unit->getTasks() as $task) {
                $completion = $grouped_list[$task->getId()][$_SESSION['id']];
                if (!($completion instanceof Task\InstanceCompletion)
                    || $completion->getCompletionStatus() != Task\InstanceCompletion::COMPLETION_STATUS_COMPLETE
                ) {
                    $is_completed = false;
                    break;
                } else {
                    $completion_date_list[] = $completion->getCompletionDate()->getDateTimeObject();
                }
            }
        }
        $is_completed = ($is_completed && !empty($completion_date_list));
        $is_expanded  = !$is_completed && $unit->isOpen();

        $this->setVar(
            array(
                'UNIT_ID'                     => Scrub::text($unit->getId()),
                'UNIT_HOMEPAGE_ID'            => Scrub::text($unit->getUnitCategory()->getId()),
                'UNIT_NAME'                   => Scrub::text($unit->getTitle()),
                'UNIT_OPEN_DATE'              => Scrub::text($unit->getOpenDate()->getDatabaseDate()),
                'UNIT_DUE_DATE'               => Scrub::text($unit->getDueDate()->getDatabaseDate()),
                'UNIT_STUDENT_COUNT'          => count($this->student_list),
                'UNIT_TASK_COMPLETE_CLASS'    => '',
                'UNIT_EXPANDED_STATE'         => ($is_expanded ? 'expanded' : 'collapsed'),
                'UNIT_EXPANDED_STATE_ICON'    => ($is_expanded  ? 'icon-collapse' : 'icon-add'),
                'UNIT_LIST_CLASS'             => ($is_expanded ? '' : 'hide'),
                'unit_task_block'             => '',
                'unit_completion_block'       => '',
                'unit_expand_block'           => '',
                'unit_no_expand_block'        => '',
                'unit_actions_block'          => '',
                'unit_delete_block'           => '',
                'unit_modify_block'           => '',
                'unit_report_actions_block'   => '',
                'unit_add_task_actions_block' => '',
            )
        );

        if (!$this->readOnly) {
            $this->parse('unit_delete_block', 'unit_delete');
            $this->parse('unit_modify_block', 'unit_modify');
            $this->parse('unit_report_actions_block', 'unit_report_actions');
            if (!$unit->isAfterDueDate()) {
                $this->parse('unit_add_task_actions_block', 'unit_add_task_actions');
            }
            $this->parse('unit_actions_block', 'unit_actions');
        }

        // Output due date and urgency
        // If due date isn't reached, don't display at all, instead only
        // show how long until this unit opens.
        $completion_status = null;
        $rel_time          = null;

        // How many days until this item is due?
        $now  = new DateTime('now');
        $diff = $now->diff(new DateTime($unit->getDueDate()->getDatabase()));
        $days = $diff->days;
        if ($diff->invert) {
            $days = 0;
        }
        $urgency = min(10, max(0, $days));

        if ($is_completed) {
            $completion_status = 'Completed';
            $rel_time          = new Alaress_Date_Format(max($completion_date_list));
            $urgency           = 10;
        } else {
            // Otherwise, show a standard open/due message.
            if ($unit->isBeforeOpenDate()) {
                $completion_status = 'This unit will open';
                $rel_time          = $unit->getRelativeOpenDate();
                $urgency           = null;
            } elseif ($unit->isOpen()) {
                $completion_status = 'This unit will close';
                $rel_time          = $unit->getRelativeDueDate();
            } else {
                $completion_status = 'This unit closed';
                $rel_time          = $unit->getRelativeDueDate();
                $urgency           = 0;
            }
        }
        $this->setVar('UNIT_COMPLETION_STATUS', Scrub::text($completion_status));
        $this->setVar('UNIT_RELATIVE_DATE', Scrub::text($rel_time->getRelativeTime()));
        $this->setVar('UNIT_UPCOMING_URGENCY', Scrub::text($urgency));
        $this->parse('unit_completion_block', 'unit_completion');

        // Output list of tasks within units, only if the task is open
        $display_task_list =
            $unit->getTasks()->count() > 0 &&
            ($unit->isOpen() || $unit->isAfterDueDate() || !$this->readOnly);
        if ($display_task_list) {
            $this->parse('unit_expand_block', 'unit_expand');
        } else {
            $this->parse('unit_no_expand_block', 'unit_no_expand');
        }
        if ($display_task_list || $is_completed) {
            foreach ($unit->getTasks() as $task) {
                $this->renderTaskInUnit($task, $unit, $grouped_list[$task->getId()]);
            }
        }

        $this->newZone('az-new-task-unit-' . $unit->getId(), 'ATTACH_ZONE');

        $this->parse('unit_block', 'unit', true);
    }

    /**
     * Displays this task in the list of tasks for a unit.
     *
     * @param Task\Instance $task The task that is being rendered
     * @param UnitInstance $unit The unit that the task is rendered into
     * @param Task\InstanceCompletion[] $completion_list The list of completions, keyed by student
     */
    public function renderTaskInUnit(Task\Instance $task, UnitInstance $unit, $completion_list)
    {

        // Students only:
        // Output whether this item is complete.
        $unit_task_class  = '';
        $unit_task_status = '';
        $unit_task_title  = '';
        $undoable         = false;
        $completable      = false;
        if ($this->readOnly) {
            $unit_task_class = 'icon-countdown incomplete';
            $unit_task_title = 'Incomplete';
            $completion_item = $completion_list[$_SESSION['id']];
            if ($completion_item instanceof Task\InstanceCompletion) {
                switch ($completion_item->getCompletionStatus()) {
                    case Task\InstanceCompletion::COMPLETION_STATUS_COMPLETE:
                        $unit_task_class  = 'icon-drop-box complete';
                        $unit_task_status = 'Approved';
                        $unit_task_title  = 'Approved';
                        break;
                    case Task\InstanceCompletion::COMPLETION_STATUS_REQUIRES_CONFIRMATION:
                        $unit_task_class  = 'icon-rss pending';
                        $unit_task_status = 'Completed';
                        $unit_task_title  = 'Completed';
                        $undoable         = true;
                        break;
                    case Task\InstanceCompletion::COMPLETION_STATUS_INCOMPLETE:
                    default:
                        $completable = true;
                        break;
                }
            } else {
                $completable = true;
            }
        }
        $this->setVar('UNIT_TASK_COMPLETE_CLASS', $unit_task_class);

        $complete_count = count($completion_list);

        /* Prevent division by zero issue when there are no students
		 * in student_list
		 */
        $num = count($this->student_list);

        if ($num) {
            $gradient = round($complete_count / $num * 10);
        } else {
            $gradient = 100;
        }

        $this->setVar(
            array(
                'UNIT_TASK_ID'                    => Scrub::text($task->getId()),
                'UNIT_TASK_NAME'                  => Scrub::text($task->getTitle()),
                'UNIT_TASK_LINK'                  => Scrub::text($task->getLink()),
                'UNIT_TASK_CLASS'                 => Scrub::text($task->getIconClass()),
                'UNIT_TASK_PREVIEW'               => $task->getType() == Task::TYPE_TASK ? 'data-plugin="preview"' : '',
                'UNIT_TASK_TYPE_LABEL'            => Scrub::text($task->getLabel()),
                'UNIT_TASK_SELF_ASSESS'           => ($task->getRequiresConfirmation() ? '' : 'checked'),
                'UNIT_TASK_COMPLETION_COUNT'      => $complete_count,
                'UNIT_TASK_GRADIENT'              => $gradient,
                'UNIT_TASK_COMPLETION_STATUS'     => $unit_task_status,
                'UNIT_TASK_COMPLETION_TITLE'      => $unit_task_title,
                'UNIT_TASK_COMPLETE_CLASS'        => $unit_task_class,
                'UNIT_TASK_SET_STATUS'            => '',
                'unit_task_count_block'           => '',
                'unit_task_completion_block'      => '',
                'unit_task_due_date_block'        => '',
                'unit_task_start_block'           => '',
                'unit_task_actions_block'         => '',
                'unit_task_delete_block'          => '',
                'unit_task_modify_block'          => '',
                'unit_task_delete_form_block'     => '',
                'unit_task_modify_form_block'     => '',
                'unit_task_quiz_results_block'    => '',
                'unit_task_quiz_attempts_block'   => '',
                'unit_task_duework_results_block' => '',
            )
        );

        // Teacher sees the number of students that have completed
        // this task, nobody else does.
        if ($this->readOnly) {
            if ($unit_task_status) {
                $this->parse('unit_task_completion_block', 'unit_task_completion');
            }
            if ($unit->isOpen() && !$task->getRequiresConfirmation()) {
                // Have I, the student, completed this yet?
                // Let me undo if so, otherwise let me complete it.
                if ($undoable) {
                    $this->setVar('UNIT_TASK_SET_STATUS', '/undo');
                } elseif ($completable) {
                    $this->setVar('UNIT_TASK_SET_STATUS', '/complete');
                }
                $this->parse('unit_task_start_block', 'unit_task_start');
            }
            if ($task->getType() == Task::TYPE_ONLINE_ASSESS) {
                $this->setVar('UNIT_QUIZ_ID', Scrub::text($task->getAssessment()->getId()));
                // Am I a student that has previous attempts? Show that block if I do.
                if ($complete_count > 0) {
                    $this->parse('unit_task_quiz_attempts_block', 'unit_task_quiz_attempts');
                }
            }
        } else {
            // Output task modify URL
            $this->setVar('UNIT_TASK_MODIFY_URL', '#');
            if ($task->getType() == Task::TYPE_DUE_WORK || $task->getType() == Task::TYPE_ONLINE_ASSESS) {
                $this->setVar('UNIT_TASK_MODIFY_URL', '/task/instance/modify/' . $task->getId());
            }

            $this->parse('unit_task_count_block', 'unit_task_count');
            $this->parse('unit_task_delete_block', 'unit_task_delete');
            $this->parse('unit_task_delete_form_block', 'unit_task_delete_form');
            $this->parse('unit_task_modify_block', 'unit_task_modify');
            // Only show modify form if this is a simple task.
            // (No assessment or due work)
            if ($task->getType() == Task::TYPE_TASK) {
                $zone = $this->newZone('az-task-' . $task->getId());
                if (strpos($task->getLink(), '/storage/fetch.php') === 0) {
                    $zone->attachFiles(array(substr($task->getLink(), -40)));
                    $this->setVar(
                        array(
                            'UNIT_TASK_ATTACH_DISPLAY' => 'display: block;',
                            'UNIT_TASK_URL_DISPLAY'    => 'display: none;'
                        )
                    );

                } else {
                    $this->setVar(
                        array(
                            'UNIT_TASK_ATTACH_DISPLAY' => 'display: none;',
                            'UNIT_TASK_URL_DISPLAY'    => 'display: block;'
                        )
                    );

                }

                $zone->renderIntoSitePage($this->sitepage, 'ATTACH_ZONE');

                $this->parse('unit_task_modify_form_block', 'unit_task_modify_form');
            } elseif ($task->getType() == Task::TYPE_DUE_WORK) {
                $this->setVar('UNIT_DUEWORK_ID', Scrub::text($task->getSubmissionBox()->getId()));
                $this->parse('unit_task_duework_results_block', 'unit_task_duework_results');
            } elseif ($task->getType() == Task::TYPE_ONLINE_ASSESS) {
                $this->setVar('UNIT_QUIZ_ID', Scrub::text($task->getAssessment()->getId()));
                $this->parse('unit_task_quiz_results_block', 'unit_task_quiz_results');
            }
            $this->parse('unit_task_actions_block', 'unit_task_actions');
        }

        // Set due dates for tasks
        $dueDate = null;
        if ($task->getType() == Task::TYPE_DUE_WORK) {
            $dueDate = $task->getSubmissionBox()->getDueDate();
        } elseif ($task->getType() == Task::TYPE_ONLINE_ASSESS) {
            $dueDate = $task->getAssessment()->getFinish();
        }
        if ($dueDate instanceof Alaress_Date_Format) {
            $this->setVar(
                array(
                    'UNIT_TASK_DUE_DATE'          => Scrub::text($dueDate->getDateOnly()),
                    'UNIT_TASK_RELATIVE_DUE_DATE' => Scrub::text($dueDate->getRelativeTime())
                )
            );
            $this->parse('unit_task_due_date_block', 'unit_task_due_date');
        }
        $this->parse('unit_task_block', 'unit_task', true);
    }


    /**
     * @param string $id
     * @param string $target
     *
     * @return \Schoolbox\Resource\Attachment\Zone
     */
    protected function newZone($id, $target = '')
    {
        $zone = new \Schoolbox\Resource\Attachment\Zone($id);
        $zone->setMulti(false)
            ->setReadOnly(false)
            ->setMimeFilter(null)
            ->setAttachmentsLabel('')
            ->setAttachPrompt('Attach file or folder')
            ->setModalTitle('Select File or Folder')
            ->setThumbnailSize(32);

        if ($target) {
            $zone->renderIntoSitePage($this->sitepage, $target);
        }

        return $zone;
    }
}
