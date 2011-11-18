<?php
/**
 * Synchronise_External_Donvale_Facade
 *
 * Facade pattern to batch process all four synchronisation tasks.
 *
 * Import data from random external CSV source and put
 * it into database.
 *
 * Due to dependencies in input data these must be run
 * in the following order:
 *
 * Students (via User)
 * Staff (via User)
 * Class
 * ClassInvolvement
 * Timetable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_External_Donvale_Facade {
	private $webFormatting = false;

	/**
	 * Process all synchronisation tasks
	 *
	 * @return void
	 */
	public function process() {
		$allStrategies = array(
			array(
				'name' => 'Synchronise_External_Donvale_User',
				'input' => Cfg::get('donvale_students_csv'),
			),
			array(
				'name' => 'Synchronise_External_Donvale_User',
				'input' => Cfg::get('donvale_staff_csv'),
			),
			array(
				'name' => 'Synchronise_External_Donvale_Class',
				'input' => Cfg::get('donvale_classes_csv'),
			),
			array(
				'name' => 'Synchronise_External_Donvale_ClassInvolvement',
				'input' => Cfg::get('donvale_classinvolvement_csv'),
			),
			array(
				'name' => 'Synchronise_External_Donvale_Timetable',
				'input' => Cfg::get('donvale_timetable_csv'),
			),
		);

		foreach ($allStrategies as $currentStrategy) {
			$class = $currentStrategy['name'];

			$sync = new $class($currentStrategy['input']);

			if ($this->getWebFormatting() === true) {
				$sync->useWebFormatting();
			}
			else {
				$sync->useCliFormatting();
			}

			$sync->process();
		}
	}

	/**
	 * Specify user wants to display on a webpage
	 *
	 * @return void
	 */
	public function useWebFormatting() {
		$this->webFormatting = true;
	}

	/**
	 * Specify user wnats to display on cli
	 *
	 * @return void
	 */
	public function useCliFormatting() {
		$this->webFormatting = false;
	}

	/* Setters/Getters
	 */

	/**
	 * Get web formatting
	 *
	 * @return bool
	 */
	public function getWebFormatting() {
		return $this->webFormatting;
	}
}
