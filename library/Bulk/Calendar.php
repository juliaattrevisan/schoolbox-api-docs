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

			/* Process rows
			 */
			$handle = fopen($this->getFile(), 'r');
			$headerRow = fgetcsv($handle, 0, ",");

			while (($thisRow = fgetcsv($handle, 0, ",")) !== false) {
				//
				// We cannot determine encoding, so strip everything not ascii
				$thisRow = array_map(
					function($field) { return preg_replace('/[\\x80-\\xFF]/', '', $field); },
					$thisRow
				);
				list(
					$start_date,
					$start_time,
					$finish_date,
					$finish_time,
					$all_day,
					$name,
					$detail,
					$location,
					$event_type,
					$publish_date,
					$attendance
				) = $thisRow;

				/* Skip empty comments and no dates
				 *
				 * If access is 3, then the sid corresponds to campus id's
				 * If access is 2, then the sid corresponds to group id's
				 */
				$start = new Alaress_Date_Format($start_date, 'd/m/y');
				//
				// Finish empty? Use start date as the finish.
				$finish = (strlen(trim($finish_date))) ?
					new Alaress_Date_Format($finish_date, 'd/m/y'):
					clone $start;

				//
				// Start or finish time empty? Default to start and end of day.
				$start_time  = strlen(trim($start_time))  ? $start_time  : '00:00:00';
				$finish_time = strlen(trim($finish_time)) ? $finish_time : '23:59:59';

				/* If a publish date is specified, use it; otherwise use an empty date
				 */
				if (strlen(trim($publish_date))) {
					$publish_date = new Alaress_Date_Format($publish_date, 'd/m/y');
				}
				else {
					$publish_date = new Alaress_Date_Format('0000-00-00', 'y-m-d');
				}

				//
				// All day? if "all day" flag is set.
				// (maybe if start_time = 00:00:00 and end_time = 23:59:59 too?)
				$all_day =
					in_array(trim(strtolower($all_day)), array('y', 'yes', '1'))
					|| ($start_time === '00:00:00' && $finish_time === '23:59:59')
				;

				if ((trim($thisRow[0]) != '') && (trim($name) != '')) {
					$event = array(
						'owner' => $uid,
						'access' => Calendar::ACCESS_CAMPUS,
						'startDate' => $start->getDatabase(),
						'endDate' => $finish->getDatabase(),
						'startTime' => $start_time,
						'endTime' => $finish_time,
						'note' => $name,
						'detail' => nl2br($detail),
						'location' => $location,
						'allday' => $all_day,

						/* Hard-coded to zero as this is deprecated
						 */
						'priority' => 0,

						'type' => $event_type,
						'attendance' => $attendance,
						'publish_date' => $publish_date->getDatabase(),
						'campusList' => $campusList,
						'groupList' => $groupList,
					);



					if (empty($event_type)) {
						$output[] = array(
							'name' => Scrub::text($name),
							'status' => 'Not Added',
							'error' => 'Event type not set',
						);
					}
					elseif ($calendarHandler->create($event)) {
						$output[] = array(
							'name' => Scrub::text($name),
							'status' => 'Added',
							'error' => 'OK',
						);
					}
					else {
						$output[] = array(
							'name' => Scrub::text($name),
							'status' => 'Not Added',
							'error' => 'Could not create event',
						);
					}
				}
				else {
					$output[] = array(
						'name' => Scrub::text($name),
						'status' => 'Not Added',
						'error' => 'Missing date and/or name',
					);
				}

				/* Free memory */
				unset($publish_date, $start, $finish);
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
			$v = new ICal_Creator($uid, $groupList, $campusList, $this->getFile());
			$v->load();

			return $v->getOutput();
		}

		return array();
	}
}
