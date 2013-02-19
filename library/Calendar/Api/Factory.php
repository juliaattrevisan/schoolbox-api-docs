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
	private $allTypes = array('Event', 'StudentBirthday', 'Submission');

	/**
	 * Available accessor classes for each event type
	 *
	 * @var array
	 */
	private $allSources = array('Sharepoint', 'Schoolbox');

	/**
	 * Produce an API Composite
	 *
	 * @return Calendar_Api_Composite
	 */
	public function produce() {
		$compositeApi = new Calendar_Api_Composite();

		/* Alternative data sources
		 */
		foreach($this->getAllTypes() as $currentType) {
			foreach ($this->getAllSources() as $currentSource) {

				$defaultImplementation = 'Calendar_Api_' . $currentType . '_' . $currentSource . '_Default';
				$uniqueImplementation = 'Calendar_Api_' . $currentType . '_' . $currentSource . '_' . ucwords(Cfg::get('site_location'));

				/* Decide which to use (unique or default)
				 * Or create no class, if neither exists
				 */
				if (class_exists($uniqueImplementation)) {
					$compositeApi->add(new $uniqueImplementation);
				}
				else if (class_exists($defaultImplementation)) {
					$compositeApi->add(new $defaultImplementation);
				}
			}
		}

		/* Throw error if no APIs were determined and found
		if (!$compositeApi->getCount()) {
			throw new Exception('No Calendar APIs found for your implementation.');
		}
		 */

		return $compositeApi;
	}

	/* Getters/Setters
	 */

	/**
	 * Get all sources
	 *
	 * @return array
	 */
	private function getAllSources() {
		return $this->allSources;
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
