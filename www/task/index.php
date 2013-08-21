<?php
/**
 * Page for creating/modifying a task
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/** @var SitePage $this */

use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskType;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Assessment\Course\TaskTypeMapper;

// Wrangle arguments
// Possible argument formats: /modify/{id}, /create/{category_id}/type/
// call this file index.php
$action       = $this->arg[0];
$task_type    = 'task';
$task_type_id = null;
if ($action === 'create') {
    $category_id = $this->arg[1];
    $category    = CategoryMapper::getInstance()->findById($category_id);
    if (isset($this->arg[2])) {
        switch ($this->arg[2]) {
            case 'link':
                $task_type    = 'link';
                $task_type_id = TaskType::TYPE_LINK;
                break;
            default:
                break;
        }
    }
    $task = new Task();
    $task->setCategory($category);
    if ($task_type_id) {
        $task->setType(TaskTypeMapper::getInstance()->findById($task_type_id));
    }
}
elseif ($action === 'modify') {
    $task_id      = $this->arg[1];
    $task         = TaskMapper::getInstance()->findById($task_id);
    $task_type    = 'task';
    $task_type_id = $task->getType()->getId();
    if ($task_type_id === TaskType::TYPE_LINK) {
        $task_type = 'link';
    }
}
else {
    error(2);
}

/** @var Task $task */
$category_id = $task->getCategory()->getId();

// Shitty permission handling
$group = new Group();
if ($group->getPermission($category_id) < WRITE_ACCESS) {
    error(6);
}

// Shitty block parsing
$this->setFile(TEMPLATE_content, "task/index.html");
$this->setBlock(TEMPLATE_content, 'create_header', 'create_header_block');
$this->setBlock(TEMPLATE_content, 'modify_header', 'modify_header_block');
$this->setBlock(TEMPLATE_content, 'link_input',    'link_input_block');
$this->setBlock(TEMPLATE_content, 'task_input',    'task_input_block');
$this->setBlock(TEMPLATE_content, 'create_button', 'create_button_block');
$this->setBlock(TEMPLATE_content, 'modify_button', 'modify_button_block');

$this->setVar('create_header_block', '');
$this->setVar('modify_header_block', '');
$this->setVar('link_input_block',    '');
$this->setVar('task_input_block',    '');
$this->setVar('create_button_block', '');
$this->setVar('modify_button_block', '');

if ($task->getId()) {
    $this->parse('modify_header_block', 'modify_header');
    $this->parse('modify_button_block', 'modify_button');
}
else {
    $this->parse('create_header_block', 'create_header');
    $this->parse('create_button_block', 'create_button');
}

// Show only this task type's inputs
$this->parse("{$task_type}_input_block", "{$task_type}_input");

$this->setVar(
    array(
        'TASK_ID'       => $task->getId(),
        'TASK_CATEGORY' => $task->getCategory()->getId(),
        'TASK_TYPE'     => $task_type,
        'TASK_LINK'     => $task->getLink(),
        'TASK_TITLE'    => $task->getTitle(),
        'TASK_CHECK_ASSESS_SELF'    => ($task_type_id === TaskType::TYPE_STUDENT_ASSESS ? 'checked' : ''),
        'TASK_CHECK_ASSESS_TEACHER' => ($task_type_id === TaskType::TYPE_TEACHER_ASSESS ? 'checked' : ''),
    )
);
