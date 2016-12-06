<?php

/*
 *
 * redirect to Redirection to 16.5 url
 *
 */

if ($this->arg[0] == 'modify' && is_numeric($this->arg[1])) {
    SitePage::localRedir('/learning/assessments/'.$this->arg[1].'/modify/', null, 301);
}
elseif ($this->arg[0] == 'create' && is_numeric($this->arg[1])) {
    SitePage::localRedir('/learning/folders/'.$this->arg[1].'/assessments/new?type=task', null, 301);
}
else {
    SitePage::pageNotFound();
}

/*
 * Old code
 */



/**
 * Page for creating/modifying a task
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/** @var SitePage $this */

use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Learning\Assessment\Redirect;

// use a return URL if passed, otherwise use the referrer (which in theory could be blank)
$return     = $_GET[Redirect::RETURN_URL] ? $_GET[Redirect::RETURN_URL] : Redirect::encode((string)parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH));
$returnUrl = $return ? "?".Redirect::RETURN_URL."=$return" : '';

// Wrangle arguments
// Possible argument formats: /modify/{id}, /create/{category_id}/{type}/
$action       = $this->arg[0];
$task_type    = 'task';
$task_type_id = null;
if ($action === 'create') {
    $category_id = $this->arg[1];
    $category    = FolderMapper::getInstance()->findById($category_id);
    if (isset($this->arg[2])) {
        switch ($this->arg[2]) {
            case 'assessment':
                $this->localRedir('/assess/create/'.$category_id.'/task/' . $returnUrl);
                break;
            case 'due_work':
                $this->localRedir('/submission/createSubmission/new/'.$category_id.'/task/' . $returnUrl);
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
        $this->localRedir('/assess/summary/'.$task->getQuiz()->getId() . $returnUrl);
    }
    elseif ($task_type_id === Task::TYPE_DUE_WORK) {
        $this->localRedir('/submission/createSubmission/modify/'.$task->getSubmissionBox()->getId() . $returnUrl);
    }
}
// Error out if you haven't redir'd by now
error(2);
