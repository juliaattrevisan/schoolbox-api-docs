<?php
/**
 * External_SchoolPro_Timetable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_SchoolPro_Timetable {

	/**
	 * Get a timetable definition
	 *
	 * @param $campusId
	 * @param int $day
	 * @return array|bool
	 */
	public function getTimetableDefinition($campusId, $day=0) {
		$vars = array(
			'sbGetTimetableDefinition',
			array(
				array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pCampusID', $campusId, SQLINT4, false, false, -1),
			)
		);

		$result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

		if ($result['success'] && count($result['result'])) {
			$allDefinitions = array();

			/* Only stack it up if this day is chosen
			 */
			foreach($result['result'] as $currentDefinition) {
				if ($day == $currentDefinition['DayNumber']) {
					$allDefinitions['desc'][] = $currentDefinition['PeriodDescription'];
					$allDefinitions['start'][] = $currentDefinition['TimeFrom'];
					$allDefinitions['end'][] = $currentDefinition['TimeTo'];
					$allDefinitions['number'][] = $currentDefinition['PeriodNumber'];
					$allDefinitions['sequence'][] = null;
				}
			}

			return $allDefinitions;
		}

		return false;
	}

	/**
	 * Get a day number
	 *
	 * @param $campusId
	 * @param int $date
	 * @return bool
	 */
	public function getDayNumber($campusId, $date = 0) {

		/* Convert from timestamp to datetime
		 */
		if ($date) {
			$dateObj = new Alaress_Date_Format($date, 'U');
			$dateToUse = $dateObj->getDatabaseDate();

			$vars = array(
				'sbGetDayNumber',
				array(
					array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
					array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
					array('@pCampusID', $campusId, SQLINT4, false, false, -1),
					array('@pDateToUse', $dateToUse, SQLVARCHAR, false, false, -1),
				)
			);

			$result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

			if ($result['success'] && count($result['result'])) {
				return $result['result'][0]['DayNumber'];
			}
		}

		return false;
	}

	/**
	 * Get period number for a particular campus
	 *
	 * @param $campusId
	 * @return bool
	 */
	public function getPeriodNumber($campusId) {
		$vars = array(
			'sbGetPeriodNumber',
			array(
				array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pCampusID', $campusId, SQLINT4, false, false, -1),
			)
		);

		$result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

		if ($result['success'] && count($result['result'])) {
			return $result['result'][0]['PeriodNumber'];
		}

		return false;
	}

	/**
	 * Get staff day timetable
	 *
	 * @param $externalUserId
	 * @param $campusId
	 * @param $dayNumber
	 */
	private function getStaffDayTimetable($externalUserId, $campusId, $dayNumber) {
		$vars = array(
			'sbGetStaffDayTimetable',
			array(
				array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pSchoolNum', $externalUserId, SQLINT4, false, false, -1),
				array('@pCampusID', $campusId, SQLINT4, false, false, -1),
				array('@pDayNumber', $dayNumber, SQLINT4, false, false, -1),
			)
		);

		$result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

		new \Alaress_Probe($result);

		if ($result['success']) {
		}
		else {
		}
	}

	/**
	 * Get staff day timetable
	 *
	 * @param $externalUserId
	 * @param $campusId
	 * @param $dayNumber
	 * @return array
	 */
	private function getStudentDayTimetable($externalUserId, $campusId, $dayNumber) {
		$definition = $this->getTimetableDefinition($campusId, $dayNumber);

		$vars = array(
			'sbGetStudentDayTimetable',
			array(
				array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pSchoolNum', $externalUserId, SQLINT4, false, false, -1),
				array('@pCampusID', $campusId, SQLINT4, false, false, -1),
				array('@pDayNumber', $dayNumber, SQLINT4, false, false, -1),
			)
		);

		$result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

		if ($result['success'] && count($result['result'])) {
			$timetable = array();
			$allClasses = $result['result'];

			/* For every period defined in the timetable definition,
			 * set up a stub stack which contains all the possible
			 * periods for this day. We'll append actual classes to
			 * this data structure.
			 */
			for ($x = 0; isset($definition['desc'][$x]); $x++) {
				$timetable[$x]['periodName'] = $definition['desc'][$x];
				$timetable[$x]['periodStart'] = $definition['start'][$x];
				$timetable[$x]['periodEnd'] = $definition['end'][$x];
				$timetable[$x]['periodNumber'] = $definition['number'][$x];
				$timetable[$x]['periodSequence'] = $definition['sequence'][$x];
			}

			/* With the result set obtained above, append actual class
			 * details to the period definition established in the previous loop
			 * Shift the $allClasses array as you append them
			 *
			 * In other connectors this is done in a "merge" style operation
			 * which requires only one loop, but I did it this way because
			 * this kind of stuff is currently not documented well.
			 */
			for ($x = 0; $x < count($timetable); $x++) {
				if (count($allClasses)) {
					$currentClass = $allClasses[0];

					/* If this class occurs in this period, append its details
					 */
					if ($timetable[$x]['periodNumber'] == $currentClass['Identifier']) {
						$timetable[$x]['description'] = $currentClass['ClassDescription'];
						$timetable[$x]['code'] = $currentClass['ClassCode'];
						$timetable[$x]['room'] = $currentClass['ClassRoom'];
						$timetable[$x]['staffID'] = $externalUserId;

						array_shift($allClasses);
					}
				}
			}

			return $timetable;
		}
	}

	/**
	 * Get the day timetable for a given person
	 *
	 * @param $externalUserId
	 * @param $userId
	 * @param $campusId
	 * @param $dayNumber
	 * @return bool|void
	 */
	public function getDayTimetable($externalUserId, $userId, $campusId, $dayNumber) {
		$groupHandler = new Group();

		if ($groupHandler->isStudent($userId)) {
			$result = $this->getStudentDayTimetable($externalUserId, $campusId, $dayNumber);
			return $result;
		}
		/* Else assume staff
		 */
		else {
			$result = $this->getStaffDayTimetable($externalUserId, $campusId, $dayNumber);
			return $result;
		}

		return false;
	}

	/**
	 * TODO: No stored proc
	 *
	 * @param $externalUserId
	 * @param $userId
	 * @param $campusId
	 */
	public function getFullTimetable ($externalUserId, $userId, $campusId) {
	}

	/**
	 * TODO: No stored proc
	 *
	 * @param $externalUserId
	 * @param $dayNumber
	 */
	public function getDaySubjects ($externalUserId, $dayNumber) {
	}

	/**
	 * TODO: No stored proc
	 *
	 * @param $classCode
	 * @param $dayNumber
	 */
	public function getSubjectPeriod ($classCode, $dayNumber) {
	}

	/**
	 * TODO: No stored proc
	 *
	 * @param $classCode
	 */
	public function getSubjectName ($classCode) {
	}
}