<?php
/**
 * External_Maze_Class
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Maze_Class implements External_ClassInterface {

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
	 * Returns a list of students that are associated with a class
	 *
	 * @param $classCode
	 * @return array
	 */
	public function getStudents($classCode) {
		$ttPeriod = $this->getHelper()->getCurrentTHKEY();

		$ingredients = $this->getHelper()->degenerateClassCode($classCode);

		$query = "
			SELECT
				ST.STKEY AS ID,
				ST.FIRST_NAME AS Firstname,
				ST.SURNAME AS Surname,
				REPLACE(REPLACE(SCHOOL_YEAR, 'Y0', ''), 'Y', '') AS YearLevel,
				KGH.DESCRIPTION AS House,
				MEDICAL AS MedicalAlert,
				'' AS SpecialNeedsFlag,
				'' AS ClassCampus
			FROM STMA
			LEFT JOIN SU ON STMA.MKEY = SU.SUKEY
			LEFT JOIN ST ON STMA.SKEY = ST.STKEY
			LEFT JOIN KGH ON ST.HOUSE = KGH.HOUSE
			WHERE SU.SUKEY = '{$ingredients['ClassCode']}'
			AND STMA.CLASS = '{$ingredients['ClassNumber']}'
			AND TTPERIOD = '{$ttPeriod}'
			AND ST.STATUS != 'LEFT'
		";

		$part1 = $this->getDb()->getAll($query);

		if (!$part1) {
			$part1 = array();
		}

		$query = "
			SELECT
				ST.STKEY AS ID,
				ST.FIRST_NAME AS Firstname,
				ST.SURNAME AS Surname,
				REPLACE(REPLACE(SCHOOL_YEAR, 'Y0', ''), 'Y', '') AS YearLevel,
				KGH.DESCRIPTION AS House,
				MEDICAL AS MedicalAlert,
				'' AS SpecialNeedsFlag,
				'' AS ClassCampus
			FROM ST
			LEFT JOIN KGC ON ST.ROLL_GROUP = KGC.ROLL_GROUP
			LEFT JOIN KGH ON ST.HOUSE = KGH.HOUSE
			WHERE KGC.ROLL_GROUP = '{$classCode}'
			AND ST.STATUS != 'LEFT'
		";

		$part2 = $this->getDb()->getAll($query);

		if (!$part2) {
			$part2 = array();
		}

		$allStudents = array_merge($part1, $part2);

		$studentList = array();

		if (is_array($allStudents) && count($allStudents)) {
			foreach($allStudents as $currentStudent) {
				$student = array(
					'synergy_id' => $currentStudent['ID'],
					'firstname' => $currentStudent['Firstname'],
					'lastname' => $currentStudent['Surname'],
					'yearlevel' => $currentStudent['YearLevel'],
					'house' => $currentStudent['House'],
					'StudentMedicalAlert' => $currentStudent['MedicalAlert'],
					'StudentSpecialNeedsFlag' => $currentStudent['SpecialNeedsFlag'],
					'ClassCampus' => $currentStudent['ClassCampus'],
				);

				$studentList[] = $student;
			}
		}

		return $studentList;
	}

	/**
	 * Returns a list of *THE IDs OF* teachers that are associated with a class (method is misnamed)
	 *
	 * @param $classCode
	 * @return array
	 */
	public function getTeacherName($classCode) {
		$ttPeriod = $this->getHelper()->getCurrentTHKEY();

		/* Assume Schoolbox $classCodes are in the format: '07SCI-1'
		 * where the 1 represents the Class which could be
		 * class 1, 2, 3 etc. of a particular subject
		 */
		$ingredients = $this->getHelper()->degenerateClassCode($classCode);

		$query = "
			SELECT
				T1TEACH,
				T2TEACH,
				'' AS T3TEACH
			FROM TT
			LEFT JOIN TTTG ON TT.TTKEY = TTTG.GKEY
			WHERE TT.TTPERIOD = '{$ttPeriod}'
			AND TTTG.SUBJ = '{$ingredients['ClassCode']}'
			AND CLASS = '{$ingredients['ClassNumber']}'
		";

		$part1 = $this->getDb()->getAll($query);

		if (!$part1) {
			$part1 = array();
		}

		$query = "
			SELECT
				TEACHER AS T1TEACH,
				TEACHERB AS T2TEACH,
				TEACHERC AS T3TEACH
			FROM KGC
			WHERE KGC.ROLL_GROUP = '{$classCode}'
		";

		$part2 = $this->getDb()->getAll($query);

		if (!$part2) {
			$part2 = array();
		}

		$allTeachers = array_merge($part1, $part2);

		/* Uniqueify everything
		 */
		$teacherKeys = array();

		if (is_array($allTeachers) && count($allTeachers)) {
			foreach($allTeachers as $currentTeacher) {
				$teacherKeys[$currentTeacher['T1TEACH']] = '';
				$teacherKeys[$currentTeacher['T2TEACH']] = '';
				$teacherKeys[$currentTeacher['T3TEACH']] = '';
			}
		}

		/* Filter out empty values
		 */
		$teacherKeys = array_filter(array_keys($teacherKeys));

		/* A list of ids
		 */
		return $teacherKeys;
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