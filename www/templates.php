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
	array(
		'name' => 'About Me',
		'leftColumnWidth' => 69.9,
		'rightColumnWidth' => 29.9,
		'components' => array(
			array(
				'class' => 'Component_Eportfolio_TextboxController',
				'keys' => array(
					'componentname' => 'Values and Beliefs',
					'description' => 'Values and Beliefs',
					'position' => 'left',
					'sequence' => 1,
				),
			),
			array(
				'class' => 'Component_Eportfolio_TextboxController',
				'keys' => array(
					'componentname' => 'Leadership Initiatives',
					'description' => 'Leadership Initiatives',
					'position' => 'left',
					'sequence' => 2,
				),
			),
			array(
				'class' => 'Component_Eportfolio_AdvancedListController',
				'keys' => array(
					'componentname' => 'Holiday Destinations',
					'description' => 'Holiday Destinations',
					'position' => 'right',
					'sequence' => 1,
				),
			),
			array(
				'class' => 'Component_Eportfolio_BasicListController',
				'keys' => array(
					'componentname' => 'Co-curricular Activities',
					'description' => 'Co-curricular Activities',
					'position' => 'right',
					'sequence' => 2,
				),
			),
			array(
				'class' => 'Component_Eportfolio_BasicListController',
				'keys' => array(
					'componentname' => 'My Hobbies',
					'description' => 'My Hobbies',
					'position' => 'right',
					'sequence' => 3,
				),
			),
		),
	),

	array(
		'name' => 'Career Aspirations',
		'leftColumnWidth' => 69.9,
		'rightColumnWidth' => 29.9,
		'components' => array(
			array(
				'class' => 'Component_Eportfolio_BlogController',
				'keys' => array(
					'componentname' => 'My Career Aspirations',
					'description' => 'My Career Aspirations',
					'position' => 'left',
					'sequence' => 1,
				),
			),
			array(
				'class' => 'Component_Eportfolio_BasicListController',
				'keys' => array(
					'componentname' => 'My Work Experience',
					'description' => 'My Work Experience',
					'position' => 'right',
					'sequence' => 1,
				),
			),
			array(
				'class' => 'Component_Eportfolio_ImageListController',
				'keys' => array(
					'componentname' => 'Images that inspire me',
					'description' => 'Images that inspire me',
					'position' => 'right',
					'sequence' => 2,
				),
			),
		),
	),

	array(
		'name' => 'My Resume',
		'leftColumnWidth' => 69.9,
		'rightColumnWidth' => 29.9,
		'components' => array(
			array(
				'class' => 'Component_Eportfolio_TextboxController',
				'keys' => array(
					'componentname' => 'My Resume',
					'description' => 'My Resume',
					'position' => 'top',
					'sequence' => 1,
				),
			),
			array(
				'class' => 'Component_Eportfolio_TextboxController',
				'keys' => array(
					'componentname' => 'My Dream Job',
					'description' => 'My Dream Job',
					'position' => 'left',
					'sequence' => 1,
				),
			),
			array(
				'class' => 'Component_Eportfolio_ImageListController',
				'keys' => array(
					'componentname' => 'Photos of me',
					'description' => 'Photos of me',
					'position' => 'left',
					'sequence' => 2,
				),
			),
			array(
				'class' => 'Component_Eportfolio_TextboxController',
				'keys' => array(
					'componentname' => 'Career History',
					'description' => 'Career History',
					'position' => 'left',
					'sequence' => 3,
				),
			),
			array(
				'class' => 'Component_Eportfolio_BasicListController',
				'keys' => array(
					'componentname' => 'Hobbies',
					'description' => 'Hobbies',
					'position' => 'right',
					'sequence' => 1,
				),
			),
			array(
				'class' => 'Component_Eportfolio_BasicListController',
				'keys' => array(
					'componentname' => 'Places I\'ve Been',
					'description' => 'Places I\'ve Been',
					'position' => 'right',
					'sequence' => 2,
				),
			),
			array(
				'class' => 'Component_Eportfolio_AdvancedListController',
				'keys' => array(
					'componentname' => 'Hobbies',
					'description' => 'Hobbies',
					'position' => 'right',
					'sequence' => 3,
				),
			),
		),
	),

	array(
		'name' => 'My Learning',
		'leftColumnWidth' => 69.9,
		'rightColumnWidth' => 29.9,
		'components' => array(
			array(
				'class' => 'Component_Eportfolio_BlogController',
				'keys' => array(
					'componentname' => 'My Reflections Blog',
					'description' => 'My Reflections Blog',
					'position' => 'left',
					'sequence' => 1,
				),
			),
			array(
				'class' => 'Component_Eportfolio_TextboxController',
				'keys' => array(
					'componentname' => 'My Goals',
					'description' => 'My Goals',
					'position' => 'left',
					'sequence' => 2,
				),
			),
			array(
				'class' => 'Component_Eportfolio_AdvancedListController',
				'keys' => array(
					'componentname' => 'Awards and Achievements',
					'description' => 'Awards and Achievements',
					'position' => 'right',
					'sequence' => 1,
				),
			),
			array(
				'class' => 'Component_Eportfolio_ImageListController',
				'keys' => array(
					'componentname' => 'Academic Showcase',
					'description' => 'Academic Showcase',
					'position' => 'right',
					'sequence' => 2,
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
			$componentInstance->sequence = $currentComponent['keys']['sequence'];

			$instanceIds = Component_InstanceMapper::getInstance()->save($componentInstance);

			/* Apply config keys for each component
			 */
			if (is_array($instanceIds) && count($instanceIds) && is_numeric($instanceIds[0])) {
				$instanceId = $instanceIds[0];

				foreach($currentComponent['keys'] as $currentKey => $currentValue) {
					$componentConfigDefinition = Component_ConfigDefinitionMapper::getInstance()->findByColumnList(array(
						'component_definition_id' => $componentDefinition->current()->id,
						'key' => $currentKey,
					));

					if ($componentConfigDefinition->count()) {
						$configDefinitionId = $componentConfigDefinition->current()->id;

						/* Put in a new instance
						 */
						$componentConfigInstance = new Component_Config();
						$componentConfigInstance->configDefinitionId = $componentConfigDefinition->current()->id;
						$componentConfigInstance->instanceId = $instanceId;
						$componentConfigInstance->value = $currentValue;

						Component_ConfigMapper::getInstance()->save($componentConfigInstance);
					}
				}
			}
		}
	}
}