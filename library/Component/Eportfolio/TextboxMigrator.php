<?php
/**
 * TextboxMigrator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Component\Eportfolio;

class TextboxMigrator extends MigratorAbstract {

	/**
	 * All textboxes to process
	 *
	 * @var array
	 */
	protected $allTextboxes = array();

	/**
	 * Config definition shared by all textboxes
	 *
	 * @var null
	 */
	protected $configDefinitionId = null;

	/**
	 * Definition id
	 *
	 * @var null
	 */
	protected $definitionId = null;

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
	 * Set all items
	 *
	 * @param null $allTextboxes
	 */
	protected function setAllTextboxes($allTextboxes) {
		$this->allTextboxes = $allTextboxes;
	}

	/**
	 * Get all items
	 *
	 * @return null
	 */
	protected function getAllTextboxes() {
		return $this->allTextboxes;
	}

	/**
	 * Set config definition id shared by all textboxes
	 *
	 * @param null $configDefinitionId
	 */
	protected function setConfigDefinitionId($configDefinitionId) {
		$this->configDefinitionId = $configDefinitionId;
	}

	/**
	 * Get config definition id shared by all textboxes
	 *
	 * @return null
	 */
	protected function getConfigDefinitionId() {
		return $this->configDefinitionId;
	}

	/**
	 * Set component definition id
	 *
	 * @param null $definitionId
	 */
	protected function setDefinitionId($definitionId) {
		$this->definitionId = $definitionId;
	}

	/**
	 * Get component definition id
	 *
	 * @return null
	 */
	protected function getDefinitionId() {
		return $this->definitionId;
	}
}