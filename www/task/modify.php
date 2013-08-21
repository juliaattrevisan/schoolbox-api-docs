<?php
/**
 * Page for modifying a task
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/** @var SitePage $this */

use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Assessment\Course\TaskType;

// Wrangle arguments
$task_id = $this->arg[0];
$task    = TaskMapper::getInstance()->findById($task_id);

// Shitty permission handling
$group = new Group();
if ($group->getPermission($task->getCategory()->getId()) < WRITE_ACCESS) {
    error(6);
}

$task_type = 'task';
if ($task->getType() === TaskType::TYPE_LINK) {
    $task_type = 'link';
}
$task_type_id = $task->getType()->getId();

$this->setFile(TEMPLATE_content, "task/modify/$task_type.html");
$this->setVar(
    array(
        'TASK_ID'       => $task_id,
        'TASK_CATEGORY' => $task->getCategory()->getId(),
        'TASK_TYPE'     => $task_type,
        'TASK_LINK'     => $task->getLink(),
        'TASK_TITLE'    => $task->getTitle(),
        'TASK_CHECK_ASSESS_SELF'    => ($task_type_id === TaskType::TYPE_STUDENT_ASSESS ? 'checked' : ''),
        'TASK_CHECK_ASSESS_TEACHER' => ($task_type_id === TaskType::TYPE_TEACHER_ASSESS ? 'checked' : ''),
    )
);
