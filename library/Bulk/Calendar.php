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

			while (($thisRow = fgetcsv($handle, 1000, ",")) !== false) {

				/* Skip empty comments and no dates
				 *
				 * If access is 3, then the sid corresponds to campus id's
				 * If access is 2, then the sid corresponds to group id's
				 */
				$date = new Alaress_Date_Format($thisRow[0], 'd/m/y');

				/* If a publish date is specified, use it; otherwise assume the
				 * Publish Date is the same as the Event Date
				 */
				if (strlen(trim($thisRow[7]))) {
					$publishDate = new Alaress_Date_Format($thisRow[7], 'd/m/y');
				}
				else {
					$publishDate = new Alaress_Date_Format('0000-00-00', 'y-m-d');
				}

				$name       = $thisRow[3];
				$event_type = $thisRow[6];
				if ((trim($thisRow[0]) != '') && (trim($name) != '')) {
					$event = array(
						'owner' => $uid,
						'access' => 3,
						'date' => $date->getDatabase(),
						'startTime' => $thisRow[1],
						'endTime' => $thisRow[2],
						'note' => $name,
						'detail' => $thisRow[4],
						'location' => $thisRow[5],

						/* Hard-coded to zero as this is deprecated
						 */
						'priority' => 0,

						'type' => $event_type,
						'attendance' => $thisRow[8],
						'publish_date' => $publishDate->getDatabase(),
						'campusList' => $campusList,
						'groupList' => $groupList,
					);
					if (empty($event_type)) {
						$output[] = array(
							'name' => Scrub::html($name),
							'status' => 'Not Added',
							'error' => 'Event type not set',
						);
					}
					elseif ($calendarHandler->create($event)) {
						$output[] = array(
							'name' => Scrub::html($name),
							'status' => 'Added',
							'error' => 'OK',
						);
					}
					else {
						$output[] = array(
							'name' => Scrub::html($name),
							'status' => 'Not Added',
							'error' => 'Could not create event',
						);
					}
				}
				else {
					$output[] = array(
						'name' => Scrub::html($name),
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
}
