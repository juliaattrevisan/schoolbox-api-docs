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

class Component_Homepage_CourseInstanceController extends Component_Homepage_ControllerAbstract {

    /**
     * @var Collection|UnitInstance[]
     */
    protected $unit_list;

    /**
     * @var array
     */
    protected $student_list;

    /**
     * {@inheritdoc}
     */
    public function execute() {
        // Get the list of students for this class (in which method?)
        $code = $this->getContext('code');
        if ($code && Config::get('external_enable')) {
            $class_handler = new External_Class();
            $this->student_list = $class_handler->getStudents($code);
        }
        else {
            $group_handler = new Group();
            $this->student_list = $group_handler->getMemberList($this->getContext('id'));
        }

        // Get the list of units for this course, as well as who has actually
        // completed each unit.
        $this->unit_list = UnitInstanceMapper::getInstance()->findForComponent($this->getInstance());
    }

    /**
     * {@inheritdoc}
     */
    public function renderTemplate($templateHandle) {
        if (!parent::loadTemplate($templateHandle, 'courseInstance')) {
            return false;
        }

        $this->parseConfig();

        $this->setVar('COMPONENT_NAME',  $this->getConfig('componentname'));
        $this->setVar('COURSE_HOMEPAGE', str_replace('/homepage/', '', Config::get('course_homepage')));

        $this->setBlock($templateHandle,  'course_selector', 'course_selector_block');
        $this->setBlock($templateHandle,  'unit_list',       'unit_list_block');
        $this->setBlock('unit_list',      'unit',            'unit_block');
        $this->setBlock('unit',           'unit_completion', 'unit_completion_block');
        $this->setBlock('unit',           'unit_task',       'unit_task_block');

        $this->setVar('course_selector_block', '');
        $this->setVar('unit_list_block',       '');

        // If courseHomepage is set, a course has been selected.
        // This means that the units and tasks that were imported are displayed.
        // If it isn't, then a selector is displayed (if user has write access)
        if ($this->getConfig('courseHomepage')) {
            // iterate the list of homepages
            $this->parse('unit_list_block', 'unit_list');

            // unit list will be a collection of Unit models
            $this->setVar('unit_block', '');
            $now = new DateTime('now');
            $i = 0;

            $student_count = count($this->student_list);
            foreach ($this->unit_list as $unit) {
                $this->setVar(
                    array(
                        'UNIT_ID'               => Scrub::text($unit->getId()),
                        'UNIT_NAME'             => Scrub::text($unit->getTitle()),
                        'UNIT_OPEN_DATE'        => Scrub::text($unit->getOpenDate()->getDatabaseDate()),
                        'UNIT_DUE_DATE'         => Scrub::text($unit->getDueDate()->getDatabaseDate()),
                        'UNIT_BACKGROUND'       => ($i++%2 === 1 ? 'dark' : ''),
                        'UNIT_STUDENT_COUNT'    => $student_count,
                        'unit_task_block'       => '',
                        'unit_completion_block' => '',
                    )
                );

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

                // Output list of tasks within units
                foreach ($unit->getTasks() as $task) {
                    $complete_count = 0;
                    $gradient       = round($complete_count / $student_count * 10);
                    $this->setVar(
                        array(
                            'UNIT_TASK_ID'               => Scrub::text($task->getId()),
                            'UNIT_TASK_NAME'             => Scrub::text($task->getTitle()),
                            'UNIT_TASK_CLASS'            => Scrub::text($task->getIconClass()),
                            'UNIT_TASK_TYPE_LABEL'       => Scrub::text($task->getLabel()),
                            'UNIT_TASK_COMPLETION_COUNT' => $complete_count,
                            'UNIT_TASK_GRADIENT'         => $gradient,
                        )
                    );
                    // What's the gradient?
                    $this->parse('unit_task_block', 'unit_task', true);
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