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

class ObjectiveMigrator extends TextboxMigrator {

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
	 * Slightly different behaviour
	 *
	 * @return mixed|void
	 */
	protected function setup() {
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
}