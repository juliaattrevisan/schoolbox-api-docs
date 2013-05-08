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
	protected $allTypes = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$allConfigRecords = Calendar_Api_Config_Mapper::getInstance()->getAllConfigRecords();

		/* These configs are base64-encode()'d to get around a weird issue with unserialize
		 * http://stackoverflow.com/questions/4264270/unserialize-sometimes-returns-false
		 */
		foreach($allConfigRecords as $currentConfig) {

			$config = $currentConfig->decodeConfig();

			/* Update $config so it knows the calendar_connector id from whence it came
			 * This is used in produce() and passed to each unique/default implementation
			 */
			$config->setId($currentConfig->getId());

			$this->addConfig($config);
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
			$defaultImplementation = 'Calendar_Api_EventType_' . $config->getEventType() . '_' . $config->getConnectorClass() . '_Default';
			$uniqueImplementation = 'Calendar_Api_EventType_' . $config->getEventType() . '_' . $config->getConnectorClass() . '_' . ucwords(Cfg::get('site_location'));

			/* Decide which to use (unique or default)
			 * Or create no class, if neither exists
			 *
			 * Inject config settings, including the calendar_connector id
			 * so it knows from whence it came
			 */
			if (class_exists($uniqueImplementation)) {
				$implementation = new $uniqueImplementation($config);
				$implementation->setConnectorId($config->getId());

				$compositeApi->add($implementation);
			}
			else if (class_exists($defaultImplementation)) {
				$implementation = new $defaultImplementation($config);
				$implementation->setConnectorId($config->getId());

				$compositeApi->add($implementation);
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
	 * Get all types
	 *
	 * @return array
	 */
	protected function getAllTypes() {
		return $this->allTypes;
	}
}
