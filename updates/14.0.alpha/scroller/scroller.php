<?php
/**
 * Add randomise option to image scroller
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__.'/../../../include/Bootstrap.php';

/* Definition for Scroller
 */
$componentDefinition = Component_DefinitionMapper::getInstance()->findByColumn('class', 'Component_Homepage_ScrollerController')->first();

/* Add the new config option to the db
 */
$componentConfigDefinition = new Component_ConfigDefinition();
$componentConfigDefinition->setDefinitionId($componentDefinition->getId());
$componentConfigDefinition->setKey('randomStartingSlide');
$componentConfigDefinition->setDefaultValue(0);
$componentConfigDefinition->setLabel('Random start');
$componentConfigDefinition->setValue(0);

Component_ConfigDefinitionMapper::getInstance()->save($componentConfigDefinition);

/* Find all the instances of Scroller
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
