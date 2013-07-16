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
	 * Controller class for this component
	 *
	 * @var string
	 */
	protected $controllerClass = 'Component_Eportfolio_TextboxController';

	/**
	 * Default name for this component
	 *
	 * @var string
	 */
	protected $componentDefaultName = 'My Textbox';

	/**
	 * All textboxes to process
	 *
	 * @var array
	 */
	protected $allTextboxes = array();

	/**
	 * Setup
	 */
	protected function setup() {
		$this->fetchAll('eportfolio_textBox');
	}

	/**
	 * Migrate this component
	 */
	public function migrate() {

		/* And deal with them all
		 */
		if (count($this->getAllTextboxes())) {
			foreach($this->getAllTextboxes() as $currentTextbox) {

				/* Insert new component instance record
				 */
				$newComp = new \Component_Instance();
				$newComp
					->setDefinitionId($this->getDefinitionId())
					->setIdentifier($this->getPageId())
					->setModule('eportfolio')
					->setSequence(0)
					->setPosition('left');

				$newCompIds = \Component_InstanceMapper::getInstance()->save($newComp);

				$componentInstanceID = $newCompIds[0];

				$newTextbox = new Textbox();
				$newTextbox->setComponentInstanceId($componentInstanceID);
				$newTextbox->setContent($currentTextbox['body']);

				TextboxMapper::getInstance()->save($newTextbox);

				$configInstance = new \Component_Config();
				$configInstance
					->setConfigDefinitionId($this->getConfigDefinitionId())
					->setInstanceId($componentInstanceID)
					->setValue($currentTextbox['heading']);

				\Component_ConfigMapper::getInstance()->save($configInstance);
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
			WHERE userID = {$this->getUserId()}
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
}