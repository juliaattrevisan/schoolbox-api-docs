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

			foreach($result['result'] as $currentDefinition) {
				$allDefinitions['desc'][] = $currentDefinition['PeriodDescription'];
				$allDefinitions['start'][] = $currentDefinition['TimeFrom'];
				$allDefinitions['end'][] = $currentDefinition['TimeTo'];
				$allDefinitions['number'][] = preg_replace('/[^0-9]+/', '', $currentDefinition['PeriodNumber']);
				$allDefinitions['sequence'][] = $currentDefinition['DayNumber'];
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
	 */
	public function getPeriodNumber($campusId) {
		$vars = array(
			'sbGetDayNumber',
			array(
				array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
				array('@pCampusID', $campusId, SQLINT4, false, false, -1),
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
	 */
	private function getStaffDayTimetable($externalUserId, $campusId, $dayNumber) {
		$vars = array(
			'sbGetDayNumber',
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
	 */
	private function getStudentDayTimetable($externalUserId, $campusId, $dayNumber) {
		$vars = array(
			'sbGetDayNumber',
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
	 * Get the day timetable for a given person
	 *
	 * @param $externalUserId
	 * @param $userId
	 * @param $campusId
	 * @param $dayNumber
	 * @return bool|void
	 */
	public function getDayTimetable ($externalUserId, $userId, $campusId, $dayNumber) {
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