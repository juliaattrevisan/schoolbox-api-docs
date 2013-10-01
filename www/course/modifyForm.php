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

// Save each set of dates.
/** @var Collection|UnitInstance[] $course_instance_list */
$course_instance_list = UnitInstanceMapper::getInstance()->findByColumn('component_id', $component_id);
foreach ($course_instance_list as $course) {
    foreach ($_POST['unit'] as $unit_data) {
        if ($unit_data['unit_instance_id'] == $course->getId()) {
            $course
                ->setOpenDate(new Alaress_Date_Format($unit_data['open_date'], 'd/m/y'))
                ->setDueDate(new Alaress_Date_Format($unit_data['due_date'], 'd/m/y'))
            ;
            break;
        }
    }
}
UnitInstanceMapper::getInstance()->save($course_instance_list);


// Go back to the homepage
$site_page->localRedir('/homepage/'.$component->getIdentifier());
