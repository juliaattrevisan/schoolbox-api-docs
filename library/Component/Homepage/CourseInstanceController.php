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

class Component_Homepage_CourseInstanceController extends Component_Homepage_ControllerAbstract {

    protected $unit_list = array();

    /**
     * {@inheritdoc}
     */
    public function execute() {
        // Test list
        $this->unit_list = array(
            array(
                'id'        => 3,
                'name'      => 'Dumb task list',
                'task_list' => array(
                    array(
                        'id'   => 6,
                        'name' => 'Dumb task 1',
                    ),
                    array(
                        'id'   => 7,
                        'name' => 'Dumb task 2',
                    ),
                    array(
                        'id'   => 1,
                        'name' => 'Dumb task 3',
                    ),
                ),
            ),
            array(
                'id'        => 6,
                'name'      => 'Smart task list',
                'task_list' => array(
                    array(
                        'id'   => 61,
                        'name' => 'Smart task 1',
                    ),
                    array(
                        'id'   => 71,
                        'name' => 'Smart task 2',
                    ),
                    array(
                        'id'   => 11,
                        'name' => 'Smart task 3',
                    ),
                ),
            ),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function renderTemplate($templateHandle) {
        if (!parent::loadTemplate($templateHandle, 'courseInstance')) {
            return false;
        }

        $this->setVar('COMPONENT_NAME', $this->getConfig('componentname'));

        $this->setBlock($templateHandle,  'course_selector', 'course_selector_block');
        $this->setBlock($templateHandle,  'unit_list',       'unit_list_block');
        $this->setBlock('unit_list',      'unit',            'unit_block');
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
                        'UNIT_ID'   => $unit['id'],
                        'UNIT_NAME' => $unit['name'],
                    )
                );

                // Output list of tasks within units
                if (!empty($unit['task_list'])) {

                    $this->setVar('unit_task_block', '');
                    foreach ($unit['task_list'] as $task) {
                        $this->setVar(
                            array(
                                'UNIT_TASK_ID'   => $task['id'],
                                'UNIT_TASK_NAME' => $task['name'],
                            )
                        );
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