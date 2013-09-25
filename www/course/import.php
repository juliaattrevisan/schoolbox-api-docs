<?php
/**
 * Page for importing a course (probably loaded via AJAX)
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Collection;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;

/** @var SitePage $this */

// Args:
// 0: homepage
// 1: component
// 2: selected homepage
list($homepage_id, $component_id, $course_homepage_id) = $this->arg;
if (empty($homepage_id) || empty($component_id) || empty($course_homepage_id)) {
    error(2);
}

// Test permissions:
// Write on $homepage_id
// Read on $course_homepage_id
$group = new Group();
if ($group->getPermission($homepage_id) < WRITE_ACCESS
    || $group->getPermission($course_homepage_id) < READ_ACCESS
) {
    error(6);
}

// Get all tasks for children of this category.
/** @var Collection|Category[] $child_list */
$child_list    = CategoryMapper::getInstance()->findByColumn('parent', $course_homepage_id);
$child_id_list = $child_list->getColumnValues('id');

/** @var Task[] $task_list */
$task_list = TaskMapper::getInstance()->findByColumn(
    'category_id',
    $child_id_list,
    array(
        'order' => array('category_id' => 'asc'),
    )
);
// Group task list by category
$grouped_task_list = array();
foreach ($task_list as $task) {
    $grouped_task_list[$task->getCategory()->getId()][] = $task;
}

// block setup (PHPLib sucks)
$this->setFile(TEMPLATE_content, "course/import.html");
$this->setBlock(TEMPLATE_content, 'course_breadcrumb', 'course_breadcrumb_block');
$this->setBlock(TEMPLATE_content, 'class_breadcrumb',  'class_breadcrumb_block');
$this->setBlock(TEMPLATE_content, 'unit',              'unit_block');
$this->setBlock('unit', 'task', 'task_block');

// Output course and category breadcrumbs
$this->setVar('class_breadcrumb_block', '');
$homepage      = CategoryMapper::getInstance()->findById($homepage_id);
$homepage_path = CategoryMapper::getInstance()->findParents($homepage);
$homepage_path->add($homepage);
foreach ($homepage_path as $parent) {
    /** @var Category $parent */
    $this->setVar('CLASS_CATEGORY_ID',   $parent->getId());
    $this->setVar('CLASS_CATEGORY_NAME', $parent->getName());
    $this->parse('class_breadcrumb_block', 'class_breadcrumb', true);
}

$this->setVar('course_breadcrumb_block', '');
$course_homepage = CategoryMapper::getInstance()->findById($course_homepage_id);
$course_base_homepage = new Category();
$course_base_homepage->setId(str_replace('/homepage/', '', Config::get('course_homepage')));
$course_homepage_path = CategoryMapper::getInstance()->findParents($course_homepage, $course_base_homepage);
$course_homepage_path->add($course_homepage);
foreach ($course_homepage_path as $parent) {
    /** @var Category $parent */
    $this->setVar('COURSE_CATEGORY_ID',   $parent->getId());
    $this->setVar('COURSE_CATEGORY_NAME', $parent->getName());
    $this->parse('course_breadcrumb_block', 'course_breadcrumb', true);
}

$this->setVar('unit_block', '');
foreach ($child_list as $unit) {
    $unit_id = $unit->getId();
    if (empty($grouped_task_list[$unit_id])) {
        continue;
    }
    $this->setVar(
        array(
            'UNIT_ID'        => $unit_id,
            'UNIT_NAME'      => $unit->getName(),
            'UNIT_OPEN_DATE' => '',
            'UNIT_DUE_DATE'  => '',
            'task_block'     => '',
        )
    );

//    foreach ($grouped_task_list[$unit_id] as $task) {
//        /** @var Task $task */
//        $this->setVar('TASK_ID',    $task->getId());
//        $this->setVar('TASK_NAME',  $task->getTitle());
//        $this->parse('task_block', 'task', true);
//    }
    $this->parse('unit_block', 'unit', true);
}
