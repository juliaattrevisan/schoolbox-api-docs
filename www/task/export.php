<?php
/**
 * Page for exporting tasks into classes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/** @var SitePage $this */

use Schoolbox\Collection;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;

$taskId = $this->arg[0];
try {
    /** @var Task $task */
    /** @var Category $category */
    $task       = TaskMapper::getInstance()->findById($taskId);
    $categoryId = $task->getCategory()->getId();
    $category   = CategoryMapper::getInstance()->findById($categoryId);
}
catch (\Schoolbox\Mapper\Exception $e) {
    error(2);
    exit;
}

// Shitty permission handling
$group = new Group();
if ($group->getPermission($categoryId) < WRITE_ACCESS) {
    error(6);
    exit;
}

// Shitty block parsing
$this->setFile(TEMPLATE_content,  "task/export.html");
$this->setBlock(TEMPLATE_content, 'unit_breadcrumb',  'unit_breadcrumb_block');
$this->setBlock(TEMPLATE_content, 'exportable',       'exportable_block');
$this->setBlock(TEMPLATE_content, 'unexportable',     'unexportable_block');
$this->setBlock('exportable',     'class',            'class_block');
$this->setBlock('class',          'class_import_allowed', 'class_import_allowed_block');
$this->setBlock('class',          'class_import_forbidden', 'class_import_forbidden_block');
$this->setBlock('class',          'class_import_exists', 'class_import_exists_block');
$this->setBlock('class',          'class_import_checkbox', 'class_import_checkbox_block');
$this->setBlock(TEMPLATE_content, 'submit',           'submit_block');
$this->setVar(
    array(
        'unit_breadcrumb_block' => '',
        'exportable_block'      => '',
        'submit_block'          => '',
        'unexportable_block'    => '',
    )
);

// Fill in task
$this->setVar(
    array(
        'TASK_ID'         => Scrub::html($task->getId()),
        'TASK_TITLE'      => Scrub::text($task->getTitle()),
        'TASK_TYPE_LABEL' => Scrub::text($task->getLabel()),
        'CATEGORY_ID'     => Scrub::html($categoryId),
    )
);

// Fill in unit breadcrumb
// TODO do this in a Twig template
foreach (CategoryMapper::getInstance()->findParents($category) as $parent) {
    /** @var Category $parent */
    $this->setVar('UNIT_CATEGORY_ID',   $parent->getId());
    $this->setVar('UNIT_CATEGORY_NAME', $parent->getName());
    $this->parse('unit_breadcrumb_block', 'unit_breadcrumb', true);
}
$this->setVar('UNIT_CATEGORY_ID',   $category->getId());
$this->setVar('UNIT_CATEGORY_NAME', $category->getName());
$this->parse('unit_breadcrumb_block', 'unit_breadcrumb', true);

// Get each class which uses the unit already.
/** @var UnitInstance[]|Collection $unitList */
$unitList = UnitInstanceMapper::getInstance()->findByUnitCategoryId($categoryId);
/** @var Category[]|Collection $classWithUnitList */
$classWithUnitList = CategoryMapper::getInstance()->findByColumn(
    'id',
    $unitList->map(function($unit) {
        /** @var UnitInstance $unit */
        return $unit->getComponent()->getIdentifier();
    })
);


if (count($unitList) > 0) {
    $this->parse('exportable_block', 'exportable');
    $this->setVar('class_block', '');
    $count = 0;
    $exportableCount = 0;
    foreach ($unitList as $unit) {

        // Load category and hierarchy for this unit
        $unitCategory = CategoryMapper::getInstance()->findById($unit->getComponent()->getIdentifier());

        // Block setup
        $this->setVar('class_import_allowed_block', '');
        $this->setVar('class_import_forbidden_block', '');
        $this->setVar('class_import_exists_block', '');
        $this->setVar('class_import_checkbox_block', '');


        $this->setVar('UNIT_CATEGORY_ID',   $unitCategory->getId().'#component'.$unit->getComponent()->getId());
        $this->setVar('UNIT_CATEGORY_NAME', $unitCategory->getCodeString() ? $unitCategory->getName().' ('.$unitCategory->getCodeString().')' : $unitCategory->getName() );

        // Variable setup
        $vars = array(
            'UNIT_INSTANCE_ID'    => $unit->getId(),
            'CLASS_COUNT'         => $count++,
            'CLASS_NAME'          => $unitCategory->getName(),
            'CLASS_OPEN_DATE'     => $unit->getRelativeOpenDate()->getRelativeTime(),
            'CLASS_DUE_DATE'      => $unit->getRelativeDueDate()->getRelativeTime(),
            'CLASS_OPEN_DATETIME' => $unit->getOpenDate()->getDatabaseDate(),
            'CLASS_DUE_DATETIME'  => $unit->getDueDate()->getDatabaseDate(),
            'CLASS_OPEN_TITLE'    => $unit->getOpenDate()->getLongDateOnly(),
            'CLASS_DUE_TITLE'     => $unit->getDueDate()->getLongDateOnly(),
        );
        $this->setVar(array_map(array('Scrub', 'html'), $vars));

        // Does the requested task already exist for this class?
        if ($group->getPermission($unitCategory->getId()) < WRITE_ACCESS) {
            $this->parse('class_import_forbidden_block', 'class_import_forbidden');
        }
        else {
            try {
                Task\InstanceMapper::getInstance()->findOneByColumnList(
                    array(
                        'task_id'                 => $task->getId(),
                        'course_unit_instance_id' => $unit->getId(),
                    )
                );
                $this->parse('class_import_exists_block', 'class_import_exists');
            }
            catch (Exception $e) {
                $exportableCount++;
                $this->parse('class_import_allowed_block', 'class_import_allowed');
                $this->parse('class_import_checkbox_block', 'class_import_checkbox');
            }
        }
        $this->parse('class_block', 'class', true);
    }
    if ($exportableCount) {
        $this->parse('submit_block', 'submit');
    }
}
else {
    $this->parse('unexportable_block', 'unexportable');
}

$trail = new Schoolbox\Breadcrumbs();
$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text' => $category->getName(),
        'href' => "/homepage/{$category->getId()}",
        'class' => '',
    ],
    [
        'text' => 'Export Task',
        'href' => '',
        'class' => 'active',
    ]
]);
