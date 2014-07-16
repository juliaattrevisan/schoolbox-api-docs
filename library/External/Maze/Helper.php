<?php
/**
 * Helper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Maze_Helper {

	/**
	 * An instance of MSSQL
	 *
	 * @var null
	 */
	private $db = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setDb(MSSQL::getInstance());
	}

	/**
	 * Get the current timetable period eg. 2013S2
	 */
	public function getCurrentTTPeriod() {
		$now = new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');

		$currentYear = $now->getUsingFormat('Y');
		$currentSemester = $this->getCurrentSemester();

		/* TODO: Hard code this for now
		 *
		 * CURRENT_SEMESTER is empty in the Maze db
		 */
		$currentSemester = 2;

		/* Only get subjects that are relevant now
		 */
		$ttPeriod = "{$currentYear}S{$currentSemester}";

		return $ttPeriod;
	}

	/**
	 * Get the current semester
	 *
	 * This should take the form below:
	 * $row['CURRENT_SEMESTER'] = '1998S4';
	 *
	 * @param null $externalUserId
	 * @return mixed
	 */
	public function getCurrentSemester($externalUserId = null) {
		$query = "
			SELECT CURRENT_SEMESTER
			FROM SI
		";

		$row = $this->getDb()->getRow($query);

		preg_match('/\d+S(\d+)/', $row['CURRENT_SEMESTER'], $matches);

		return $matches[1];
	}

	/**
	 * Glue a class code and class number together into a unique
	 * Schoolbox ClassCode
	 *
	 * @param $classCode
	 * @param $classNumber
	 * @return string
	 */
	public function generateClassCode($classCode, $classNumber) {
		return $classCode . '-' . $classNumber;
	}

	/**
	 * Deglue a unique Schoolbox ClassCode into its original constituents
	 *
	 * @param $schoolboxClassCode
	 * @return array
	 */
	public function degenerateClassCode($schoolboxClassCode) {
		list($classCode, $classNumber) = explode('-', $schoolboxClassCode);

		return array(
			'ClassCode' => $classCode,
			'ClassNumber' => $classNumber,
		);
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
}