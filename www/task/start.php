<?php
/**
 * Page for starting a task.
 *
 * Has a different action depending on the task type:
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;

/** @var SitePage $this */

// Get task
/** @var Task\Instance $task */
$task = null;
try {
    // This should populate unit instance, task, component and all that too.
    $task = Task\InstanceMapper::getInstance()->findById($this->arg[0]);
} catch (\Schoolbox\Mapper\Exception $e) {
    error(2);
}




// Check permissions
/** @var UnitInstance $unit_instance */
$unit_instance = UnitInstanceMapper::getInstance()->findById($task->getUnitInstance()->getId());
// * Ensure that user is in this class
/** @var Component_Instance $component */
$component_id   = $unit_instance->getComponent()->getId();
$component_list = Component_InstanceMapper::getInstance()->findByInstanceId($component_id);
$component      = $component_list[$component_id];

$group_handler = new Group();
if (($permission = $group_handler->getPermission($component->getIdentifier())) < READ_ACCESS) {
    error(19);
}
// * Ensure task is open
if (!$unit_instance->isOpen() && $permission < WRITE_ACCESS) {
    error(2);
}

// Get student's most recent completion.

// What type of task is this?
// Online assessment or due work? Redirect to that thing
// Task: mark it as done or that it requires confirmation.
// Then if there's a link, point to it.
switch ($task->getType()) {
    case Task::TYPE_DUE_WORK:
        // redirect to due work item
        // When the due work item is complete, mark task as complete.
        SitePage::localRedir(
            '/submission/submissionHistory/'.$task->getSubmissionBox()->getId().'/'.$_SESSION['id']
        );
        break;
    case Task::TYPE_ONLINE_ASSESS:
        // redirect to online assessment item
        // When it's complete, the mark task is marked as complete.
        SitePage::localRedir(
            '/assess/intro/'.$task->getAssessment()->getId().'/'
        );
        break;
    default:
        // Mark this item as complete.
        // Redirect to the link if there is one, otherwise redirect back to the
        // homepage this is on.
        if (!$task->getRequiresConfirmation()) {
            Task\InstanceCompletionMapper::getInstance()->markAsCompleted(
                $task->getId(), $_SESSION['id']
            );
        }

        $link = $task->getLink();
        if (strlen($link) > 0) {
            header('Location: '.$link);
            exit;
        } else {
            SitePage::localRedir('/homepage/'.$component->getIdentifier().'/');
        }
        break;
}
