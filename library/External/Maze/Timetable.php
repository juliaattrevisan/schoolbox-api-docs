<?php
/**
 * External_Maze_Timetable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Maze_Timetable {
	/**
	 * An instance of MSSQL
	 *
	 * @var null
	 */
	private $db = null;

	/**
	 * Helper
	 *
	 * @var null
	 */
	private $helper = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setDb(MSSQL::getInstance());
		$this->setHelper(new External_Maze_Helper());
	}

	/**
	 * The headings of the timetable, going horizontally
	 * from left to right containing all the times and day numbers
	 *
	 * @param $campusId
	 * @param int $day
	 * @return array
	 */
	public function getTimetableDefinition($campusId, $day=0) {
		$vars = array(
			'klp_GetTimetableStructure',
			array(
				array('@Today', date('Y-m-d'), SQLVARCHAR, false, true, -1),
				array('@ReturnClassPeriodsOnly', 'Y', SQLVARCHAR, false, false, -1),
			)
		);

		$result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

		$timetableDef = array();
		$now = new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

		/* Distill data
			 [138] => Array
                (
                    [PeriodNo] => 139
                    [DayName] => Day 5
                    [PerNoDesc] => Period 7
                    [DayIndex] => 12
                    [DayNo] => 5
                    [CalDate] => Mar 28 2014 12:00:00:000AM
                    [CalRow] => 13
                    [qrow] => 13
                )

            [139] => Array
                (
                    [PeriodNo] => 140
                    [DayName] => Day 5
                    [PerNoDesc] => Period 0
                    [DayIndex] => 12
                    [DayNo] => 5
                    [CalDate] => Mar 28 2014 12:00:00:000AM
                    [CalRow] => 14
				)
		*/
		if (is_array($result['result']) && count($result['result'])) {
			foreach ($result['result'] as $currentPeriod) {
				$periodDate = new \Alaress_Date_Format(strtotime($currentPeriod['CalDate']), 'U');

				if ($periodDate->getDatabaseDate() == $now->getDatabaseDate()) {
					if (!$day || $day == $currentPeriod['DayNo']) {
						$timetableDef['desc'][] = $currentPeriod['PerNoDesc'];
						$timetableDef['start'][] = '';
						$timetableDef['end'][] = '';
						$timetableDef['number'][] = $currentPeriod['PeriodNo'];
						$timetableDef['sequence'][] = $currentPeriod['PeriodNo'];
					}
				}
			}
		}

		return $timetableDef;
	}

	/**
	 * Get the current day number
	 *
	 * @param $campusId
	 * @param int $date
	 * @return bool
	 */
	public function getDayNumber($campusId, $date = 0) {
		$vars = array(
			'klp_GetTimetableStructure',
			array(
				array('@Today', date('Y-m-d'), SQLVARCHAR, false, true, -1),
				array('@ReturnClassPeriodsOnly', 'Y', SQLVARCHAR, false, false, -1),
			)
		);

		$result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

		$now = new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

		if (is_array($result['result']) && count($result['result'])) {
			foreach ($result['result'] as $currentPeriod) {
				if (!$date) {
					$periodDate = new \Alaress_Date_Format(strtotime($currentPeriod['CalDate']), 'U');
				}
				else {
					$periodDate = new \Alaress_Date_Format(strtotime($date), 'U');
				}

				if ($periodDate->getDatabaseDate() == $now->getDatabaseDate()) {
					return $currentPeriod['DayNo'];
				}
			}
		}

		return false;
	}

	public function temp() {

		new \Alaress_Probe($timetableDefinition);

		die();



		$vars = array(
			'klp_GetTimetable',
			array(
				array('@Today', date('Y-m-d'), SQLVARCHAR, false, true, -1),
				array('@ReturnClassPeriodsOnly', 'Y', SQLVARCHAR, false, false, -1),
			)
		);

		$result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

		if (is_array($result['result']) && count($result['result'])) {
			foreach ($result['result'] as $currentPeriod) {
				new \Alaress_Probe($currentPeriod);
			}
		}


//		$vars = array(
//			'klp_TermDates',
//			array(
//				array('@CurrDate', date('Y-m-d h:i:s'), SQLVARCHAR, false, false, -1),
//				array('@TermStart', null, SQLVARCHAR, true, true, -1),
//				array('@TermEnd', null, SQLVARCHAR, true, true, -1),
//				array('@QuiltDays', null, SQLVARCHAR, true, true, -1),
//			)
//		);
//
//		$result = $this->getDb()->executeProcedure($vars[0], $vars[1]);
//
//		Array
//		(
//			[success] => 1
// 			[output] => Array
//				(
//					[@TermStart] => J
//		            [@TermEnd] => A
//      		    [@QuiltDays] => *
//      		)
//
//		    [result] => Array
//			(
//			)
//
//		    [message] => Changed database context to 'LoretoKirribilli'.
//		)
//
//		new \Alaress_Probe($result);
	}

	public function getPeriodNumber ($campusId) {
	}

	public function getDayTimetable ($externalUserId, $userId, $campusId, $dayNumber) {
	}

	public function getFullTimetable ($externalUserId, $userId, $campusId) {
	}

	public function getDaySubjects ($externalUserId, $dayNumber) {
	}

	public function getSubjectPeriod ($classCode, $dayNumber) {
	}

	/**
	 * Get the subject name given a string
	 *
	 * @param $classCode
	 * @return bool
	 */
	public function getSubjectName($classCode) {
		$query = "
		 	SELECT TOP 1 FULLNAME
		 	FROM SU
		 	WHERE SUKEY = '{$classCode}'
		 ";

		$subject = $this->getDb()->getRow($query);

		if ($subject) {
			return $subject['FULLNAME'];
		}

		return false;
	}

	/* Getters/Setters
	 */

	/**
	 * Set db
	 *
	 * @param null $db
	 */
	private function setDb($db) {
		$this->db = $db;
	}

	/**
	 * Get db
	 *
	 * @return null
	 */
	private function getDb() {
		return $this->db;
	}

	/**
	 * Set helper
	 *
	 * @param null $helper
	 */
	private function setHelper($helper) {
		$this->helper = $helper;
	}

	/**
	 * Get helper
	 *
	 * @return null
	 */
	private function getHelper() {
		return $this->helper;
	}
} 