<?php
/**
 * Template
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Eportfolio;
use Eportfolio;
use Schoolbox;

class TemplateMapper extends Schoolbox\Mapper {

	/**
	 * Define column/property mapping
	 *
	 * @return Bool|void
	 */
	protected function mapping() {
		$this->hasTable('eportfolioTemplate');
		$this->hasModel('\\Eportfolio\\Template');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'name',
			'property'   => 'name',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'left_column_width',
			'property'   => 'leftColumnWidth',
			'type'       => self::TYPE_DECIMAL,
		));

		$this->hasMap(array(
			'column'     => 'right_column_width',
			'property'   => 'rightColumnWidth',
			'type'       => self::TYPE_DECIMAL,
		));

		parent::mapping();
	}

	/**
	 * Import a Collection of templates
	 *
	 * @param Schoolbox\Collection $allTemplates
	 */
	public function import(Schoolbox\Collection $allTemplates) {
		foreach($allTemplates as $currentTemplate) {
			$template = new Template();
			$template->setName($currentTemplate['name']);
			$template->setLeftColumnWidth($currentTemplate['leftColumnWidth']);
			$template->setRightColumnWidth($currentTemplate['rightColumnWidth']);

			$ids = $this->save($template);

			if (is_array($ids) && count($ids) && is_numeric($ids[0])) {
				$templateId = $ids[0];

				/* Process each component if the template saved correctly
				 */
				foreach($currentTemplate['components'] as $currentComponent) {
					$componentDefinition = \Component_DefinitionMapper::getInstance()->findByColumn('class', $currentComponent['class']);

					/* Spawn a component instance for the template
					 */
					$componentInstance = new \Component_Instance();
					$componentInstance->definitionId = $componentDefinition->first()->id;
					$componentInstance->module = 'eportfolio_template';
					$componentInstance->identifier = $templateId;
					$componentInstance->position = $currentComponent['keys']['position'];
					$componentInstance->sequence = $currentComponent['keys']['sequence'];

					$instanceIds = \Component_InstanceMapper::getInstance()->save($componentInstance);

					/* Apply config keys for each component
					 */
					if (is_array($instanceIds) && count($instanceIds) && is_numeric($instanceIds[0])) {
						$instanceId = $instanceIds[0];

						foreach($currentComponent['keys'] as $currentKey => $currentValue) {
							$componentConfigDefinition = \Component_ConfigDefinitionMapper::getInstance()->findByColumnList(array(
								'component_definition_id' => $componentDefinition->first()->id,
								'key' => $currentKey,
							));

							/* Put in a new instance
							 */
							if ($componentConfigDefinition->count()) {
								$componentConfigInstance = new \Component_Config();
								$componentConfigInstance->configDefinitionId = $componentConfigDefinition->first()->id;
								$componentConfigInstance->instanceId = $instanceId;
								$componentConfigInstance->value = $currentValue;

								\Component_ConfigMapper::getInstance()->save($componentConfigInstance);
							}
						}
					}
				}
			}
		}
	}
}

