<?php
/**
 * Task list course component for heads of curriculum.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use \Schoolbox\Assessment\Course\Task;
use \Schoolbox\Assessment\Course\TaskMapper;

class Component_Homepage_CourseTaskListController extends Component_Homepage_ControllerAbstract {

    /**
     * @var Group
     */
    protected $group_handler;

    /**
     * @var Task[]
     */
    protected $task_list;

    /**
     * {@inheritdoc}
     */
    public function execute() {
        $this->group_handler = new Group();
        $this->task_list     = TaskMapper::getInstance()->findByCategory($this->getContext('id'));
    }

    /**
     * {@inheritdoc}
     */
    public function renderTemplate($templateHandle) {
        if (!parent::loadTemplate($templateHandle, 'course_task_list')) {
            return false;
        }

        // Permission denied if user doesn't have write access to category
        if ($this->group_handler->getPermission($this->getContext('id')) < WRITE_ACCESS) {
            return false;
        }

        $this->parseConfig();

        return true;
    }

}