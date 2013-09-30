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
     * {@inheritdoc}
     */
    public function execute() {
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
            foreach ($this->unit_list as $unit) {
                $this->setVar(
                    array(
                        'UNIT_ID'               => $unit->getId(),
                        'UNIT_NAME'             => $unit->getTitle(),
                        'UNIT_OPEN_DATE'        => $unit->getOpenDate()->getDatabaseDate(),
                        'UNIT_DUE_DATE'         => $unit->getDueDate()->getDatabaseDate(),
                        'unit_task_block'       => '',
                        'unit_completion_block' => '',
                    )
                );

                // Output due date and urgency
                // If due date isn't reached, don't display at all, instead only
                // show how long until this unit opens.
                $completion_status = null;
                $rel_time          = null;
                $urgency           = null;
                $now               = new DateTime('now');
                if ($unit->isBeforeOpenDate()) {
                    $completion_status = 'opens';
                    $rel_time          = $unit->getOpenDate();
                }
                // If student has completed all tasks within this course:
                // show when they completed it
                elseif ($unit->isOpen()) {
                    $completion_status = 'due';
                    $rel_time          = $unit->getDueDate();

                    // How many days until this item is due?
                    $diff = $now->diff(new DateTime($unit->getDueDate()->getDatabase()));
                    $days = $diff->days;
                    if ($diff->invert) {
                        $days = 0;
                    }
                    $urgency = min(10, max(0, $days));
                }
                else {
                    $completion_status = 'closed';
                    $rel_time          = $unit->getDueDate();
                    $urgency           = 0;
                }
                $rel_time = new Alaress_Date_Relative($rel_time);
                $this->setVar('UNIT_COMPLETION_STATUS', $completion_status);
                $this->setVar('UNIT_RELATIVE_DATE',     $rel_time->getRelativeTime());
                $this->setVar('UNIT_UPCOMING_URGENCY',  $urgency);
                $this->parse('unit_completion_block', 'unit_completion');

                // Output list of tasks within units
                foreach ($unit->getTasks() as $task) {
                    $this->setVar(
                        array(
                            'UNIT_TASK_ID'   => $task->getId(),
                            'UNIT_TASK_NAME' => $task->getTitle(),
                        )
                    );
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