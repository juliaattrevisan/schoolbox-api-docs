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

/** @var Schoolbox\User $user */
$user = $_SESSION['user'];

// This should be a POST if AJAX
$isAjax = SitePage::detectAjax();
if ($isAjax && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST requests only']);
    header(':', true, 400);
    exit;
}

// Am I a student undoing their task completion?
// I can only undo self-assessable items, all others will have no effect.
$isUndo     = isset($this->arg[1]) && $this->arg[1] == 'undo';
$isComplete = isset($this->arg[1]) && $this->arg[1] == 'complete';

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

// Get student's most recent completion.

// What type of task is this?
// Online assessment or due work? Redirect to that thing
// Task: mark it as done or that it requires confirmation.
// Then if there's a link, point to it.
$url      = '/homepage/' . $component->getIdentifier() . '/#component' . $component_id;
$isLocal  = true;
$redirect = false;
switch ($task->getType()) {
    case Task::TYPE_DUE_WORK:
        // redirect to due work item
        // When the due work item is complete, mark task as complete.
        $url      = '/submission/submissionHistory/'.$task->getSubmissionBox()->getId().'/'.$user->getId();
        $redirect = true;
        break;

    case Task::TYPE_ONLINE_ASSESS:
        // redirect to online assessment item
        // When it's complete, the mark task is marked as complete.
        $url      = '/assess/intro/'.$task->getAssessment()->getId().'/';
        $redirect = true;
        break;

    default:
        // Mark this item as complete.
        // Redirect to the link if there is one, otherwise redirect back to the
        // homepage this is on.
        $neverRedirect = false;
        if ($unit_instance->isOpen() && !$task->getRequiresConfirmation()) {
            // Mark/unmark are never redirects.
            if ($isUndo) {
                $neverRedirect = true;
                Task\CompletionMapper::getInstance()->unmarkAsCompleted($task->getId(), $user->getId());
            } elseif ($isComplete) {
                $neverRedirect = true;
                Task\CompletionMapper::getInstance()->markAsCompleted($task->getId(), $user->getId());
            }
        }

        $link = $task->getLink();
        if (strlen($link) > 0) {
            $url      = $link;
            $isLocal  = false;
            $redirect = !$neverRedirect;
        }
        break;
}

if ($isAjax) {
    if ($redirect) {
        echo json_encode([
            'type'     => 'redirect',
            'fragment' => $url,
        ]);
    } else {
        // Spit out component HTML and let the calling page deal with it
        $controller = new Component_Homepage_CourseOutlineController($component, $_SESSION['user'], $this);
        $renderer   = new Component_Renderer();
        ob_start();
        $renderer->renderHomepageComponent(
            Schoolbox\Resource\Folder\Mapper::getInstance()->findById($unit_instance->getCategory()->getId()),
            $controller
        );
        echo json_encode([
            'type'     => 'fragment',
            'fragment' => ob_get_clean(),
        ]);
    }
} else {
    if ($isLocal) {
        SitePage::localRedir($url);
    } else {
        header('Location: '.$url);
    }
}

// Should I have not exited yet: dump the component's content into JSON
// and let the calling script deal with it
exit;
