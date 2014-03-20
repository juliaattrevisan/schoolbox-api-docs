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
		$ttPeriod = $this->getHelper()->getCurrentTTPeriod();

		$query = "
			SELECT
				ST.STKEY AS ID,
				ST.FIRST_NAME AS Firstname,
				ST.SURNAME AS Surname,
				REPLACE(REPLACE(SCHOOL_YEAR, 'Y0', ''), 'Y', '') AS YearLevel,
				KGH.DESCRIPTION AS House,
				'' AS MedicalAlert,
				'' AS SpecialNeedsFlag,
				'' AS ClassCampus
			FROM STMA
			LEFT JOIN SU ON STMA.MKEY = SU.SUKEY
			LEFT JOIN ST ON STMA.SKEY = ST.STKEY
			LEFT JOIN KGH ON ST.HOUSE = KGH.HOUSE
			WHERE SU.SUKEY = '{$classCode}'
			AND TTPERIOD = '{$ttPeriod}'
		";

		$allStudents = $this->getDb()->getAll($query);

		$studentList = array();

		if (is_array($allStudents) && count($allStudents)) {
			foreach($allStudents as $currentStudent) {
				$student = array(
					'synergy_id' => $currentStudent['ID'],
					'lastname' => $currentStudent['Firstname'],
					'firstname' => $currentStudent['Surname'],
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