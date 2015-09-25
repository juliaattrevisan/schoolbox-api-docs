<?php
/**
 * Page for creating/modifying a task
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/** @var SitePage $this */

use Schoolbox\Assessment\Course\Task;

$action       = $this->arg[0];
$task_type    = 'task';
$task_type_id = null;
if ($action === 'modify') {
    $task_instance_id = $this->arg[1];
    $task_instance    = Task\InstanceMapper::getInstance()->findById($task_instance_id);
    $task_type        = 'task';
    $task_type_id     = $task_instance->getType();
    // Set the template type.
    // If an assess or due work task, redirect to those edit pages.
    if ($task_type_id === Task::TYPE_TASK) {
        $task_type = 'task';
    }
    elseif ($task_type_id === Task::TYPE_ONLINE_ASSESS) {
        $this->localRedir('/assess/modify/'.$task_instance->getAssessment()->getId());
    }
    elseif ($task_type_id === Task::TYPE_DUE_WORK) {
        $this->localRedir('/submission/createSubmission/modify/'.$task_instance->getSubmissionBox()->getId());
    }
}

// Error out if you haven't redir'd by now
error(2);
