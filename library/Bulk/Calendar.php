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

			/* Use factory to spawn a builder
			 */
			$factory = new \Calendar\Import\Factory($uid, $_SESSION['id'], $groupList, $campusList, $this->getFile());
			$strategy = $factory->build();
			$strategy->load();

			return $strategy->getOutput();
		}

		return array();
	}
}
