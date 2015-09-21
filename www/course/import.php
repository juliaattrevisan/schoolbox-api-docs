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
//list($homepage_id, $component_id, $course_homepage_id) = $this->arg;
//$renderer = new Schoolbox\Assessment\Course\Renderer();
//$renderer->setupFromCourseAndComponent($course_homepage_id, $component_id);
//$renderer->render();

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
$task_list     = TaskMapper::getInstance()->findGroupedListForCategoryList($child_id_list);

// block setup (PHPLib sucks)
$this->setFile(TEMPLATE_content, "course/import.html");
$this->setBlock(TEMPLATE_content, 'course_breadcrumb', 'course_breadcrumb_block');
$this->setBlock(TEMPLATE_content, 'importable',        'importable_block');
$this->setBlock(TEMPLATE_content, 'submit',            'submit_block');
$this->setBlock(TEMPLATE_content, 'unimportable',      'unimportable_block');
$this->setBlock('importable',     'unit',              'unit_block');
$this->setBlock('unit', 'task', 'task_block');

$this->setVar(
    array(
        'class_breadcrumb_block'  => '',
        'course_breadcrumb_block' => '',
        'importable_block'        => '',
        'unimportable_block'      => '',
        'submit_block'            => '',
        'HOMEPAGE_ID'             => Scrub::text($homepage_id),
        'COURSE_HOMEPAGE_ID'      => Scrub::text($course_homepage_id),
        'COMPONENT_ID'            => Scrub::text($component_id),
    )
);

// Output course and category breadcrumbs
$homepage = CategoryMapper::getInstance()->findById($homepage_id);

$course_homepage = CategoryMapper::getInstance()->findById($course_homepage_id);
$course_base_homepage = new Category();
$course_base_homepage->setId(str_replace('/homepage/', '', Config::get('course_homepage')));
$course_homepage_path = CategoryMapper::getInstance()->findParents($course_homepage, $course_base_homepage);
$course_homepage_path->add($course_homepage);
foreach ($course_homepage_path as $parent) {
    /** @var Category $parent */
    $this->setVar('COURSE_CATEGORY_ID',   Scrub::text($parent->getId()));
    $this->setVar('COURSE_CATEGORY_NAME', Scrub::text($parent->getName()));
    $this->parse('course_breadcrumb_block', 'course_breadcrumb', true);
}

$count = 0;
$this->setVar('unit_block', '');
foreach ($child_list as $unit) {
    $unit_id = $unit->getId();
    if (count($task_list->getOffset($unit_id)) == 0) {
        continue;
    }
    $this->setVar(
        array(
            'UNIT_ID'              => Scrub::text($unit_id),
            'UNIT_COUNT'           => $count++,
            'UNIT_ENABLED'         => 1,
            'UNIT_ENABLED_CHECKED' => 'checked',
            'UNIT_INSTANCE_ID'     => '',
            'UNIT_NAME'            => Scrub::text($unit->getName()),
            'UNIT_OPEN_DATE'       => '',
            'UNIT_DUE_DATE'        => '',
            'task_block'           => '',
        )
    );
    $this->parse('unit_block', 'unit', true);
}
if ($count > 0) {
    $this->parse('importable_block', 'importable');
    $this->parse('submit_block',     'submit');
}
else {
    $this->parse('unimportable_block', 'unimportable');
}

$trail = new \Schoolbox\Breadcrumbs(Schoolbox\Resource\Folder\Mapper::getInstance());
$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text'  => $homepage->getName(),
        'href'  => "/homepage/{$homepage->getId()}",
        'class' => '',
    ],
    [
        'text'  => 'Import Course',
        'href'  => '',
        'class' => 'active',
    ]
]);