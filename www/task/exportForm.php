<?php
/**
 * Form for exporting tasks into classes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../include/Bootstrap.php';

use Schoolbox\Collection;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\CriterionMapper;

$sitePage = new SitePage();

$taskId = $_POST['task'];
if (empty($taskId)) {
    error(2);
    exit;
}
try {
    /** @var Task $task */
    /** @var Folder $category */
    $task       = TaskMapper::getInstance()->findById($taskId);
    $categoryId = $task->getCategory()->getId();
    $category   = FolderMapper::getInstance()->findById($categoryId);
}
catch (\Schoolbox\Mapper\Exception $e) {
    error(2);
    exit;
}

// Shitty permission handling - if I can't access the task's category, I can't
// import it this way.
// Consider revising to read access, as this action doesn't update $category
$groupHandler = new Group();
if ($groupHandler->getPermission($categoryId) < WRITE_ACCESS) {
    error(6);
    exit;
}

// Get list of updatable items.
$unitList = UnitInstanceMapper::getInstance()->findByUnitCategoryId($categoryId);
/** @var Folder[] $classWithUnitList */
$classWithUnitList = FolderMapper::getInstance()->findByColumn(
    'id',
    $unitList->map(function($unit) {
        /** @var UnitInstance $unit */
        return $unit->getComponent()->getIdentifier();
    })
);

foreach ($classWithUnitList as $classWithUnit) {

    // Can I actually write to this category?
    if ($groupHandler->getPermission($classWithUnit->getId()) < WRITE_ACCESS) {
        continue;
    }

    // Get the unit instances that apply to this class.
    /** @var UnitInstance[] $unitListForClass */
    $unitListForClass = $unitList->filter(function($unit) use($classWithUnit) {
        /** @var UnitInstance $unit */
        return $unit->getComponent()->getIdentifier() == $classWithUnit->getId();
    });
    foreach ($unitListForClass as $unit) {
        // Did I actually select this item?
        if (!empty($_POST['unit_instance'][$unit->getId()])) {
            try {
                // If this search succeeds, the task is already in the class.
                // Don't try to import it again.
                Task\InstanceMapper::getInstance()->findOneByColumnList(
                    array(
                        'task_id'                 => $task->getId(),
                        'course_unit_instance_id' => $unit->getId(),
                    )
                );
                continue;
            }
            catch (Exception $e) {
                // Run the import
                // First import rubrics for both the course and unit that is
                // being imported.
                CriterionMapper::getInstance()->copyFromCategory(
                    $category->getParent()->getId(),
                    $unit->getCategory()->getId()
                );
                CriterionMapper::getInstance()->copyFromCategory(
                    $category->getId(),
                    $unit->getCategory()->getId()
                );
                UnitInstanceMapper::getInstance()->saveTaskIntoUnitInstance(
                    $unit,
                    $task,
                    $_SESSION['user']
                );
            }
        }
    }
}

// May as well redirect back to the export for this item!
$sitePage->localRedir('/task/export/'.$taskId);
exit;
