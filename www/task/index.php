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
use Schoolbox\Assessment\Course\TaskMapper;

// Wrangle arguments
// Possible argument formats: /modify/{id}, /create/{category_id}/{type}/
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
}
elseif ($action === 'modify') {
    $task_id      = $this->arg[1];
    $task         = TaskMapper::getInstance()->findById($task_id);
    $task_type    = 'task';
    $task_type_id = $task->getType();
    // Set the template type.
    // If an assess or due work task, redirect to those edit pages.
    if ($task_type_id === Task::TYPE_ONLINE_ASSESS) {
        $this->localRedir('/assess/modify/'.$task->getAssessment()->getId());
    }
    elseif ($task_type_id === Task::TYPE_DUE_WORK) {
        $this->localRedir('/submission/createSubmission/modify/'.$task->getSubmissionBox()->getId());
    }
}
// Error out if you haven't redir'd by now
error(2);
