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
use Schoolbox\Resource\CategoryMapper;

$page = new SitePage();

$category_id = $_POST['category'];

// Shitty permission handling
$group = new Group();
$permission_denied = false;
if ($group->getPermission($category_id) < WRITE_ACCESS) {
    $permission_denied = true;
}
else {
    if ($_POST['action'] === 'delete') {
        $task        = TaskMapper::getInstance()->findById($_POST['id']);
        $category_id = $task->getCategory()->getId();
        TaskMapper::getInstance()->delete($task);
    }
    elseif ($_POST['action'] === 'reorder') {
        $i = 1;
        foreach ($_POST['id'] as $id) {
            $task = TaskMapper::getInstance()->findById($id);
            $task->setOrder($i++);
            TaskMapper::getInstance()->save($task);
        }
    }
    else {
        $task_id = $_POST['id'];
        if ($task_id) {
            $task = TaskMapper::getInstance()->findById($task_id);
            $task
                ->setTitle($_POST['title'])
                ->setLink($_POST['link'])
                ->setRequiresConfirmation(!$_POST['self_assess'])
            ;
            $category_id = $task->getCategory()->getId();
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
                ->setLink($_POST['link'])
                ->setRequiresConfirmation(!$_POST['self_assess'])
            ;
        }
        TaskMapper::getInstance()->save($task);
    }
}

// Redirect: depends on request type.
// Ajax: dump out request details, including rendering of component
// Non-Ajax: redirect to the homepage
if ($page->detectAjax()) {
    $result = array(
        'success' => false,
        'error'   => 500,
        'message' => 'Unknown Error',
        'html'    => '',
    );
    if ($permission_denied) {
        $result['error']   = 403;
        $result['message'] = 'Permission Denied';
    }
    else {
        // Load the component that this task was added to.
        /** @var Component_Instance[] $component_list */
        $component_id   = $_POST['component'];
        $component_list = Component_InstanceMapper::getInstance()->findByInstanceId($component_id);
        $component      = $component_list[$component_id];

        if ($component instanceof Component_Instance) {
            // Dump out template contents
            $chf = new Component_Homepage_Factory(new User(), new SitePage(false));

            /** @var Component_Homepage_CourseTaskListController $controller */
            $controller = $chf->buildComponent($component);
            $controller->setup(
                array('id' => $category_id),
                $controller->getInstance()->getConfigArray()
            );

            $target = "component_position_{$controller->getInstance()->getPosition()}";
            $handle = TEMPLATE_content;

            if ($controller->execute() !== false) {
                if ($controller->renderTemplate($handle) !== false) {
                    $controller->parse($target, $handle, true);
                }
            }
            $result['success'] = true;
            $result['error']   = 200;
            $result['message'] = 'OK';
            $result['html']    = $controller->getPageTemplate()->parse($target, $handle);
        }
    }

    echo json_encode($result);
    exit;
}
else {
    if ($permission_denied) {
        error(6);
    }
    $page->localRedir("/homepage/$category_id/");
}
