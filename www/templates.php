<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once('../include/Bootstrap.php');

$templateMapper = new \Eportfolio\TemplateMapper();

$allTemplates = array(
	'template' => array(
		'name' => 'Mortimer',
		'leftColumnWidth' => 17.4,
		'rightColumnWidth' => 80,
		'components' => array(
			array(
				'class' => 'Component_Eportfolio_BasicListController',
				'keys' => array(
					'componentname' => 'The most basic of lists',
					'description' => 'A purpose for the most basic of lists',
					'position' => 'top',
				),
			),
		),
	),
);



foreach($allTemplates as $currentTemplate) {
	$template = new \Eportfolio\Template();
	$template->setName($currentTemplate['name']);
	$template->setLeftColumnWidth($currentTemplate['leftColumnWidth']);
	$template->setRightColumnWidth($currentTemplate['rightColumnWidth']);

	$ids = $templateMapper->save($template);

	if (is_array($ids) && count($ids) && is_numeric($ids[0])) {
		$templateId = $ids[0];

		/* Process each component if the template saved correctly
		 */
		$sequence = 0;

		foreach($currentTemplate['components'] as $currentComponent) {
			$componentDefinition = Component_DefinitionMapper::getInstance()->findByColumn('class', $currentComponent['class']);

			/* Spawn a component instance for the template
			 */
			$componentInstance = new Component_Instance();
			$componentInstance->definitionId = $componentDefinition->current()->id;
			$componentInstance->module = 'eportfolio_template';
			$componentInstance->identifier = $templateId;
			$componentInstance->position = $currentComponent['keys']['position'];
			$componentInstance->sequence = ++$sequence;

			$instanceIds = Component_InstanceMapper::getInstance()->save($componentInstance);

			/* Apply config keys for each component
			 */
			if (is_array($instanceIds) && count($instanceIds) && is_numeric($instanceIds[0])) {
				$instanceId = $instanceIds[0];

				foreach($currentComponent['keys'] as $currentKey => $currentValue) {
					$componentConfigDefinition = Component_ConfigDefinitionMapper::getInstance()->findByColumnList(array(
						'component_definition_id' => $componentDefinition->id,
						'key' => $currentKey,
					));

					if ($componentConfigDefinition->count()) {
						$configDefinitionId = $componentConfigDefinition->current()->id;

						/* Put in a new instance
						 */
						$componentConfigInstance = new Component_Config();
						$componentConfigInstance->configDefinitionId = $componentConfigDefinition->current()->id;
						$componentConfigInstance->instanceId = $instanceId;
echo "Saving"; print_r($componentConfigInstance);
						Component_ConfigMapper::getInstance()->save($componentConfigInstance);
					}
				}
			}
		}
	}
}