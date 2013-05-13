<?php
/**
 * BlogMigrator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Component\Eportfolio;

class TextboxMigrator extends MigratorAbstract {

	/**
	 * All known forms of textbox
	 *
	 * @var array
	 */
	private $textboxRegistry = array(
		'Award' => 'eportfolio_objective_award',
		'Cover Letter' => 'eportfolio_objective_coverletter',
		'Education History' => 'eportfolio_objective_educationhistory',
		'Employment History' => 'eportfolio_objective_employmenthistory',
		'Goal' => 'eportfolio_objective_goal',
		'Interest' => 'eportfolio_objective_interest',
		'Skill' => 'eportfolio_objective_skill',
	);

	/**
	 * All textboxes to process
	 * This changes between the values described in $textboxRegistry
	 *
	 * @var array
	 */
	private $allTextboxes = array();

	/**
	 * Config definition shared by all textboxes
	 *
	 * @var null
	 */
	private $configDefinitionId = null;

	/**
	 * Definition id
	 *
	 * @var null
	 */
	private $definitionId = null;

	/**
	 * Setup
	 */
	protected function setup() {
		$definition = \Component_DefinitionMapper::getInstance()->findByColumn('class', 'Component_Eportfolio_TextboxController');

		$this->setDefinitionId($definition->current()->id);

		/* Set up config definition for this component by spawning one if it doesn't already exist
		 */
		$exists = \Component_ConfigDefinitionMapper::getInstance()->findByColumn('component_definition_id', $definition->current()->id);

		if (!$exists->count()) {
			$configDefinition = new \Component_ConfigDefinition();
			$configDefinition->id = null;
			$configDefinition->definitionId = $definition->current()->id;
			$configDefinition->key = 'componentname';
			$configDefinition->defaultValue = 'My Textbox';
			$configDefinition->label = 'Title';

			$configDefinitionIds = \Component_ConfigDefinitionMapper::getInstance()->save($configDefinition);

			$this->setConfigDefinitionId($configDefinitionIds[0]);
		}
		else {
			$this->setConfigDefinitionId($exists->current()->id);
		}
	}

	/**
	 * Migrate this component
	 */
	public function migrate() {

		/* Deal with every record in this table
		 */
		foreach($this->getTextboxRegistry() as $label => $table) {

			/* Get all the instances for this particular table
			 */
			$this->fetchAll($table);

			/* Process the entirety of that particular table into individual textboxes
			 * There should only be one record per person, but just in case...
			 */
			if (count($this->getAllTextboxes())) {
				foreach($this->getAllTextboxes() as $currentTextbox) {

					/* Insert new component instance record
					 */
					$newComp = new \Component_Instance();
					$newComp->definitionId = $this->getDefinitionId();
					$newComp->identifier = $this->getPageId();
					$newComp->module = 'eportfolio';
					$newComp->sequence = 0;
					$newComp->position = 'left';

					$newCompIds = \Component_InstanceMapper::getInstance()->save($newComp);

					$componentInstanceID = $newCompIds[0];

					$newTextbox = new Textbox();
					$newTextbox->setComponentInstanceId($componentInstanceID);
					$newTextbox->setContent($currentTextbox['body']);

					TextboxMapper::getInstance()->save($newTextbox);

					$configInstance = new \Component_Config();
					$configInstance->configDefinitionId = $this->getConfigDefinitionId();
					$configInstance->instanceId = $componentInstanceID;
					$configInstance->value = $label;

					\Component_ConfigMapper::getInstance()->save($configInstance);
				}
			}
		}
	}

	/**
	 * Fetch all the records for this component
	 */
	protected function fetchAll($table) {

		/* Don't forget to reset after each table is processed
		 */
		$this->setAllTextboxes(array());

		$query = "
			SELECT *
			FROM {$table}
			WHERE user_id = {$this->getUserId()}
		";

		$result = $this->getDb()->getAll($query);

		if ($result) {
			$this->setAllTextboxes($result);
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set textbox registry
	 *
	 * @param array $textboxRegistry
	 */
	private function setTextboxRegistry($textboxRegistry) {
		$this->textboxRegistry = $textboxRegistry;
	}

	/**
	 * Get textbox registry
	 *
	 * @return array
	 */
	private function getTextboxRegistry() {
		return $this->textboxRegistry;
	}

	/**
	 * Set all items
	 *
	 * @param null $allTextboxes
	 */
	private function setAllTextboxes($allTextboxes) {
		$this->allTextboxes = $allTextboxes;
	}

	/**
	 * Get all items
	 *
	 * @return null
	 */
	private function getAllTextboxes() {
		return $this->allTextboxes;
	}

	/**
	 * Set config definition id shared by all textboxes
	 *
	 * @param null $configDefinitionId
	 */
	private function setConfigDefinitionId($configDefinitionId) {
		$this->configDefinitionId = $configDefinitionId;
	}

	/**
	 * Get config definition id shared by all textboxes
	 *
	 * @return null
	 */
	private function getConfigDefinitionId() {
		return $this->configDefinitionId;
	}

	/**
	 * Set component definition id
	 *
	 * @param null $definitionId
	 */
	private function setDefinitionId($definitionId) {
		$this->definitionId = $definitionId;
	}

	/**
	 * Get component definition id
	 *
	 * @return null
	 */
	private function getDefinitionId() {
		return $this->definitionId;
	}
}