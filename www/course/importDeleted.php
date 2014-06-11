<?php
/**
 * Page for importing units from deleted components into a course.
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
use Schoolbox\Assessment\Course\UnitInstanceMapper;

/** @var SitePage $this */
//list($homepage_id, $component_id, $course_homepage_id) = $this->arg;
//$renderer = new Schoolbox\Assessment\Course\Renderer();
//$renderer->setupFromCourseAndComponent($course_homepage_id, $component_id);
//$renderer->render();

// Args:
// 0: homepage
// 1: component
list($homepage_id, $component_id) = $this->arg;
if (empty($homepage_id) || empty($component_id)) {
    error(2);
}

// Test permissions:
// Write on $homepage_id
$group = new Group();
if ($group->getPermission($homepage_id) < WRITE_ACCESS) {
    error(6);
}

// Get all the deleted units
$deletedUnitList = UnitInstanceMapper::getInstance()->findOrphanedForCategoryId($homepage_id);

// Get all tasks for children of this category.
/** @var Collection|Category[] $child_list */
//$child_list    = CategoryMapper::getInstance()->findByColumn('parent', $course_homepage_id);
//$child_id_list = $child_list->getColumnValues('id');
//$task_list     = TaskMapper::getInstance()->findGroupedListForCategoryList($child_id_list);

// block setup (PHPLib sucks)
$this->setFile(TEMPLATE_content, "course/importDeleted.html");
$this->setBlock(TEMPLATE_content, 'class_breadcrumb',  'class_breadcrumb_block');
$this->setBlock(TEMPLATE_content, 'unit',              'unit_block');

$this->setVar(
    array(
        'class_breadcrumb_block'  => '',
        'course_breadcrumb_block' => '',
        'importable_block'        => '',
        'unimportable_block'      => '',
        'submit_block'            => '',
        'HOMEPAGE_ID'             => Scrub::text($homepage_id),
        'COMPONENT_ID'            => Scrub::text($component_id),
    )
);

// Output course and category breadcrumbs
$homepage      = CategoryMapper::getInstance()->findById($homepage_id);
$homepage_path = CategoryMapper::getInstance()->findParents($homepage);
$homepage_path->add($homepage);
foreach ($homepage_path as $parent) {
    /** @var Category $parent */
    $this->setVar('CLASS_CATEGORY_ID',   Scrub::text($parent->getId()));
    $this->setVar('CLASS_CATEGORY_NAME', Scrub::text($parent->getName()));
    $this->parse('class_breadcrumb_block', 'class_breadcrumb', true);
}

//$course_homepage = CategoryMapper::getInstance()->findById($course_homepage_id);
//$course_base_homepage = new Category();
//$course_base_homepage->setId(str_replace('/homepage/', '', Config::get('course_homepage')));
//$course_homepage_path = CategoryMapper::getInstance()->findParents($course_homepage, $course_base_homepage);
//$course_homepage_path->add($course_homepage);
//foreach ($course_homepage_path as $parent) {
//    /** @var Category $parent */
//    $this->setVar('COURSE_CATEGORY_ID',   Scrub::text($parent->getId()));
//    $this->setVar('COURSE_CATEGORY_NAME', Scrub::text($parent->getName()));
//    $this->parse('course_breadcrumb_block', 'course_breadcrumb', true);
//}

$count = 0;
$this->setVar('unit_block', '');
foreach ($deletedUnitList as $unit) {
    $unit_id = $unit->getId();
//    if (count($task_list->getOffset($unit_id)) == 0) {
//        continue;
//    }
    $this->setVar(
        array(
            'UNIT_ID'              => Scrub::text($unit_id),
//            'UNIT_COUNT'           => $count++,
            'UNIT_ENABLED'         => 1,
            'UNIT_ENABLED_CHECKED' => 'checked',
//            'UNIT_INSTANCE_ID'     => '',
            'UNIT_NAME'            => Scrub::text($unit->getTitle()),
            'UNIT_OPEN_DATE'       => $unit->getOpenDate()->getDatabaseDate(),
            'UNIT_OPEN_DATE_LABEL' => $unit->getOpenDate()->getDateOnly(),
            'UNIT_OPEN_DATE_REL'   => $unit->getRelativeOpenDate()->getRelativeTime(),
            'UNIT_DUE_DATE'        => $unit->getDueDate()->getDatabaseDate(),
            'UNIT_DUE_DATE_LABEL'  => $unit->getDueDate()->getDateOnly(),
            'UNIT_DUE_DATE_REL'    => $unit->getRelativeDueDate()->getRelativeTime(),
//            'task_block'           => '',
        )
    );
    $this->parse('unit_block', 'unit', true);
}
//if ($count > 0) {
//    $this->parse('importable_block', 'importable');
//    $this->parse('submit_block',     'submit');
//}
//else {
//    $this->parse('unimportable_block', 'unimportable');
//}