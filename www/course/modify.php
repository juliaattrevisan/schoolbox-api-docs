<?php
/**
 * Page for modifying a course that has been loaded
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
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;

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
//var_dump($component, $course_list);exit;

// block setup (PHPLib sucks)
$this->setFile(TEMPLATE_content, "course/import.html");
$this->setBlock(TEMPLATE_content, 'course_breadcrumb', 'course_breadcrumb_block');
$this->setBlock(TEMPLATE_content, 'class_breadcrumb',  'class_breadcrumb_block');
$this->setBlock(TEMPLATE_content, 'unit',              'unit_block');
$this->setBlock('unit', 'task', 'task_block');

$this->setVar(
    array(
        'class_breadcrumb_block'  => '',
        'course_breadcrumb_block' => '',
        'FORM_ID'                 => 'modifyForm',
        'FORM_ACTION'             => '/course/modifyForm.php',
        'HOMEPAGE_ID'             => '',
        'COURSE_HOMEPAGE_ID'      => '',
        'COMPONENT_ID'            => Scrub::text($component_id),
    )
);

// Output course and category breadcrumbs
$homepage      = CategoryMapper::getInstance()->findById($component->getIdentifier());
$homepage_path = CategoryMapper::getInstance()->findParents($homepage);
$homepage_path->add($homepage);
foreach ($homepage_path as $parent) {
    /** @var Category $parent */
    $this->setVar('CLASS_CATEGORY_ID',   Scrub::text($parent->getId()));
    $this->setVar('CLASS_CATEGORY_NAME', Scrub::text($parent->getName()));
    $this->parse('class_breadcrumb_block', 'class_breadcrumb', true);
}

$component_config = $component->getConfigArray();
$course_homepage = CategoryMapper::getInstance()->findById($component_config['courseHomepage']['value']);
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

$this->setVar('unit_block', '');
foreach ($course_list as $unit) {
    $unit_id = $unit->getId();
    $unit->setUnitCategory(CategoryMapper::getInstance()->findById($unit->getUnitCategory()->getId()));
    $this->setVar(
        array(
            'UNIT_ID'          => Scrub::text($unit->getUnitCategory()->getId()),
            'UNIT_INSTANCE_ID' => Scrub::text($unit->getId()),
            'UNIT_NAME'        => Scrub::text($unit->getUnitCategory()->getName()),
            'UNIT_OPEN_DATE'   => $unit->getOpenDate()->getUsingFormat('d/m/Y'),
            'UNIT_DUE_DATE'    => $unit->getDueDate()->getUsingFormat('d/m/Y'),
            'task_block'       => '',
        )
    );
    $this->parse('unit_block', 'unit', true);
}
