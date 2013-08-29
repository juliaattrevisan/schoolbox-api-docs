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
// Possible argument formats: /modify/{id}, /create/{category_id}/{type}/
// call this file index.php
$action       = $this->arg[0];
$task_type    = 'task';
$task_type_id = null;
if ($action === 'create') {
    $category_id = $this->arg[1];
    $category    = CategoryMapper::getInstance()->findById($category_id);
    if (isset($this->arg[2])) {
        switch ($this->arg[2]) {
            case 'assessment':
                $this->localRedir('/assess/create/'.$category_id.'/task/');
                break;
            case 'due_work':
                $this->localRedir('/submission/createSubmission/new/'.$category_id.'/task/');
                break;
            case 'task':
            default:
                break;
        }
    }
    $task = new Task();
    $task->setCategory($category);
}
elseif ($action === 'modify') {
    $task_id      = $this->arg[1];
    $task         = TaskMapper::getInstance()->findById($task_id);
    $task_type    = 'task';
    $task_type_id = $task->getType();
    // Set the template type.
    // If an assess or due work task, redirect to those edit pages.
    if ($task_type_id === Task::TYPE_TASK) {
        $task_type = 'task';
    }
    elseif ($task_type_id === Task::TYPE_ONLINE_ASSESS) {
        $this->localRedir('/assess/modify/'.$task->getAssessment()->getId());
    }
    elseif ($task_type_id === Task::TYPE_DUE_WORK) {
        $this->localRedir('/submission/createSubmission/modify/'.$task->getSubmissionBox()->getId());
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
$this->setBlock(TEMPLATE_content, 'create_button', 'create_button_block');
$this->setBlock(TEMPLATE_content, 'modify_button', 'modify_button_block');

$this->setVar('create_header_block', '');
$this->setVar('modify_header_block', '');
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

$this->setVar(
    array(
        'TASK_ID'            => $task->getId(),
        'TASK_CATEGORY'      => $task->getCategory()->getId(),
        'TASK_TYPE'          => $task_type,
        'TASK_LINK'          => $task->getLink(),
        'TASK_TITLE'         => $task->getTitle(),
        'TASK_CONF_REQUIRED' => ($task->getRequiresConfirmation() ? 'checked' : ''),
    )
);
