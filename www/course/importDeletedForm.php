<?php
/**
 * Form for importing deleted units into a course component
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../include/Bootstrap.php';

use Schoolbox\Assessment\Course\UnitInstanceMapper;

$site_page = new SitePage();

$homepage_id        = $_POST['homepage'];
$component_id       = $_POST['component'];
if (empty($homepage_id) || empty($component_id)) {
    error(2);
}

// Test permissions:
// Write on $homepage_id
$group = new Group();
if ($group->getPermission($homepage_id) < WRITE_ACCESS) {
    error(6);
}

/** @var Component_Instance $component */
$component = Component_InstanceMapper::getInstance()->findById($component_id);

// Change the component for each selected unit.
$deletedUnitList = UnitInstanceMapper::getInstance()->findOrphanedForCategoryId($homepage_id);
foreach ($deletedUnitList as $unit) {
    if (!empty($_POST['unit'][$unit->getId()])) {
        $unit->setComponent($component);
        $unit->setDeleted(null);
        UnitInstanceMapper::getInstance()->save($unit);
    }
}

// Go back to the homepage
$site_page->localRedir('/homepage/'.$homepage_id);
