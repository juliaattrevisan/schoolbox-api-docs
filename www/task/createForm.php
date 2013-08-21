<?php
/**
 * Form for creating a task
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

$category_id = $_POST['category'];
$category    = CategoryMapper::getInstance()->findById($category_id);

// Shitty permission handling
$group = new Group();
if ($group->getPermission($category_id) < WRITE_ACCESS) {
    error(6);
}

// Make the task and set various fields
$task = new Task();
$task
    ->setOwner($_SESSION['id'])
    ->setTitle($_POST['title'])
    ->setCategory($category)
;

// Task type: either task (from there, either student or teacher task) or link
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

TaskMapper::getInstance()->save($task);

// Redirect: depends on request type.
// Ajax: dump out a request type
// Non-Ajax: redirect to the homepage
$page->localRedir("/homepage/$category_id/");
