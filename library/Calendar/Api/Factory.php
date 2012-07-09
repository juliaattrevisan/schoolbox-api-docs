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
	private $allSources = array('Schoolbox', 'Sharepoint', 'Vcal');

	/**
	 * Produce an API Composite
	 *
	 * @return Calendar_Api_Composite
	 */
	public function produce() {
		$compositeApi = new Calendar_Api_Composite();

		/* Alternate data sources
		 */
		foreach ($this->getAllSources() as $currentSource) {
			$defaultImplementation = 'Calendar_Api_' . $currentSource . '_Default';
			$uniqueImplementation = 'Calendar_Api_' . $currentSource . '_' . Cfg::get('site_domain');

			if (class_exists($uniqueImplementation)) {
				$compositeApi->add(new $uniqueImplementation);
			}
			else {
				$compositeApi->add(new $defaultImplementation);
			}
		}

		return $compositeApi;
	}

	/* Getters/Setters
	 */

	/**
	 * Get all sources
	 *
	 * @return array
	 */
	public function getAllSources() {
		return $this->allSources;
	}
}
