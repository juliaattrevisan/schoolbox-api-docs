<?php
/**
 * Form for importing a course into a component
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../include/Bootstrap.php';

use Schoolbox\Collection;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Assessment\Course\Task;

$site_page = new SitePage();

$component_id = $_POST['component'];
if (empty($component_id)) {
    error(2);
}

//list($component_id) = $this->arg;
//$renderer = new Schoolbox\Assessment\Course\Renderer();
//$renderer->setupFromComponent($component_id);
//$renderer->save($homepage);
//$renderer->redirectToHomepage();

/** @var Component_Instance $component */
$component = Component_InstanceMapper::getInstance()->findById($component_id);
$homepage  = Schoolbox\Resource\Folder\Mapper::getInstance()->findById($component->getIdentifier());

// Test permissions:
// Write on $homepage_id
$group = new Group();
$permission_denied = false;
if ($group->getPermission($component->getIdentifier()) < WRITE_ACCESS) {
    $permission_denied = true;
}

// Save each set of units.
if (!$permission_denied) {
    /** @var Collection|UnitInstance[] $saved_list */
    /** @var Collection|UnitInstance[] $deleted_list */
    $saved_list   = new Collection();
    $deleted_list = new Collection();
    $undeletedIds = [];
    if ($_POST['action'] === 'delete') {
        // Add only the course instances in $_POST[id] to the list to be deleted.
        $deleted_list = UnitInstanceMapper::getInstance()->findByColumnList(
            array(
                'id'           => $_POST['id'],
                'component_id' => $component_id,
            )
        );
    }
    else {
        foreach ($_POST['unit'] as $unit_data) {
            // Figure out which unit instance ID (if any) is attached
            $unit_id   = $unit_data['unit_id'];
            $import    = !empty($unit_data['import']);
            $deleted   = false;
            $open_date = Alaress_Date_Format::newLocalShort($unit_data['open_date']);
            $due_date  = Alaress_Date_Format::newLocalShort($unit_data['due_date']);
            try {
                /** @var UnitInstance $course_instance */
                // Is there a unit instance? If so fetch it, otherwise fetch by unit
                // category and component.
                if (!empty($unit_data['unit_instance_id'])) {
                    $course_instance = UnitInstanceMapper::getInstance()->findById($unit_data['unit_instance_id']);
                }
                else {
                    // Try to restore a course before creating a new one.
                    try {
                        $course_instance = UnitInstanceMapper::getInstance()->findOneByColumnList(
                            array(
                                'component_id'     => $component_id,
                                'unit_category_id' => $unit_id,
                            )
                        );
                    } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
                        // Deleted? Attempt to restore it
                        $course_instance = $e->getDeletedObject();
                        $course_instance->setDeleted(null);
                        $undeletedIds[$course_instance->getId()] = true;
                    }
                }
                if ($import) {
                    $course_instance
                        ->setOpenDate($open_date)
                        ->setDueDate($due_date)
                    ;
                    $saved_list->add($course_instance);
                }
                else {
                    $deleted_list->add($course_instance);
                }
            }
            catch (\Schoolbox\Mapper\Exception $e) {
                if ($import) {
                    // This is an item that wasn't in the list before, add it.
                    $unit_homepage   = CategoryMapper::getInstance()->findById($unit_id);
                    $course_instance = new UnitInstance();
                    $course_instance
                        ->setComponent($component)
                        ->setTitle($unit_homepage->getName())
                        ->setCategory($homepage)
                        ->setUnitCategory($unit_homepage)
                        ->setOpenDate($open_date)
                        ->setDueDate($due_date)
                    ;
                    $saved_list->add($course_instance);
                }
            }
        }
    }
    if (count($saved_list)) {
        if (UnitInstanceMapper::getInstance()->save($saved_list)) {
            // Update due dates of items, if required
            $taskMapper = Task\InstanceMapper::getInstance();
            foreach ($saved_list as $savedInstance) {
                $unitInstanceId = $savedInstance->getId();
                $updateOpenDate = true;
                $updateDueDate  = true;
                $undelete       = array_key_exists($unitInstanceId, $undeletedIds);
                if (!$undelete) {
                    foreach ($_POST['unit'] as $unitPost) {
                        if ($unitPost['unit_instance_id'] == $unitInstanceId) {
                            $updateOpenDate = $unitPost['update_open_date'];
                            $updateDueDate  = $unitPost['update_due_date'];
                            break;
                        }
                    }
                }

                // Not updating either? Go no further
                if (!($updateOpenDate || $updateDueDate || $undelete)) {
                    continue;
                }

                // Get all the tasks out, and update their due dates
                // (and possibly deleted status)
                /** @var Task\Instance[] $taskList */
                $taskList = $taskMapper->findByColumn(
                    'course_unit_instance_id',
                    $savedInstance->getId(),
                    ['deleted' => $undelete,]
                );
                foreach ($taskList as $task) {
                    if ($undelete) {
                        $task->setDeleted(null);
                        $taskMapper->save($task);
                    } elseif ($updateDueDate || $updateOpenDate) {
                        $cloner = new Task\Cloner($task);
                        if ($updateOpenDate) {
                            $cloner->updateTaskOpenTime();
                        }
                        if ($updateDueDate) {
                            $cloner->updateTaskCloseTime();
                        }
                    }
                }
//                $taskMapper->save($taskList);
            }
        }
    }
    if (count($deleted_list)) {
        UnitInstanceMapper::getInstance()->delete($deleted_list);
    }
}

// Go back to the homepage, or output AJAX
if ($site_page->detectAjax()) {
    $result = array(
        'success' => false,
        'error'   => 500,
        'message' => 'Unknown Error',
    );
    if ($permission_denied) {
        $result['error']   = 403;
        $result['message'] = 'Permission Denied';
    }
    else {
        $result['success'] = true;
        $result['error']   = 200;
        $result['message'] = 'OK';
    }
    // HTML should be the output of this component.
    echo json_encode($result);
    exit;
}
else {
    if ($permission_denied) {
        error(6);
    }
    $site_page->localRedir('/homepage/'.$component->getIdentifier());
}
