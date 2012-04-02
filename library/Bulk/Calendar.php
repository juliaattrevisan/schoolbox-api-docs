<?php
/**
 * Bulk_Calendar
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Bulk_Calendar {
	private $file = null;

	/**
	 * Constructor
	 *
	 * @param $file
	 */
	public function __construct($file) {
		$this->setFile($file);
	}

	/**
	 * Wrapper for uploading a calendar file which could be in either format
	 *
	 * @return array
	 */
	public function addCalendar() {
		if (stristr(file_get_contents($this->getFile(), NULL, NULL, 0, 20), 'BEGIN:VCALENDAR')) {
			return $this->addVCalCalendar($this->getFile());
		}
		else {
			return $this->addCSVCalendar($this->getFile());
		}
	}

	/**
	 * Imports events from csv
	 *
	 * @param <type> $file
	 * @return array
	 */
	private function addCSVCalendar() {
		$calendarHandler = new Calendar();

		/* List of outcomes
		 */
		$output = array();

		if (is_file($this->getFile())) {

			/* Set defaults for id and campus if they are not submitted
			 */
			if ($_POST['userid'] == '') {
				$uid = Scrub::text($_SESSION['id']);
			}
			else {
				$uid = Scrub::text($_POST['userid']);
			}

			/* Set up specified campus list
			 * Set up specified group list
			 */
			$groupList = array();
			$campusList = array();

			if (is_array($_POST['userGroup'])) {
				$groupList = array_map(array(Scrub, 'text'), $_POST['userGroup']);
			}

			if (is_array($_POST['campusList'])) {
				$campusList = array_map(array(Scrub, 'text'), $_POST['campusList']);
			}

			/* Process rows
			 */
			$handle = fopen($this->getFile(), 'r');
			$headerRow = fgetcsv($handle, 1000, ",");

			while (($thisRow = fgetcsv($handle, 1000, ",")) !== FALSE) {

				/* Skip empty comments and no dates
				 *
				 * If access is 3, then the sid corresponds to campus id's
				 * If access is 2, then the sid corresponds to group id's
				 */
				$date = new Alaress_Date_Format(Scrub::html($thisRow[0]), 'd/m/y');

				if ((trim($thisRow[0]) != '') && (trim($thisRow[3]) != '')) {
					$event = array(
						'owner' => $uid,
						'access' => 3,
						'date' => $date->getDatabase(),
						'startTime' => Scrub::html($thisRow[1]),
						'endTime' => Scrub::html($thisRow[2]),
						'note' => Scrub::html($thisRow[3]),
						'detail' => Scrub::html($thisRow[4]),
						'location' => Scrub::html($thisRow[5]),
						'type' => Scrub::html($thisRow[6]),
						'campusList' => $campusList,
						'groupList' => $groupList,
					);

					if ($calendarHandler->create($event)) {
						$output[] = array(
							'name' => Scrub::html($thisRow[3]),
							'status' => 'Added',
							'error' => 'OK',
						);
					}
					else {
						$output[] = array(
							'name' => Scrub::html($thisRow[3]),
							'status' => 'Not Added',
							'error' => 'Could not create event',
						);
					}
				}
				else {
					$output[] = array(
						'name' => Scrub::html($thisRow[3]),
						'status' => 'Not Added',
						'error' => 'Missing date and/or name',
					);
				}
			}
		}

		return $output;
	}

	/**
	 * Imports from a vcal file, exported from outlook
	 *
	 * @return array
	 */
	private function addVCalCalendar() {
		if (is_file($this->getFile())) {
			if ($_POST['userid'] == '') {
				$uid = Scrub::text($_SESSION['id']);
			}
			else {
				$uid = $_POST['userid'];
			}

			/* Set up specified campus list
			 * Set up specified group list
			 */
			$groupList = array();
			$campusList = array();

			if (is_array($_POST['userGroup'])) {
				$groupList = array_map(array(Scrub, 'text'), $_POST['userGroup']);
			}

			if (is_array($_POST['campusList'])) {
				$campusList = array_map(array(Scrub, 'text'), $_POST['campusList']);
			}

			/* Process vcal events
			 */
			$v = new ICal_Creator($uid, $groupList, $campusList, $this->getFile());
			$v->load();

			return $v->getOutput();
		}

		return array();
	}

	/* Getters/Setters
	 */

	/**
	 * Set file
	 *
	 * @param $file
	 */
	private function setFile($file) {
		$this->file = $file;
	}

	/**
	 * Get file
	 *
	 * @return null
	 */
	private function getFile() {
		return $this->file;
	}
}
