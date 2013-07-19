<?php
/**
 * MigratorAbstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Component\Eportfolio;

abstract class MigratorAbstract {

	/**
	 * Controller class for this component
	 *
	 * @var string
	 */
	protected $controllerClass = '';

	/**
	 * Default name for this component
	 *
	 * @var string
	 */
	protected $componentDefaultName = '';

	/**
	 * An old db handler
	 *
	 * @var null
	 */
	protected $db = null;

	/**
	 * The owner of the eportfolio
	 *
	 * @var null
	 */
	protected $userId = null;

	/**
	 * The page belonging to the user
	 *
	 * @var null
	 */
	protected $pageId = null;

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
	 * Constructor
	 */
	final public function __construct($userId, $pageId) {
		$this->setDb(\DeprecatedDatabase::getInstance());

		$this->setUserId($userId);
		$this->setPageId($pageId);

		/* Force first config
		 */
		$this->firstConfig();

		/* Hook method
		 */
		$this->setup();
	}

	/**
	 * Ensures components have a config record for default names etc.
	 */
	protected function firstConfig() {
		$definition = \Component_DefinitionMapper::getInstance()->findByColumn('class', $this->getControllerClass());
		$id         = $definition->first()->getId();
		$this->setDefinitionId($id);

		/* Set up config definition for this component by spawning one if it doesn't already exist
		 */
		$exists = \Component_ConfigDefinitionMapper::getInstance()->findByColumn('component_definition_id', $id);

		if (!$exists->count()) {
			$configDefinition = new \Component_ConfigDefinition();
			$configDefinition->setId(null);
			$configDefinition->setDefinitionId($id);
			$configDefinition->setKey('componentname');
			$configDefinition->setDefaultValue($this->getComponentDefaultName());
			$configDefinition->setLabel('Title');

			$configDefinitionIds = \Component_ConfigDefinitionMapper::getInstance()->save($configDefinition);

			$this->setConfigDefinitionId($configDefinitionIds[0]);
		}
		else {
			$this->setConfigDefinitionId($exists->first()->getId());
		}
	}

	/**
	 * Hook method
	 *
	 * @return mixed
	 */
	abstract protected function setup();

	/**
	 * Main method
	 *
	 * @return mixed
	 */
	abstract public function migrate();

	/* Getters/Setters
	 */

	/**
	 * Set db
	 *
	 * @param null $db
	 */
	protected function setDb($db) {
		$this->db = $db;
	}

	/**
	 * Get db
	 *
	 * @return null
	 */
	protected function getDb() {
		return $this->db;
	}

	/**
	 * Set page id
	 * 
	 * @param null $pageId
	 */
	protected function setPageId($pageId) {
		$this->pageId = $pageId;
	}

	/**
	 * Get page id
	 *
	 * @return null
	 */
	protected function getPageId() {
		return $this->pageId;
	}

	/**
	 * Set user id
	 *
	 * @param null $userId
	 */
	protected function setUserId($userId) {
		$this->userId = $userId;
	}

	/**
	 * Get user id
	 *
	 * @return null
	 */
	protected function getUserId() {
		return $this->userId;
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

	/**
	 * Set component default name
	 *
	 * @param string $componentDefaultName
	 */
	protected function setComponentDefaultName($componentDefaultName) {
		$this->componentDefaultName = $componentDefaultName;
	}

	/**
	 * Get component default name
	 *
	 * @return string
	 */
	protected function getComponentDefaultName() {
		return $this->componentDefaultName;
	}

	/**
	 * Set controller class
	 *
	 * @param string $controllerClass
	 */
	protected function setControllerClass($controllerClass) {
		$this->controllerClass = $controllerClass;
	}

	/**
	 * Get controller class
	 *
	 * @return string
	 */
	protected function getControllerClass() {
		return $this->controllerClass;
	}
}