<?php
/**
 * Add collapse option to everything
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__.'/../../../include/Bootstrap.php';

/* Definition for all components
 */
$allComponentDefinitions = Component_DefinitionMapper::getInstance()->findAllForModule('homepage');

foreach ($allComponentDefinitions as $componentDefinition) {

    /* Add the new config option to the db
     */
    $componentConfigDefinition = new Component_ConfigDefinition();
    $componentConfigDefinition->setDefinitionId($componentDefinition->getId());
    $componentConfigDefinition->setKey('collapse');
    $componentConfigDefinition->setDefaultValue(0);
    $componentConfigDefinition->setLabel('Collapse Content');
    $componentConfigDefinition->setValue(0);

    Component_ConfigDefinitionMapper::getInstance()->save($componentConfigDefinition);

    /* Find all the instances
     */
    $allInstances = Component_InstanceMapper::getInstance()->findByColumn('component_definition_id', $componentDefinition->getId());

    /* Add the new component config for every instance
     */
    foreach($allInstances as $currentInstance) {
        $config = new Component_Config();
        $config->setConfigDefinitionId($componentConfigDefinition->getId());
        $config->setInstanceId($currentInstance->getId());
        $config->setValue(0);

        Component_ConfigMapper::getInstance()->save($config);
    }
}