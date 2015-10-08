<?php
/**
 * Form for creating or modifying a task instance.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../include/Bootstrap.php';

use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Folder;

$page = new SitePage();

$course_instance_id = $_POST['unit_id'];
/** @var UnitInstance $course_instance */
$course_instance = UnitInstanceMapper::getInstance()->findById($course_instance_id);
/** @var Component_Instance $component */
$component   = Component_InstanceMapper::getInstance()->findById($course_instance->getComponent()->getId());
$category_id = $component->getIdentifier();
$category    = Folder\Mapper::getInstance()->findById($category_id);

// Lame permission handling
// Current user needs write access to the category this task instance is in.
$group             = new Group();
$permission_denied = false;
if ($group->getPermission($category_id) < WRITE_ACCESS) {
    $permission_denied = true;
} else {
    if ($_POST['action'] === 'delete') {
        Task\InstanceMapper::getInstance()->delete(
            Task\InstanceMapper::getInstance()->findByColumnList(
                array(
                    'id'                      => $_POST['id'],
                    'course_unit_instance_id' => $course_instance_id,
                )
            )
        );
    } elseif ($_POST['action'] === 'reorder') {
        $i                 = 1;
        $update_collection = new \Schoolbox\Collection();
        foreach ($_POST['id'] as $id) {
            // Ensure that this task is within the requested unit
            /** @var Task\Instance $instance */
            $instance = Task\InstanceMapper::getInstance()->findById($id);
            if ($instance->getUnitInstance()->getId() != $course_instance_id) {
                $permission_denied = true;
                break;
            }
            $instance->setOrder($i++);
            $update_collection->add($instance);
        }
        if (!$permission_denied) {
            Task\InstanceMapper::getInstance()->save($update_collection);
        }
    } else {
        $task_instance_id = $_POST['id'];

        $url = '';

        if (!empty($_POST['url'])) {
            // give precedence to URL
            $url = $_POST['url'];

        } else {
            // if the attachment points to a folder, use that for the URL
            $str = \Schoolbox\Resource\Browser\NodeFactory::SBFS . '.folder:';
            if (strpos($_POST['attachment'][0], $str) === 0) {
                $url = '/homepage/' . substr($_POST['attachment'][0], strlen($str));
                unset($_POST['attachment']);
            }

            // ok, so now if we still have an attachment we need to deal with it
            if (is_array($_POST['attachment'])) {
                try {
                    $factory = new CollectorFactory();
                    $file = $factory->build($_POST['attachment'])->getFirst();

                    $url = '/storage/fetch.php?hash=' . $file->getHash();

                } catch (InvalidArgumentException $e) {
                    // noop - leave $url blank
                }
            }
        }


        if ($task_instance_id) {
            /** @var Task\Instance $task_instance */
            $task_instance = Task\InstanceMapper::getInstance()->findOneByColumnList(
                array(
                    'id'                      => $_POST['id'],
                    'course_unit_instance_id' => $course_instance_id,
                )
            );
            $task_instance
                ->setTitle($_POST['title'])
                ->setLink($url)
                ->setRequiresConfirmation(!$_POST['self_assess']);
        } else {
            // Make the task and set various fields
            $task_instance = new Task\Instance();
            $task_instance
                ->setOwner($_SESSION['id'])
                ->setTask(null)
                ->setUnitInstance($course_instance)
                ->setTitle($_POST['title'])
                ->setLink($url)
                ->setRequiresConfirmation(!$_POST['self_assess']);
        }
        Task\InstanceMapper::getInstance()->save($task_instance);
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
    } else {
        // Load the component that this task was added to.
        /** @var Component_Instance[] $component_list */
        $component_id   = $component->getId();
        $component_list = Component_InstanceMapper::getInstance()->findByInstanceId($component_id);
        $component      = $component_list[$component_id];

        if ($component instanceof Component_Instance) {
            // Dump out template contents
            // There ought to be a method which does this.
            $chf = new Component_Homepage_Factory(new \Schoolbox\User(), new SitePage(false));

            /** @var Component_Homepage_CourseOutlineController $controller */
            $controller = $chf->buildComponent($component);
            $controller->setup(
                $category,
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

    header(':', true, $result['error']);
    echo json_encode($result);
    exit;
} else {
    if ($permission_denied) {
        error(6);
    }
    $page->localRedir("/homepage/$category_id/");
}
