<?php
/**
 * Save form for a student's status.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../include/Bootstrap.php';
$page = new SitePage();

use Schoolbox\Assessment\Course\Task;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;

// Post vars:
// student_id
// task_id

/** @var Category $category */
try {
    /** @var Task\Instance $task */
    $task = Task\InstanceMapper::getInstance()->findById($_POST['task_id']);
    Task\InstanceMapper::getInstance()->loadJoinedModel('course_unit_instance', $task);
    UnitInstanceMapper::getInstance()->loadJoinedModel('component', $task->getUnitInstance());
}
catch (Schoolbox\Mapper\Exception $e) {
    error(6);
}

// Check permission on category
$group = new Group();
if ($group->getPermission($task->getUnitInstance()->getComponent()->getIdentifier()) < WRITE_ACCESS) {
    error(6);
}

$student = UserMapper::getInstance()->findById($_POST['student_id']);

// Update the user's completion
$completion = new Task\InstanceCompletion();
$completion
    ->setTaskInstance($task)
    ->setStudent($student)
    ->setCompletionDate(new Alaress_Date_Format(new DateTime('now')))
    ->setCompletionStatus($_POST['status'])
;
Task\InstanceCompletionMapper::getInstance()->save($completion);
