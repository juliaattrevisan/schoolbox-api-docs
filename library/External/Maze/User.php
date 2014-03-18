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
	 * An index of all the tablaeus
	 */
	public function tableIndex() {
		$query = "
			SELECT Distinct TABLE_NAME
			FROM information_schema.TABLES
		";

		new \Alaress_Probe($this->getDb()->getAll($query));

		$query = "
			SELECT DB_NAME() AS DataBaseName
		";

		new \Alaress_Probe($this->getDb()->getAll($query));
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

//		$query = "
//			SELECT TOP 5 *
//			FROM ST
//		";
//
//		new \Alaress_Probe($this->getDb()->getAll($query));

		/* The School Year is never represented as just an int, it's always a
		 * string of some sort like "Y04" or "Year 2" or "Students In Year 3"
		 * so you have to replace chars
		 */

		/* Student Tutor, Name and Description are Initials, Formal Name and
		 * Full Name respectively.
		 *
		 * Student Head Of House is also their full name.
		 *
		 * StudentForm is an identifier; StudentFormStaffName is their full name.
		 */
		$query = "
			SELECT
				KLPID AS StudentID,
				FIRST_NAME AS StudentGiven1,
				SURNAME AS StudentSurname,
				BIRTHDATE AS StudentBirthDate,
				'' AS StudentTutor,
				'' AS StudentTutorName,
				'' AS StudentTutorDescription,
				'' AS TeacherTutorID,
				'' AS HeadOfHouseID,
				'' AS StudentHeadOfHouse,
				'' AS StudentForm,
				'' AS StudentFormStaffName,
				'' AS TeacherFormID,
				'' AS FormDescription,
				ST.HOUSE AS StudentHouse,
				KGH.DESCRIPTION AS StudentHouseDescription,
				REPLACE(REPLACE(SCHOOL_YEAR, 'Y0', ''), 'Y', '') AS StudentYearLevel,
				'' AS StudentYearLevelCoordinator,
				'' AS YearLevelCoordID,
				'' AS StudentLockerBook,
				EMERGENCY_ONE_NAME AS StudentEmergencyName,
				EMERGENCY_ONE_PHONE AS StudentEmergencyPhone,
				EMERGENCY_ONE_MOBILE AS StudentEmergencyPhoneAlt,
				EMERGENCY_TWO_NAME AS StudentEmergency2Name,
				EMERGENCY_TWO_PHONE AS StudentEmergency2Phone,
				EMERGENCY_TWO_MOBILE AS StudentEmergency2PhoneAlt,
				'' AS StudentEmergency3Name,
				'' AS StudentEmergency3Phone,
				'' AS StudentEmergency3PhoneAlt,
				MEDICAL_ALERT AS StudentMedicalAlert,
				'' AS StudentSpecialNeedsFlag,
				'' AS AlertMessage,
				'' AS StudentBoarder
			FROM ST
			LEFT JOIN KGH ON ST.HOUSE = KGH.HOUSE
		";

		return $this->getDb()->getAll($query);
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