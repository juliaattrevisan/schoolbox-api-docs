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
if ($group->getPermission($category_id) < WRITE_ACCESS) {
    error(6);
    exit;
}

// Shitty block parsing
$this->setFile(TEMPLATE_content,  "task/export.html");
$this->setBlock(TEMPLATE_content, 'unit_breadcrumb',  'unit_breadcrumb_block');
$this->setBlock(TEMPLATE_content, 'exportable',       'exportable_block');
$this->setBlock(TEMPLATE_content, 'unexportable',     'unexportable_block');
$this->setBlock('exportable',     'class',            'class_block');
$this->setBlock('class',          'class_breadcrumb', 'class_breadcrumb_block');
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
        'TASK_ID'         => $task->getId(),
        'TASK_TITLE'      => Scrub::text($task->getTitle()),
        'TASK_TYPE_LABEL' => Scrub::text($task->getLabel()),
    )
);

// Fill in unit breadcrumb
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
$unitList = UnitInstanceMapper::getInstance()->findByUnitCategoryId($categoryId);
$categoryWithUnitList = CategoryMapper::getInstance()->findByColumn(
    'id',
    $unitList->map(function($unit) {
        /** @var UnitInstance $unit */
        return $unit->getComponent()->getIdentifier();
    })
);
if (count($categoryWithUnitList) > 0) {
    $this->parse('exportable_block', 'exportable');
    $this->setVar('class_block', '');
    /** @var Category[]|Collection $categoryWithUnitList */
    $count = 0;
    foreach ($categoryWithUnitList as $categoryWithUnit) {
        /** @var UnitInstance[] $categoryUnitList */
        $categoryUnitList = $unitList->filter(function($unit) use($categoryWithUnit) {
            /** @var UnitInstance $unit */
            return $unit->getComponent()->getIdentifier() == $categoryWithUnit->getId();
        });
        foreach ($categoryUnitList as $categoryUnit) {

            // Fill in breadcrumb
            $this->setVar('class_breadcrumb_block', '');
            foreach (CategoryMapper::getInstance()->findParents($categoryWithUnit) as $parent) {
                /** @var Category $parent */
                $this->setVar('UNIT_CATEGORY_ID',   $parent->getId());
                $this->setVar('UNIT_CATEGORY_NAME', $parent->getName());
                $this->parse('class_breadcrumb_block', 'class_breadcrumb', true);
            }
            $this->setVar('UNIT_CATEGORY_ID',   $categoryWithUnit->getId());
            $this->setVar('UNIT_CATEGORY_NAME', $categoryWithUnit->getName());
            $this->parse('class_breadcrumb_block', 'class_breadcrumb', true);

            $this->setVar(
                array(
                    'UNIT_ID'              => $categoryUnit->getId(),
                    'CLASS_COUNT'           => $count++,
                    'CLASS_ENABLED_CHECKED' => 'checked',
                    'CLASS_NAME'            => $categoryWithUnit->getName(),
                    'CLASS_OPEN_DATE'       => $categoryUnit->getRelativeOpenDate()->getRelativeTime(),
                    'CLASS_DUE_DATE'        => $categoryUnit->getRelativeDueDate()->getRelativeTime(),
                )
            );

            // Does the requested task already exist for this class?

            $this->parse('class_block', 'class', true);
        }
    }
}
else {
    $this->parse('unexportable_block', 'unexportable');
}
