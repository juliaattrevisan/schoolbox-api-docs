<?php
/**
 * Bulk_Calendar
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Bulk_Calendar extends Bulk_Abstract {

	/**
	 * Wrapper for uploading a calendar file which could be in either format
	 *
	 * @return array
	 */
	public function addCalendar() {
		if (stristr(file_get_contents($this->getFile(), null, null, 0, 20), 'BEGIN:VCALENDAR')) {
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
	 *
	 * @return array
	 */
	private function addCSVCalendar() {
		if (is_file($this->getFile())) {

			/* Set defaults for id and campus if they are not submitted
			 */
			if ($_POST['userid'] == '') {
				$uid = $_SESSION['id'];
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
				$groupList = $_POST['userGroup'];
			}

			if (is_array($_POST['campusList'])) {
				$campusList = $_POST['campusList'];
			}

			$importer = new \Calendar\Import\Csv($uid, $_SESSION['id'], $groupList, $campusList, $this->getFile());
			$importer->load();

			return $importer->getOutput();
		}

		return array();
	}

	/**
	 * Imports from a vcal file, exported from outlook
	 *
	 * @return array
	 */
	private function addVCalCalendar() {
		if (is_file($this->getFile())) {
			if ($_POST['userid'] == '') {
				$uid = $_SESSION['id'];
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
				$groupList = $_POST['userGroup'];
			}

			if (is_array($_POST['campusList'])) {
				$campusList = $_POST['campusList'];
			}

			/* Process vcal events
			 */
			$v = new \Calendar\Import\Vcal($uid, $_SESSION['id'], $groupList, $campusList, $this->getFile());
			$v->load();

			return $v->getOutput();
		}

		return array();
	}
}
