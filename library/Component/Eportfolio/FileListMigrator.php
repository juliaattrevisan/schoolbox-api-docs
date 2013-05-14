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
	 * Controller class for this component
	 *
	 * @var string
	 */
	protected $controllerClass = 'Component_Eportfolio_FileListController';

	/**
	 * Default name for this component
	 *
	 * @var string
	 */
	protected $componentDefaultName = 'My Files';

	/**
	 * All files
	 *
	 * @var null
	 */
	private $allFiles = array();

	/**
	 * Setup
	 */
	protected function setup() {
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
}