<?php
/**
 * Form for creating or modifying a task
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
if ($task_id) {
    $task = TaskMapper::getInstance()->findById($task_id);
    $task->setTitle($_POST['title']);

    // Modify field values based on the type of this item.
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
}
else {
    // Make the task and set various fields
    $category_id = $_POST['category'];
    $category    = CategoryMapper::getInstance()->findById($category_id);
    $task        = new Task();
    $task
        ->setOwner($_SESSION['id'])
        ->setTitle($_POST['title'])
        ->setCategory($category)
    ;

    // Set the task type based on what was passed in
    $task_type = null;
    switch ($_POST['type']) {
        case 'task':
            switch ($_POST['assess_method']) {
                case 'self':
                    $task_type = TaskType::TYPE_STUDENT_ASSESS;
                    break;
                case 'teacher':
                    $task_type = TaskType::TYPE_TEACHER_ASSESS;
                    break;
            }
            break;
        case 'link':
            $task_type = TaskType::TYPE_LINK;
            break;
        default:
            break;
    }
    if (!isset($task_type)) {
        error(2);
    }
    $task->setType($task_type);
    if ($task_type === TaskType::TYPE_LINK) {
        $link = trim($_POST['link']);
        if (strlen($link) === 0) {
            error(2);
        }
        $task->setLink($link);
    }
}

// Shitty permission handling
$group = new Group();
if ($group->getPermission($task->getCategory()->getId()) < WRITE_ACCESS) {
    error(6);
}

TaskMapper::getInstance()->save($task);

// Redirect: depends on request type.
// Ajax: dump out a request type
// Non-Ajax: redirect to the homepage
$page->localRedir("/homepage/{$task->getCategory()->getId()}/");
