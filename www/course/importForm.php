<?php
/**
 * Form for importing a course into a component
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../include/Bootstrap.php';

//use Schoolbox\Resource\Category;
use Schoolbox\Collection;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;

$site_page = new SitePage();

$homepage_id        = $_POST['homepage'];
$course_homepage_id = $_POST['course_homepage'];
$component_id       = $_POST['component'];
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

// What to do?
// Create new course_unit_instance items
// Foreach task within the unit:
// Import as a task instance
// Import assessment if necessary
$component          = Component_InstanceMapper::getInstance()->findById($component_id);
$unit_instance_list = new Collection();
foreach ($_POST['unit'] as $unit_id => $unit) {
    $unit_homepage = CategoryMapper::getInstance()->findById($unit_id);
    $unit_instance = new UnitInstance();
    $unit_instance
        ->setComponent($component)
        ->setTitle($unit_homepage->getName())
        ->setUnitCategory($unit_homepage)
        ->setOpenDate(new Alaress_Date_Format($unit['open_date'], 'd/m/y'))
        ->setDueDate(new Alaress_Date_Format($unit['open_date'], 'd/m/y'))
    ;
    $unit_instance_list->add($unit_instance);
}
UnitInstanceMapper::getInstance()->save($unit_instance_list);
// Add post save methods to the unit instance mapper: it imports tasks within it

// Go back to the homepage
$site_page->localRedir('/homepage/'.$homepage_id);
