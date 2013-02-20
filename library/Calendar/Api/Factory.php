<?php
/**
 * Calendar_Api_Factory
 *
 * Creates an API composite.
 *
 * Produce a stack of API classes both based on school domain and
 * data source. Uses the Composite pattern to treat an amalgamation
 * of APIs as a single API class.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_Factory {

	/**
	 * A list of all the possible event types to display
	 * 
	 * @var array
	 */
	private $allTypes = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$configMapper = Calendar_Api_Config_Mapper::getInstance();

		$allConfigs = $configMapper->findAll();

		for ($allConfigs->rewind(); $allConfigs->valid(); $allConfigs->next()) {
			$current = $allConfigs->current();

			/* These configs are base64-encode()'d to get around a weird issue with unserialize
			 * http://stackoverflow.com/questions/4264270/unserialize-sometimes-returns-false
			 */
			$this->addConfig(unserialize(base64_decode($current->getConfig())));
		}
	}

	/**
	 * Produce an API Composite
	 *
	 * @return Calendar_Api_Composite
	 */
	public function produce() {
		$compositeApi = new Calendar_Api_Composite();

		foreach($this->getAllTypes() as $config) {
			$defaultImplementation = 'Calendar_Api_' . $config->getEventType() . '_' . $config->getConnectorClass() . '_Default';
			$uniqueImplementation = 'Calendar_Api_' . $config->getEventType() . '_' . $config->getConnectorClass() . '_' . ucwords(Cfg::get('site_location'));

			/* Decide which to use (unique or default)
			 * Or create no class, if neither exists
			 *
			 * Inject config settings
			 */
			if (class_exists($uniqueImplementation)) {
				$compositeApi->add(new $uniqueImplementation($config));
			}
			else if (class_exists($defaultImplementation)) {
				$compositeApi->add(new $defaultImplementation($config));
			}
		}

		/* Throw error if no APIs were determined and found
		if (!$compositeApi->getCount()) {
			throw new Exception('No Calendar APIs found for your implementation.');
		}
		 */

		return $compositeApi;
	}

	/**
	 * Empty list of configs
	 */
	public function resetConfigs() {
		$this->allTypes = array();

		return $this;
	}

	/**
	 * Use only preselected configs
	 *
	 * @param $config
	 *
	 * @return \Calendar_Api_Factory
	 */
	public function addConfig($config) {
		$this->allTypes[] = $config;

		return $this;
	}

	/* Getters/Setters
	 */

	/**
	 * Set all types
	 *
	 * @param array $allTypes
	 */
	private function setAllTypes($allTypes) {
		$this->allTypes = $allTypes;
	}

	/**
	 * Get all types
	 *
	 * @return array
	 */
	private function getAllTypes() {
		return $this->allTypes;
	}
}
