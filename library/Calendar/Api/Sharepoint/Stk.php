<?php
/**
 * Calendar_Api_Sharepoint_Stk
 *
 * St. Kentigern uses iport, a customisation of Sharepoint.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_Sharepoint_Stk implements Calendar_Api_Interface {

	/**
	 * Constructor
	 */
	public function __construct() {
		$config = array(
			'host' => '10.1.1.29',
			'user' => 'ntdomain\alaress',
			'passwd' => 'Tjs782',
			'db' => 'WSS_Content',
		);

		/* Dodgy dbal implementation requires this
		 */
		$this->setDb(new MSSQL(false, $config));
	}

	/**
	 * Get day events from this data source
	 *
	 * @param $currentDay
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getDayEvents($currentDay) {
		$collection = new Calendar_Event_Collection();

		/* Fill in your query result set here and populate $collection
		 */

		return $collection;
	}

	/**
	 * Get student birthdays from this data source
	 *
	 * @param $currentDay
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getStudentBirthdays($currentDay) {
		$collection = new Calendar_Event_Collection();

		/* Fill in your query result set here and populate $collection
		 */

		return $collection;
	}

	/**
	 * Get submission events from this data source
	 *
	 * @param $currentDay
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getSubmissionEvents($currentDay) {
		$collection = new Calendar_Event_Collection();

		/* Fill in your query result set here and populate $collection
		 */

		return $collection;
	}

	/* Getters/Setters
	 */

	/**
	 * Set db
	 *
	 * @param $db
	 */
	public function setDb($db) {
		$this->db = $db;
	}

	/**
	 * Get db
	 *
	 * @return null
	 */
	public function getDb() {
		return $this->db;
	}
}
