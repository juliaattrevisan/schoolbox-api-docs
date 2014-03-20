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
	 */
	public function getTimetableDefinition($campusId, $day=0) {
		$vars = array(
			'klp_GetTimetable',
			array(
				array('@Today', null, SQLVARCHAR, false, false, -1),
				array('@ReturnClassPeriodsOnly', 'Y', SQLVARCHAR, false, false, -1),
			)
		);

		$result = $this->getDb()->executeProcedure($vars[0], $vars[1]);

		var_dump($result);
//
//		$query = "
//			SELECT *
//			FROM TH
//		";
//
//		return $this->getDb()->getAll($query);
	}

	public function getDayNumber ($campusId, $date = 0) {
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