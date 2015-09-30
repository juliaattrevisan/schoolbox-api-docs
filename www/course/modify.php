<?php
/**
 * Page for modifying a course that has been loaded
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;

/** @var SitePage $this */
//list($component_id) = $this->arg;
//$renderer = new Schoolbox\Assessment\Course\Renderer();
//$renderer->setupFromComponent($component_id);
//$renderer->render();

// Args:
// 0: component
list($component_id) = $this->arg;
if (empty($component_id)) {
    error(2);
}

/** @var Component_Instance $component */
$component_list = Component_InstanceMapper::getInstance()->findByInstanceId($component_id);
$component      = $component_list[$component_id];

// Test permissions:
// Write on $homepage_id
$group = new Group();
if ($group->getPermission($component->getIdentifier()) < WRITE_ACCESS) {
    error(6);
}

/** @var UnitInstance[] $course_list */
$course_list = UnitInstanceMapper::getInstance()->findByColumn(
    'component_id',
    $component_id,
    array(
        'order' => array(
            'open_date' => 'asc',
            'due_date'  => 'asc',
        )
    )
);

// block setup (PHPLib sucks)
$this->setFile(TEMPLATE_content, "course/modify.html");
$this->setBlock(TEMPLATE_content, 'course', 'course_block');
$this->setBlock('course', 'course_breadcrumb', 'course_breadcrumb_block');
$this->setBlock(TEMPLATE_content, 'unit', 'unit_block');
$this->setBlock('unit', 'unit_update_open_date', 'unit_update_open_date_block');
$this->setBlock('unit', 'unit_update_due_date', 'unit_update_due_date_block');

$this->setVar(
    array(
        'course_block'           => '',
        'HOMEPAGE_ID'            => '',
        'COURSE_HOMEPAGE_ID'     => '',
        'COMPONENT_ID'           => Scrub::text($component_id),
    )
);

// Output course and category breadcrumbs
$homepage = CategoryMapper::getInstance()->findById($component->getIdentifier());

// Output course breadcrumbs (for every course that is in this outline)
$courseBaseHomepage = new Category();
$courseBaseHomepage->setId(str_replace('/homepage/', '', Config::get('course_homepage')));

// Keep a list of the units that have already been shown
$unitCategoryIdList = array();
$courseIdList       = array();
foreach ($course_list as $unit) {
    $this->setVar('course_breadcrumb_block', '');

    // Has this unit category had its breadcrumb shown already? Don't show it again
    $unitCategoryId = $unit->getUnitCategory()->getId();
    if (array_key_exists($unitCategoryId, $unitCategoryIdList)) {
        continue;
    }
    $unitCategoryIdList[$unitCategoryId] = $unitCategoryId;

    // Find the course for this unit, and show its breadcrumb if it isn't shown
    // I am not showing anything if this category doesn't exist
    try {
        $unitCategory = CategoryMapper::getInstance()->findById($unitCategoryId);
    } catch (MapperException $e) {
        continue;
    }
    $courseId = $unitCategory->getParent()->getId();
    if (array_key_exists($courseId, $courseIdList)) {
        continue;
    }
    $courseIdList[$courseId] = $courseId;

    // Now, finally show the breadcrumb.
    foreach (CategoryMapper::getInstance()->findParents($unitCategory, $courseBaseHomepage) as $parent) {
        $this->setVar('COURSE_CATEGORY_ID', Scrub::text($parent->getId()));
        $this->setVar('COURSE_CATEGORY_NAME', Scrub::text($parent->getName()));
        $this->parse('course_breadcrumb_block', 'course_breadcrumb', true);
    }
    $this->parse('course_block', 'course', true);
}

$this->setVar('unit_block', '');
$count                   = 0;
$displayed_category_list = array();
foreach ($course_list as $unit) {
    $unit_id = $unit->getId();
    try {
        $unit->setUnitCategory(
            CategoryMapper::getInstance()->findById(
                $unit->getUnitCategory()->getId()
            )
        );
    } catch (MapperException $e) {
        continue;
    }
    $displayed_category_list[$unit->getUnitCategory()->getId()] = true;
    $this->setVar(
        array(
            'UNIT_ID'              => Scrub::text($unit->getUnitCategory()->getId()),
            'UNIT_COUNT'           => $count++,
            'UNIT_ENABLED'         => 1,
            'UNIT_ENABLED_CHECKED' => 'checked',
            'UNIT_INSTANCE_ID'     => Scrub::text($unit->getId()),
            'UNIT_NAME'            => Scrub::text($unit->getUnitCategory()->getName()),
            'UNIT_OPEN_DATE'       => $unit->getOpenDate()->getLocalShort(),
            'UNIT_DUE_DATE'        => $unit->getDueDate()->getLocalShort(),
        )
    );
    $this->parse('unit_update_due_date_block', 'unit_update_due_date');
    $this->parse('unit_update_open_date_block', 'unit_update_open_date');
    $this->parse('unit_block', 'unit', true);
}

// Output any items that aren't in the existing block, too.
/** @var Category[]|Collection $child_list */
$child_list    = CategoryMapper::getInstance()->findByColumn('parent', $courseIdList);
$child_id_list = $child_list->getColumnValues('id');
$task_list     = TaskMapper::getInstance()->findGroupedListForCategoryList($child_id_list);
foreach ($child_list as $unit) {
    $unit_id = $unit->getId();
    if (array_key_exists($unit_id, $displayed_category_list)) {
        continue;
    }
    if (count($task_list->getOffset($unit_id)) == 0) {
        continue;
    }
    $this->setVar(
        array(
            'UNIT_ID'                     => Scrub::text($unit_id),
            'UNIT_COUNT'                  => $count++,
            'UNIT_ENABLED'                => 0,
            'UNIT_ENABLED_CHECKED'        => '',
            'UNIT_INSTANCE_ID'            => '',
            'UNIT_NAME'                   => Scrub::text($unit->getName()),
            'UNIT_OPEN_DATE'              => '',
            'UNIT_DUE_DATE'               => '',
            'unit_update_due_date_block'  => '',
            'unit_update_open_date_block' => '',
        )
    );
    $this->parse('unit_block', 'unit', true);
}

$trail = new \Schoolbox\Breadcrumbs(Schoolbox\Resource\Folder\Mapper::getInstance());
$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text'  => $homepage->getName(),
        'href'  => "/homepage/{$homepage->getId()}",
        'class' => '',
    ],
    [
        'text'  => 'Modify Course',
        'href'  => '',
        'class' => 'active',
    ]
]);
