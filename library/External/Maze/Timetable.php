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
			if ($date) {
				$periodDate = new \Alaress_Date_Format(strtotime($date), 'U');
			}

			foreach ($result['result'] as $currentPeriod) {
				if (!$date) {
					$periodDate = new \Alaress_Date_Format(strtotime($currentPeriod['CalDate']), 'U');
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

	/**
	 * Calculates the current period based on the current time
	 *
	 * @param $campusId
	 * @return int
	 */
	public function getPeriodNumber($campusId) {
		return 0;
	}

	/**
	 * Return a user's timetable for a specific day
	 *
	 * @param $externalUserId
	 * @param $userId
	 * @param $campusId int ignored
	 * @param $dayNumber
	 * @return array|bool
	 */
	public function getDayTimetable($externalUserId, $userId, $campusId, $dayNumber) {
		return $this->getStudentTimetable($externalUserId, $dayNumber);
	}

	/**
	 * Return a student's timetable for a specific day
	 *
	 * @param $externalUserId
	 * @param $dayNumber
	 * @return mixed
	 */
	private function getStudentTimetable($externalUserId, $dayNumber) {

		/* Get all the subjects for the current ttperiod
		 */
		$query = "
			SELECT *
			FROM STMA
			WHERE SKEY = '{$externalUserId}'
			AND TTPERIOD = '{$this->getHelper()->getCurrentTTPeriod()}'
		";

		$result1 = $this->getDb()->getAll($query);

		/* Get all the periods (I think this sql stored proc is somehow not working)
		 */
		$vars = array(
			'klp_GetTimetable',
			array(
				array('@Today', date('Y-m-d h:i:s'), SQLVARCHAR, false, false, -1),
				array('@ReturnClassPeriodsOnly', 'Y', SQLVARCHAR, false, false, -1),
			)
		);

		$result2 = $this->getDb()->executeProcedure($vars[0], $vars[1]);

		/* Get the timetable definition
		 */
		$vars = array(
			'klp_GetTimetableStructure',
			array(
				array('@Today', null, SQLVARCHAR, false, true, -1),
				array('@ReturnClassPeriodsOnly', 'Y', SQLVARCHAR, false, false, -1),
			)
		);

		$result3 = $this->getDb()->executeProcedure($vars[0], $vars[1]);

		/* Manual join
		 * For every STMA record, find the STMA.IDENT
		 * Find all the GetTimetable.class records that match
		 * Find all the PeriodNo's that GetTimetable.class belongs in
		 */
		$allObjects = array();

		/* Reduce data sets. This generates a list of subjects and the periods
		 * in which they appear stored as an array
		 */
		if (is_array($result1) && count($result1)) {
			foreach($result1 as $currentStma) {
				$object = array();
				$object['stma'] = $currentStma;
				$object['period'] = array();

				/* This tends to be a big data set
				 */
				foreach($result2['result'] as $currentPeriod) {
					if ($currentStma['IDENT'] == $currentPeriod['class']) {
						$object['period'][$currentPeriod['periodno']] = $currentPeriod;
					}
				}

				$allObjects[] = $object;
			}
		}

		/* Invert everything in the timetable definition
		 */
		$allStructures = array();
		$allSubjects = array();

		foreach($result3['result'] as $currentTimetableStructure) {

			/* Restrict to a specified Day
			 * Otherwise skip it
			 */
			if ($currentTimetableStructure['DayNo'] == $dayNumber) {

				/* Clone that guy out
				 */
				$structure = $currentTimetableStructure;
				$structure['class']['subject'] = array();
				$structure['class']['periods'] = array();

				/* Find the subjects which have this period number
				 * This class occurs in $currentTimetableStructure
				 */
				foreach($allObjects as $currentObject) {
					if (in_array($currentTimetableStructure['PeriodNo'], array_keys($currentObject['period']))) {

						/* Assemble
						 */
						$class = $currentObject['period'][$currentTimetableStructure['PeriodNo']];

						/* Separate subject identifier
						 */
						$class['subject'] = $currentObject['stma']['MKEY'];

						/* Stack up
						 */
						$structure['class'] = $class;

						/* Remember these classes, so we can get their details later
						 * in one query and assimilate them into the grand design
						 */
						$allSubjects[] = $currentObject['stma']['MKEY'];
					}
				}

				/* Stack up
				 */
				$allStructures[] = $structure;
			}
		}

		return $allStructures;
	}

	/**
	 * Return the ENTIRE timetable for a user
	 *
	 * @param $externalUserId
	 * @param $userId
	 * @param $campusId
	 * @return array|bool
	 */
	public function getFullTimetable ($externalUserId, $userId, $campusId) {
	}

	/**
	 * Retrieve the list of subjects for a user on a particular day
	 *
	 * @param $externalUserId
	 * @param $dayNumber
	 * @return bool
	 */
	public function getDaySubjects ($externalUserId, $dayNumber) {
	}

	/**
	 * give a subject code and day this function returns the period that it occurs in
	 * Assumption made that this should be the sequence rather than period name
	 *
	 * @param $classCode
	 * @param $dayNumber
	 * @return int|bool
	 */
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