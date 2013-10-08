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

// Test permissions:
// Write on $homepage_id
$group = new Group();
if ($group->getPermission($component->getIdentifier()) < WRITE_ACCESS) {
    error(6);
}

// Save each set of units.
$saved_list   = new Collection();
$deleted_list = new Collection();
foreach ($_POST['unit'] as $unit_data) {
    // Figure out which unit instance ID (if any) is attached
    $unit_id   = $unit_data['unit_id'];
    $import    = !empty($unit_data['import']);
    $open_date = new Alaress_Date_Format($unit_data['open_date'], 'd/m/y');
    $due_date  = new Alaress_Date_Format($unit_data['due_date'], 'd/m/y');
    try {
        // Is there a unit instance? If so fetch it, otherwise fetch by unit
        // category and component.
        if (!empty($unit_data['unit_instance_id'])) {
            $course_instance = UnitInstanceMapper::getInstance()->findById($unit_data['unit_instance_id']);
        }
        else {
            $course_instance = UnitInstanceMapper::getInstance()->findOneByColumnList(
                array(
                    'component_id'     => $component_id,
                    'unit_category_id' => $unit_id,
                )
            );
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
                ->setUnitCategory($unit_homepage)
                ->setOpenDate($open_date)
                ->setDueDate($due_date)
            ;
            $saved_list->add($course_instance);
        }
    }
}
if (count($saved_list)) {
    UnitInstanceMapper::getInstance()->save($saved_list);
}
if (count($deleted_list)) {
    UnitInstanceMapper::getInstance()->delete($deleted_list);
}

// Go back to the homepage
$site_page->localRedir('/homepage/'.$component->getIdentifier());
