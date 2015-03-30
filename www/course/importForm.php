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
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\CriterionMapper;

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

/** @var Component_Instance $component */
$component = Component_InstanceMapper::getInstance()->findById($component_id);

// Merge rubrics from course into this homepage.
CriterionMapper::getInstance()->copyFromCategory($course_homepage_id, $homepage_id);

// And create a new unit instance for each option.
$unit_instance_list = new Collection();
foreach ($_POST['unit'] as $unit_data) {
    $unit_id = $unit_data['unit_id'];
    $import  = !empty($unit_data['import']);
    if ($import) {
        // Merge rubrics from first, because they need to exist before the due work is added.
        CriterionMapper::getInstance()->copyFromCategory($unit_id, $homepage_id);

        // and save the unit into the course.
        $unit_homepage = CategoryMapper::getInstance()->findById($unit_id);
        $unit_instance = new UnitInstance();
        $unit_instance
            ->setComponent($component)
            ->setTitle($unit_homepage->getName())
            ->setCategory($component->getIdentifier())
            ->setUnitCategory($unit_homepage)
            ->setOpenDate(Alaress_Date_Format::newLocalShort($unit_data['open_date']))
            ->setDueDate(Alaress_Date_Format::newLocalShort($unit_data['due_date']))
        ;
        $unit_instance_list->add($unit_instance);
    }
}
UnitInstanceMapper::getInstance()->save($unit_instance_list);

// Go back to the homepage
$site_page->localRedir('/homepage/'.$homepage_id);
