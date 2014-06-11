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

// Get the config option called "courseHomepage" and save it.
// (Technically not necessary because you can load the list of tasks instead)
$definition = Component_ConfigDefinitionMapper::getInstance()->findOneByColumnList(array(
    'component_definition_id' => $component->getDefinitionId(),
    'key'                     => 'courseHomepage',
));

$config_mapper = Component_ConfigMapper::getInstance();
try {
    $config = $config_mapper->findOneByColumnList(array(
        'component_config_definition_id' => $definition->getId(),
        'component_instance_id'          => $component->getId(),
    ));
}
catch (Schoolbox\Mapper\Exception $e) {
    $config = new Component_Config();
    $config
        ->setConfigDefinitionId($definition->getId())
        ->setInstanceId($component->getId())
    ;
}
$config->setValue(1);
$config_mapper->save($config);

// Change the component for each selected unit.
$deletedUnitList = UnitInstanceMapper::getInstance()->findOrphanedForCategoryId($homepage_id);
foreach ($deletedUnitList as $unit) {
    if (!empty($_POST['unit'][$unit->getId()])) {
        $unit->setComponent($component);
        UnitInstanceMapper::getInstance()->save($unit);
    }
}

// Go back to the homepage
$site_page->localRedir('/homepage/'.$homepage_id);
