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
			SELECT TOP 10 *
			FROM STMA
			LEFT JOIN SU ON STMA.MKEY = SU.SUKEY
			LEFT JOIN ST ON STMA.SKEY = ST.STKEY
			WHERE SU.SUKEY = '{$classCode}'
			AND TTPERIOD = '{$ttPeriod}'
		";

		return $this->getDb()->getAll($query);
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