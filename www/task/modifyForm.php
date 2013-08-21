<?php
/**
 * Form for modifying a task
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../include/Bootstrap.php';

use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Assessment\Course\TaskType;
use Schoolbox\Resource\CategoryMapper;

$page = new SitePage();

$task_id = $_POST['id'];
$task    = TaskMapper::getInstance()->findById($task_id);

// Shitty permission handling
$group = new Group();
if ($group->getPermission($task->getCategory()->getId()) < WRITE_ACCESS) {
    error(6);
}

// Make the task and set various fields
$task->setTitle($_POST['title']);

// Task type: either task (from there, either student or teacher task) or link
$task_type = null;
switch ($task->getType()->getId()) {
    case TaskType::TYPE_LINK:
        $task->setLink($_POST['link']);
        break;
    case TaskType::TYPE_TEACHER_ASSESS:
    case TaskType::TYPE_STUDENT_ASSESS:
        switch ($_POST['assess_method']) {
            case 'self':
                $task->setType(TaskType::TYPE_STUDENT_ASSESS);
                break;
            case 'teacher':
                $task->setType(TaskType::TYPE_TEACHER_ASSESS);
                break;
        }
        break;
    default:
        break;
}

TaskMapper::getInstance()->save($task);

// Redirect: depends on request type.
// Ajax: dump out a request type
// Non-Ajax: redirect to the homepage
$page->localRedir("/homepage/{$task->getCategory()->getId()}/");
