<?php
/**
 * External_Maze_User
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Maze_User implements External_UserInterface {

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
	 * Gets the contact info of the person who is a Student's Contact. The ID
	 * passed is the Synergetic of the Contact, not the Student.
	 *
	 * @param $externalId
	 * @return array|bool
	 */
	public function getParent($externalId) {
	}

	/**
	 * Gets the contact info of the person who is a Student's Contact. The ID
	 * passed is the Synergetic of the Contact, not the Student. Anyone who is
	 * not supposed to be having their details publicised (via the
	 * DirectoryIncludeFlag) are not included in the result set.
	 * Accept array of Synergy IDs, or just an int
	 *
	 * @param $externalId
	 *
	 * @return mixed
	 */
	public function getParentSync($externalId) {
	}

	/**
	 * Get all the parents emails for a class, or group of students
	 *
	 * @param array $synergyIds
	 *
	 * @return array
	 */
	public function getParentEmails($synergyIds) {
	}

	/**
	 * Get a guy's info from Schoolpro
	 *
	 * @param  $synId
	 * @return array|bool
	 */
	public function getStudent($synId) {
	}

	/**
	 * Get a guy's info from Schoolpro
	 *
	 * @param  $synId
	 * @return array|bool
	 */
	public function getStaff($synId) {
	}

	/**
	 * Get a list of student contacts (parents) and their contact information
	 *
	 * @param $externalUserId
	 * @return mixed
	 */
	public function getStudentContactList($externalUserId) {
	}

	/**
	 * Gets a list of Contacts for all Students in a particular year level. The
	 * year level is stored in Synergetic.
	 *
	 * Used in reporting.
	 *
	 * Note that there is no StudentContactHomeCountryDesc in the Synergetic views
	 * so I changed it to use CountryCode instead.
	 *
	 * @param $yearlevel
	 * @param $name
	 * @param $boardingStatus
	 * @param $form
	 *
	 * @return mixed
	 */
	public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form) {
	}

	/**
	 * @param null $externalUserId
	 */
	public function getCurrentSemester($externalUserId = null) {
	}

	/**
	 * Get a list of subjects for a particular user
	 *
	 * @param $externalUserId
	 * @param $userId
	 * @return array|bool
	 */
	public function getSubjectList($externalUserId, $userId) {
	}

	/**
	 * Returns a list of externalIds
	 *
	 * @param $synergyID
	 * @return array|bool
	 */
	public function getEnrolledStudents($synergyID) {
	}

	/**
	 * Get an email address for a particular person
	 *
	 * @param $externalUserId
	 * @return bool
	 */
	public function getEmail($externalUserId) {
	}

	/**
	 * Returns a local path to the user's portrait
	 *
	 * This indicates that Cedar needs to upload the pics manually to portrait_dir
	 *
	 * @param Integer $externalUserId
	 * @return Bool|String
	 */
	public function getPortrait($externalUserId) {
	}

	/**
	 * Stub method, probably not used
	 */
	public function getStudentUserAccountInfo() {
	}

	/**
	 * Stub method, probably not used
	 */
	public function getParentUserAccountInfo() {
	}

	/**
	 * Stub method, probably not used
	 */
	public function getStaffUserAccountInfo() {
	}

	/**
	 * Stub method, probably not used
	 */
	public function determineBoarderType($boarderValue) {
	}

	/**
	 * Stub method, probably not used
	 */
	public function getHousemasterByHouseId($houseID) {
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