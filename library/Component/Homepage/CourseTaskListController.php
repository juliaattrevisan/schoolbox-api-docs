<?php
/**
 * Task list course component for heads of curriculum.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;

class Component_Homepage_CourseTaskListController extends Component_Homepage_ControllerAbstract {

    /**
     * @var Group
     */
    protected $group_handler;

    /**
     * @var Category
     */
    protected $category;

    /**
     * @var Task[]
     */
    protected $task_list;

    /**
     * {@inheritdoc}
     */
    public function execute() {
        $this->group_handler   = new Group();
        $this->category        = CategoryMapper::getInstance()->findById($this->getContext('id'));
        $this->task_list       = TaskMapper::getInstance()->findByCategory($this->category);
    }

    /**
     * {@inheritdoc}
     */
    public function renderTemplate($templateHandle) {
        if (!parent::loadTemplate($templateHandle, 'courseTaskList')) {
            return false;
        }

        // Permission denied if user doesn't have write access to category
        if ($this->group_handler->getPermission($this->getContext('id')) < WRITE_ACCESS) {
            return false;
        }

        $this->parseConfig();

        $this->setBlock($templateHandle, 'task', 'task_block');
        $this->setBlock($templateHandle, 'category_breadcrumb', 'category_breadcrumb_block');
        $this->setBlock('task', 'edit_task', 'edit_task_block');
        $this->setBlock('task', 'edit_task_form', 'edit_task_form_block');
        $this->setBlock('edit_task_form', 'link_input', 'link_input_block');
        $this->setBlock('edit_task_form', 'task_input', 'task_input_block');

        $this->setVar(array(
            'SESSION_NAME' => session_name(),
            'SESSION_ID'   => session_id(),
            'COMPONENT_NAME' => $this->getConfig('componentname'),
        ));

        // Start shitting out tasks into the template
        $this->setVar('category_breadcrumb_block', '');
        foreach (CategoryMapper::getInstance()->findParents($this->category) as $parent) {
            /** @var Category $parent */
            $this->setVar('PARENT_CATEGORY_ID',   $parent->getId());
            $this->setVar('PARENT_CATEGORY_NAME', $parent->getName());
            $this->parse('category_breadcrumb_block', 'category_breadcrumb', true);
        }
        $this->setVar('task_block', '');

        $i = 0;
        foreach ($this->task_list as $task) {
            $this->setVar(
                array(
                    'TASK_ID'         => $task->getId(),
                    'TASK_TITLE'      => Scrub::text($task->getTitle()),
                    'TASK_TYPE'       => '',
                    'TASK_TYPE_LABEL' => Scrub::text($task->getType()->getLabel()),
                    'TASK_CLASS'      => Scrub::text($task->getType()->getIconClass()),
                    'TASK_LINK'       => Scrub::text($task->getLink()),
                    'DARK'            => ($i++%2 === 1 ? 'dark' : ''),
                )
            );
            $this->parse('edit_task_block', 'edit_task');

            $task_type = $task->getType()->getId();

            $this->setVar('edit_task_form_block', '');
            $this->setVar('link_input_block', '');
            $this->setVar('task_input_block', '');
            if ($task_type === TaskType::TYPE_LINK) {
                $this->setVar('TASK_TYPE', 'link');
                $this->parse('link_input_block', 'link_input');
                $this->parse('edit_task_form_block', 'edit_task_form');
            }
            elseif ($task_type === TaskType::TYPE_STUDENT_ASSESS) {
                $this->setVar(array(
                    'TASK_CHECK_ASSESS_SELF'    => 'checked',
                    'TASK_CHECK_ASSESS_TEACHER' => '',
                    'TASK_TYPE'                 => 'task',
                ));
                $this->parse('task_input_block', 'task_input');
                $this->parse('edit_task_form_block', 'edit_task_form');
            }
            elseif ($task_type === TaskType::TYPE_TEACHER_ASSESS) {
                $this->setVar(array(
                    'TASK_CHECK_ASSESS_SELF'    => '',
                    'TASK_CHECK_ASSESS_TEACHER' => 'checked',
                    'TASK_TYPE'                 => 'task',
                ));
                $this->parse('task_input_block', 'task_input');
                $this->parse('edit_task_form_block', 'edit_task_form');
            }
            elseif ($task_type === TaskType::TYPE_DUE_WORK) {
                $this->setVar('TASK_TYPE', 'due_work');
            }
            elseif ($task_type === TaskType::TYPE_ONLINE_ASSESS) {
                $this->setVar('TASK_TYPE', 'assessment');
            }

            $this->parse('task_block', 'task', true);
        }

        return true;
    }

}