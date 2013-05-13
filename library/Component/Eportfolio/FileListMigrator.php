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

class FileListMigrator extends MigratorAbstract {

	/**
	 * All files
	 *
	 * @var null
	 */
	private $allFiles = array();

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
		$definition = \Component_DefinitionMapper::getInstance()->findByColumn('class', 'Component_Eportfolio_FileListController');

		$this->setDefinitionId($definition->current()->id);

		/* Set up config definition for this component by spawning one if it doesn't already exist
		 */
		$exists = \Component_ConfigDefinitionMapper::getInstance()->findByColumn('component_definition_id', $definition->current()->id);

		if (!$exists->count()) {
			$configDefinition = new \Component_ConfigDefinition();
			$configDefinition->id = null;
			$configDefinition->definitionId = $definition->current()->id;
			$configDefinition->key = 'componentname';
			$configDefinition->defaultValue = 'My Files';
			$configDefinition->label = 'Title';

			$configDefinitionIds = \Component_ConfigDefinitionMapper::getInstance()->save($configDefinition);

			$this->setConfigDefinitionId($configDefinitionIds[0]);
		}
		else {
			$this->setConfigDefinitionId($exists->current()->id);
		}

		$this->fetchAll();
	}

	/**
	 * Migrate this component
	 */
	public function migrate() {
		if (count($this->getAllFiles())) {

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

			foreach($this->getAllFiles() as $currentFile) {
				$newFileList = new FileList();
				$newFileList->setPageId($this->getPageId());
				$newFileList->setFileId($currentFile['id']);
				$newFileList->setCreatedAt($currentFile['published']);
				$newFileList->setUpdatedAt($currentFile['published']);

				FileListMapper::getInstance()->save($newFileList);
			}
		}
	}

	/**
	 * Fetch all the records for this component
	 */
	protected function fetchAll() {
		$query = "
			SELECT *
			FROM myFile
			WHERE owner = {$this->getUserId()}
			AND published IS NOT NULL
		";

		$result = $this->getDb()->getAll($query);

		if ($result) {
			$this->setAllFiles($result);
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set all items
	 *
	 * @param null $allFiles
	 */
	private function setAllFiles($allFiles) {
		$this->allFiles = $allFiles;
	}

	/**
	 * Get all items
	 *
	 * @return null
	 */
	private function getAllFiles() {
		return $this->allFiles;
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