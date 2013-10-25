<?php
/**
 * External_SchoolPro_User
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_SchoolPro_User implements External_UserInterface {

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
		$this->setDatabase(MSSQL::getInstance());
	}

	public function getPortrait() {

		'spgTimetableDay',
			array(
				array('@TimeTableGroup', $campusModel->getExtTimetableCode(), SQLVARCHAR, false, false, -1),
				array('@Date',           date('Y-m-d', $date),                SQLVARCHAR, false, false, -1),
				array('@CurrentDay',     'day',                               SQLINT4,    true,  false, -1),
			)
	}

	/* Getters/Setters
	 */

	/**
	 * @param null $db
	 */
	private function setDb($db) {
		$this->db = $db;
	}

	/**
	 * @return null
	 */
	private function getDb() {
		return $this->db;
	}
}