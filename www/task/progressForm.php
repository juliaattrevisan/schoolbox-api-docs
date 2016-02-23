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
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

// Post vars:
// student_id
// task_id

$permissionDenied = false;

/** @var Folder $category */
try {
    /** @var Task\Instance $task */
    $task = Task\InstanceMapper::getInstance()->findById($_POST['task_id']);
    Task\InstanceMapper::getInstance()->loadJoinedModel('course_unit_instance', $task);
    UnitInstanceMapper::getInstance()->loadJoinedModel('component', $task->getUnitInstance());
}
catch (Schoolbox\Mapper\Exception $e) {
    $permissionDenied = true;
}

// Check permission on category
$group = new Group();
if ($group->getPermission($task->getUnitInstance()->getComponent()->getIdentifier()) < WRITE_ACCESS) {
    $permissionDenied = true;
}

if (!$permissionDenied) {
    $student = UserMapper::getInstance()->findById($_POST['student_id']);

    // Update the user's completion
    $completion = new Task\InstanceCompletion();
    $completion
        ->setTaskInstance($task)
        ->setStudent($student)
        ->setCompletionDate(new Alaress_Date_Format(new DateTime('now')))
        ->setCompletionStatus($_POST['status'])
    ;

    // Set status, either explicitly or based on what the current status of this task is.
    $newStatus = null;
    if (isset($_POST['status'])) {
        $newStatus = $_POST['status'];
    }
    elseif (isset($_POST['current_status'])) {
        switch ($_POST['current_status']) {
            case Task\InstanceCompletion::COMPLETION_STATUS_INCOMPLETE:
                $newStatus = Task\InstanceCompletion::COMPLETION_STATUS_COMPLETE;
                break;
            case Task\InstanceCompletion::COMPLETION_STATUS_REQUIRES_CONFIRMATION:
                $newStatus = Task\InstanceCompletion::COMPLETION_STATUS_COMPLETE;
                break;
            case Task\InstanceCompletion::COMPLETION_STATUS_COMPLETE:
                $newStatus = Task\InstanceCompletion::COMPLETION_STATUS_INCOMPLETE;
                break;
            default:
                break;
        }
    }
    if (isset($newStatus)) {
        $completion->setCompletionStatus($newStatus);
    }

    Task\InstanceCompletionMapper::getInstance()->save($completion);
}

// Output the new status via JSON
//$page->outputJson($status, $message, $extra);
if ($page->detectAjax()) {
    $result = array(
        'success' => false,
        'error'   => 500,
        'message' => 'Unknown Error',
    );
    if ($permissionDenied) {
        $result['error']   = 403;
        $result['message'] = 'Permission Denied';
    }
    else {
        $result['success'] = true;
        $result['error']   = 200;
        $result['message'] = 'OK';
    }
    if (isset($newStatus)) {
        $result['status'] = $newStatus;
    }
    echo json_encode($result);
    exit;
}
else {
    if ($permissionDenied) {
        error(6);
    }
    $page->backToTheFuture(1);
}